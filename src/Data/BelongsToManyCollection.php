<?php

    namespace Wixnit\Data;

    use JsonSerializable;
    use Wixnit\Exception\RelationException;
    use Wixnit\Exception\DatabaseException;

    /**
     * A lazy, array-like view over a #[BelongsToMany] relation - the tier-1 many-to-many
     * flavor, backed by a plain junction table with no rich data of its own. Mirrors
     * HasManyCollection's read behavior (non-materializing count()/index access, paged
     * streaming iteration) but replaces add() with attach()/detach()/sync(), since
     * associating two existing rows (rather than creating a new child) is what a
     * many-to-many relation actually needs.
     *
     *   #[BelongsToMany(Tag::class, pivot: 'post_tag', localKey: 'postid', relatedKey: 'tagid')]
     *   public BelongsToManyCollection $tags;
     *
     *   $post->tags->count();          // one COUNT(*) against the pivot table
     *   $post->tags->attach($tag);      // INSERT IGNORE into post_tag - safe to call twice
     *   $post->tags->detach($tag);      // DELETE the pivot row
     *   $post->tags->sync([$a, $b]);    // replace the full set
     */
    class BelongsToManyCollection implements \ArrayAccess, \Countable, \IteratorAggregate, JsonSerializable
    {
        private Transactable $parent;
        private RelationDefinition $definition;
        private ?array $items = null;
        private bool $loaded = false;

        private int $streamPageSize = 500;

        function __construct(Transactable $parent, RelationDefinition $definition)
        {
            $this->parent = $parent;
            $this->definition = $definition;
        }

        /**
         * Forces the whole relation to load right now, if it hasn't already.
         * @return static
         */
        public function load(): static
        {
            if(!$this->loaded)
            {
                $ids = $this->pivotRelatedIds();
                $this->items = $this->fetchByIds($ids);
                $this->loaded = true;
            }
            return $this;
        }

        /**
         * @param array $items
         * @return void
         */
        public function primeWith(array $items): void
        {
            $this->items = array_values($items);
            $this->loaded = true;
        }

        public function isLoaded(): bool
        {
            return $this->loaded;
        }

        /**
         * @return array force a full load and return a plain array
         */
        public function all(): array
        {
            $this->load();
            return $this->items;
        }

        /**
         * Associate one or more existing rows with this relation. Safe to call more than
         * once for the same pair - INSERT IGNORE means attaching an already-attached row
         * is a harmless no-op rather than a duplicate-key error.
         * @param Transactable|array $items
         * @return static
         */
        public function attach(Transactable|array $items): static
        {
            $items = is_array($items) ? $items : [$items];
            $conn = $this->parent->getConnection();
            $localValue = $this->localValue();

            $sql = "INSERT IGNORE INTO ".$this->definition->pivotTable.
                " (".$this->definition->pivotLocalKey.", ".$this->definition->pivotRelatedKey.") VALUES (?, ?)";

            $stmt = $conn->prepare($sql);

            if($stmt === false)
            {
                throw DatabaseException::QueryFailed(__METHOD__, $sql, [], $conn->error, $conn->errno);
            }

            foreach($items as $item)
            {
                $relatedValue = $item->id;
                $stmt->bind_param("ss", $localValue, $relatedValue);

                if(!$stmt->execute())
                {
                    throw DatabaseException::QueryFailed(__METHOD__, $sql, [$localValue, $relatedValue], $stmt->error, $stmt->errno);
                }
            }

            $this->invalidate();
            return $this;
        }

        /**
         * Remove the association between this relation and one or more existing rows -
         * does not delete the related rows themselves, only the pivot entry.
         * @param Transactable|array $items
         * @return static
         */
        public function detach(Transactable|array $items): static
        {
            $items = is_array($items) ? $items : [$items];
            $conn = $this->parent->getConnection();
            $localValue = $this->localValue();

            $sql = "DELETE FROM ".$this->definition->pivotTable.
                " WHERE ".$this->definition->pivotLocalKey."=? AND ".$this->definition->pivotRelatedKey."=?";

            $stmt = $conn->prepare($sql);

            if($stmt === false)
            {
                throw DatabaseException::QueryFailed(__METHOD__, $sql, [], $conn->error, $conn->errno);
            }

            foreach($items as $item)
            {
                $relatedValue = $item->id;
                $stmt->bind_param("ss", $localValue, $relatedValue);

                if(!$stmt->execute())
                {
                    throw DatabaseException::QueryFailed(__METHOD__, $sql, [$localValue, $relatedValue], $stmt->error, $stmt->errno);
                }
            }

            $this->invalidate();
            return $this;
        }

        /**
         * Replace the full set of associated rows: detaches everything not in $items,
         * attaches everything in $items that isn't already there.
         * @param array $items
         * @return static
         */
        public function sync(array $items): static
        {
            $conn = $this->parent->getConnection();
            $localValue = $this->localValue();

            $sql = "DELETE FROM ".$this->definition->pivotTable." WHERE ".$this->definition->pivotLocalKey."=?";
            $stmt = $conn->prepare($sql);

            if($stmt === false)
            {
                throw DatabaseException::QueryFailed(__METHOD__, $sql, [], $conn->error, $conn->errno);
            }

            $stmt->bind_param("s", $localValue);

            if(!$stmt->execute())
            {
                throw DatabaseException::QueryFailed(__METHOD__, $sql, [$localValue], $stmt->error, $stmt->errno);
            }

            if(count($items) > 0)
            {
                $this->attach($items);
            }
            else
            {
                $this->invalidate();
            }
            return $this;
        }

        /**
         * @return int number of associated rows - a single COUNT(*) against the pivot
         *              table if not yet loaded, never a full load just to answer this
         */
        public function count(): int
        {
            if($this->loaded)
            {
                return count($this->items);
            }

            $conn = $this->parent->getConnection();
            $localValue = $this->localValue();

            $sql = "SELECT COUNT(1) AS c FROM ".$this->definition->pivotTable." WHERE ".$this->definition->pivotLocalKey."=?";
            $stmt = $conn->prepare($sql);

            if($stmt === false)
            {
                throw DatabaseException::QueryFailed(__METHOD__, $sql, [], $conn->error, $conn->errno);
            }

            $stmt->bind_param("s", $localValue);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            return (int)($row['c'] ?? 0);
        }

        public function offsetExists(mixed $offset): bool
        {
            if($this->loaded)
            {
                return isset($this->items[$offset]);
            }
            if(!is_int($offset) || ($offset < 0))
            {
                return false;
            }
            $ids = $this->pivotRelatedIds(1, $offset);
            return count($ids) > 0;
        }

        public function offsetGet(mixed $offset): mixed
        {
            if($this->loaded)
            {
                return $this->items[$offset] ?? null;
            }
            if(!is_int($offset) || ($offset < 0))
            {
                return null;
            }
            $ids = $this->pivotRelatedIds(1, $offset);

            if(count($ids) === 0)
            {
                return null;
            }
            $found = $this->fetchByIds($ids);
            return $found[0] ?? null;
        }

        /**
         * $collection[] = $tag delegates to attach() - the offset itself is ignored.
         */
        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->attach($value);
        }

        public function offsetUnset(mixed $offset): void
        {
            throw new RelationException(
                "BelongsToManyCollection does not support unset() by position.\n".
                "  Why: a pivot relation is unordered - there's no stable meaning to \"the item at position {$offset}\".\n".
                "  Fix: call detach(\$item) with the specific related object you want to remove."
            );
        }

        /**
         * Streams the relation in fixed-size pages when it hasn't been loaded yet.
         * @return \Generator
         */
        public function getIterator(): \Generator
        {
            if($this->loaded)
            {
                foreach($this->items as $item)
                {
                    yield $item;
                }
                return;
            }

            $offset = 0;

            while(true)
            {
                $ids = $this->pivotRelatedIds($this->streamPageSize, $offset);

                if(count($ids) === 0)
                {
                    break;
                }
                foreach($this->fetchByIds($ids) as $item)
                {
                    yield $item;
                }
                if(count($ids) < $this->streamPageSize)
                {
                    break;
                }
                $offset += $this->streamPageSize;
            }
        }

        private function invalidate(): void
        {
            $this->loaded = false;
            $this->items = null;
        }

        /**
         * @param int|null $limit
         * @param int|null $offset
         * @return array related-side ids stored in the pivot table for this parent
         */
        private function pivotRelatedIds(?int $limit = null, ?int $offset = null): array
        {
            $conn = $this->parent->getConnection();
            $localValue = $this->localValue();

            $sql = "SELECT ".$this->definition->pivotRelatedKey." FROM ".$this->definition->pivotTable.
                " WHERE ".$this->definition->pivotLocalKey."=?";

            if($limit !== null)
            {
                $sql .= " LIMIT ".((int)$limit)." OFFSET ".((int)($offset ?? 0));
            }

            $stmt = $conn->prepare($sql);

            if($stmt === false)
            {
                throw DatabaseException::QueryFailed(__METHOD__, $sql, [], $conn->error, $conn->errno);
            }

            $stmt->bind_param("s", $localValue);
            $stmt->execute();
            $result = $stmt->get_result();

            $ids = [];
            while($row = $result->fetch_assoc())
            {
                $ids[] = $row[$this->definition->pivotRelatedKey];
            }
            return $ids;
        }

        /**
         * @param array $ids
         * @return array related model instances matching the given ids, via one query
         */
        private function fetchByIds(array $ids): array
        {
            if(count($ids) === 0)
            {
                return [];
            }

            $related = $this->definition->related;
            $idColumn = strtolower(array_reverse(explode("\\", $related))[0])."id";
            $conn = $this->parent->getConnection();

            $result = $related::Get($conn, new Filter([$idColumn => new In(...$ids)]));
            return $result->list;
        }

        private function localValue(): string
        {
            return $this->parent->id;
        }

        public function jsonSerialize(): array
        {
            return $this->items ?? [];
        }
    }
