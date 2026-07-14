<?php

    namespace Wixnit\Data;

    use Wixnit\Utilities\Span;
    use Wixnit\Exception\RelationException;

    /**
     * A lazy, array-like view over a #[HasMany] relation - the flavor recommended for
     * relations expected to grow large. Nothing queries the database until the
     * collection is actually touched, and count()/single-index access are specifically
     * designed to avoid loading the whole relation just to answer a small question.
     *
     * Built and bound to its parent automatically - application code never constructs
     * one directly, just declares the property:
     *
     *   #[HasMany(Review::class, 'userid')]
     *   public HasManyCollection $reviews;
     *
     *   $user->reviews->count();      // one COUNT(*), never loads the rows
     *   $user->reviews[3];             // one LIMIT 1 OFFSET 3, never loads the rest
     *   foreach($user->reviews as $r) // streams in pages, never holds the whole set at once
     *   $user->reviews->all();         // explicit, opt-in full materialization
     *   $user->reviews->add($review);  // sets the foreign key on $review and saves it
     */
    class HasManyCollection implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
    {
        private Transactable $parent;
        private RelationDefinition $definition;
        private ?array $items = null;
        private bool $loaded = false;

        /** number of rows fetched per page while streaming an un-loaded collection via foreach */
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
                $related = $this->definition->related;
                $result = $related::Get($this->parent->getConnection(), new Filter([$this->definition->foreignKey => $this->localValue()]));
                $this->items = $result->list;
                $this->loaded = true;
            }
            return $this;
        }

        /**
         * Marks the collection as already loaded with a known set of items, without
         * running a query - used by batched eager-loading (With()) so a collection that
         * was already fetched as part of a page-wide query doesn't get queried again the
         * first time it's touched.
         * @param array $items
         * @return void
         */
        public function primeWith(array $items): void
        {
            $this->items = array_values($items);
            $this->loaded = true;
        }

        /**
         * @return bool whether this collection has already been fully loaded into memory
         */
        public function isLoaded(): bool
        {
            return $this->loaded;
        }

        /**
         * Force a full load and return the result as a plain array - the explicit,
         * opt-in escape hatch for when real array semantics (array_map(), spreading,
         * json_encode(), etc.) are genuinely needed.
         * @return array
         */
        public function all(): array
        {
            $this->load();
            return $this->items;
        }

        /**
         * Add one child, or an array of children, to this relation: sets the foreign key
         * on each to the parent's id and saves it. This is the only sanctioned way to
         * associate a new child - there's no path where mutating the collection can
         * silently fail to persist, because every mutation is a save.
         * @param Transactable|array $children
         * @return static
         */
        public function add(Transactable|array $children): static
        {
            $items = is_array($children) ? $children : [$children];
            $fk = $this->definition->foreignKey;
            $localValue = $this->localValue();

            foreach($items as $child)
            {
                $child->$fk = $localValue;
                $child->save();

                if($this->loaded)
                {
                    $this->items[] = $child;
                }
            }
            return $this;
        }

        /**
         * @return int the number of related rows - a single COUNT(*) if not yet loaded,
         *              never a full load just to answer this
         */
        public function count(): int
        {
            if($this->loaded)
            {
                return count($this->items);
            }
            $related = $this->definition->related;
            return $related::Count($this->parent->getConnection(), new Filter([$this->definition->foreignKey => $this->localValue()]));
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
            return $this->fetchOne($offset) !== null;
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
            return $this->fetchOne($offset);
        }

        /**
         * $collection[] = $child and $collection[$anything] = $child both delegate to
         * add() - the offset itself is ignored, since a query-backed collection has no
         * meaningful position to assign into other than "add this as a new child".
         */
        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->add($value);
        }

        public function offsetUnset(mixed $offset): void
        {
            throw new RelationException(
                "HasManyCollection does not support unset() yet.\n".
                "  Why: removing/detaching a child from a relation isn't implemented in this pass.\n".
                "  Fix: delete or reassign the child directly instead, e.g. \$child->delete();, until a remove()/detach() method exists."
            );
        }

        /**
         * Streams the relation in fixed-size pages when it hasn't been loaded yet, so
         * `foreach` over a large relation doesn't hold the whole thing in memory at once.
         * If the collection was already loaded (directly, or primed by With()), iterates
         * the already-fetched set instead of querying again.
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

            $related = $this->definition->related;
            $offset = 0;

            while(true)
            {
                $page = $related::Get(
                    $this->parent->getConnection(),
                    new Filter([$this->definition->foreignKey => $this->localValue()]),
                    new Span($offset, $offset + $this->streamPageSize - 1)
                );

                if($page->count() === 0)
                {
                    break;
                }
                foreach($page as $item)
                {
                    yield $item;
                }
                if($page->count() < $this->streamPageSize)
                {
                    break;
                }
                $offset += $this->streamPageSize;
            }
        }

        private function fetchOne(int $offset): ?Transactable
        {
            $related = $this->definition->related;
            $page = $related::Get(
                $this->parent->getConnection(),
                new Filter([$this->definition->foreignKey => $this->localValue()]),
                new Span($offset, $offset)
            );
            return ($page->count() > 0) ? $page->list[0] : null;
        }

        private function localValue(): string
        {
            $key = $this->definition->localKey;
            return ($key === null) ? $this->parent->id : $this->parent->$key;
        }

        /**
         * Only serializes if already loaded, same reasoning as LazyText - a lazy
         * relation shouldn't be forced to load just to build a JSON response. Use
         * With() on the query first if the relation should be included.
         * @return array
         */
        public function jsonSerialize(): mixed
        {
            return $this->loaded ? $this->items : [];
        }
    }
