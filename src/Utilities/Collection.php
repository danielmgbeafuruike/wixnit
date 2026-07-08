<?php

    namespace Wixnit\Utilities;

    use ArrayAccess;
    use ArrayIterator;
    use Countable;
    use IteratorAggregate;
    use JsonSerializable;
    use Traversable;

    /**
     * A fluent, chainable wrapper around an array, e.g.:
     *
     *   Collection::Make($users)
     *       ->filter(fn($u) => $u->active)
     *       ->sortBy("name")
     *       ->pluck("email")
     *       ->implode(", ");
     *
     * Works with `foreach`, `count()`, array access (`$collection["key"]`), and `json_encode()`
     * out of the box. For static, one-off array operations without the fluent wrapper, see
     * `Arr` / `ArrayUtil` instead - this class is largely built on top of them.
     */
    class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
    {
        protected array $list = [];

        public function __construct(array $items = [])
        {
            $this->list = $items;
        }

        /**
         * create a new collection from an array
         * @param array $items
         * @return static
         */
        public static function Make(array $items = []): static
        {
            return new static($items);
        }


        #region access

        /**
         * get the underlying array
         * @return array
         */
        public function all(): array
        {
            return $this->list;
        }

        /**
         * alias of all() - get the underlying array
         * @return array
         */
        public function toArray(): array
        {
            return $this->list;
        }

        /**
         * convert the collection to a JSON string
         * @param int $flags optional json_encode() flags, e.g. JSON_PRETTY_PRINT
         * @return string
         */
        public function toJson(int $flags = 0): string
        {
            return json_encode($this->list, $flags);
        }

        /**
         * get the number of items in the collection
         * @return int
         */
        public function count(): int
        {
            return count($this->list);
        }

        /**
         * is the collection empty?
         * @return bool
         */
        public function isEmpty(): bool
        {
            return count($this->list) === 0;
        }

        /**
         * does the collection have at least one item?
         * @return bool
         */
        public function isNotEmpty(): bool
        {
            return count($this->list) > 0;
        }

        /**
         * get the first item (optionally the first that passes a callback), or a default value
         * @param callable|null $callback
         * @param mixed $default
         * @return mixed
         */
        public function first(?callable $callback = null, mixed $default = null): mixed
        {
            return Arr::First($this->list, $callback, $default);
        }

        /**
         * get the last item (optionally the last that passes a callback), or a default value
         * @param callable|null $callback
         * @param mixed $default
         * @return mixed
         */
        public function last(?callable $callback = null, mixed $default = null): mixed
        {
            if($callback === null)
            {
                return Arr::Last($this->list, $default);
            }

            $matches = array_values(array_filter($this->list, $callback));
            return (count($matches) > 0) ? $matches[count($matches) - 1] : $default;
        }

        /**
         * get an item by key, or a default value if it doesn't exist. Supports dot notation for nested arrays.
         * @param string|int $key
         * @param mixed $default
         * @return mixed
         */
        public function get(string | int $key, mixed $default = null): mixed
        {
            if(is_int($key))
            {
                return $this->list[$key] ?? $default;
            }
            return Arr::Get($this->list, $key, $default);
        }
        #endregion


        #region transformation (each of these returns a new Collection, leaving the original untouched)

        /**
         * keep only the items that pass a callback, e.g. ->filter(fn($x) => $x > 5).
         * With no callback, removes falsy values (same as PHP's array_filter() with no callback).
         * @param callable|null $callback
         * @return static
         */
        public function filter(?callable $callback = null): static
        {
            $filtered = ($callback === null) ? array_filter($this->list) : array_filter($this->list, $callback);
            return new static(array_values($filtered));
        }

        /**
         * transform every item through a callback
         * @param callable $callback
         * @return static
         */
        public function map(callable $callback): static
        {
            return new static(array_map($callback, $this->list));
        }

        /**
         * run a callback for every item without transforming the collection. Stop early by
         * returning false from the callback.
         * @param callable $callback
         * @return static
         */
        public function each(callable $callback): static
        {
            $keys = array_keys($this->list);
            for($i = 0; $i < count($keys); $i++)
            {
                if($callback($this->list[$keys[$i]], $keys[$i]) === false)
                {
                    break;
                }
            }
            return $this;
        }

        /**
         * reduce the collection down to a single value
         * @param callable $callback
         * @param mixed $initial
         * @return mixed
         */
        public function reduce(callable $callback, mixed $initial = null): mixed
        {
            return array_reduce($this->list, $callback, $initial);
        }

        /**
         * pluck a single column of values out of a collection of arrays/objects,
         * optionally keyed by another column
         * @param string $value
         * @param string|null $key
         * @return static
         */
        public function pluck(string $value, ?string $key = null): static
        {
            return new static(Arr::Pluck($this->list, $value, $key));
        }

        /**
         * group the items by the value of a given key (or a callback), returning a
         * Collection of Collections keyed by group
         * @param string|callable $key
         * @return static
         */
        public function groupBy(string | callable $key): static
        {
            $ret = [];
            $items = array_values($this->list);

            for($i = 0; $i < count($items); $i++)
            {
                $item = $items[$i];
                $group = is_callable($key) ? $key($item) : (is_array($item) ? Arr::Get($item, $key) : ($item->$key ?? null));

                if(!isset($ret[$group]))
                {
                    $ret[$group] = new static();
                }
                $ret[$group]->push($item);
            }
            return new static($ret);
        }

        /**
         * sort the items by the value of a given key (or a callback)
         * @param string|callable $key
         * @param string $direction "asc" or "desc"
         * @return static
         */
        public function sortBy(string | callable $key, string $direction = "asc"): static
        {
            $items = array_values($this->list);

            usort($items, function($a, $b) use ($key)
            {
                $valA = is_callable($key) ? $key($a) : (is_array($a) ? Arr::Get($a, $key) : ($a->$key ?? null));
                $valB = is_callable($key) ? $key($b) : (is_array($b) ? Arr::Get($b, $key) : ($b->$key ?? null));

                return $valA <=> $valB;
            });

            if(strtolower($direction) === "desc")
            {
                $items = array_reverse($items);
            }
            return new static($items);
        }

        /**
         * sort the items by the value of a given key (or a callback), descending
         * @param string|callable $key
         * @return static
         */
        public function sortByDesc(string | callable $key): static
        {
            return $this->sortBy($key, "desc");
        }

        /**
         * remove duplicate values. If $key is given, items are treated as arrays/objects and
         * de-duplicated by that key instead of by whole-value equality.
         * @param string|null $key
         * @return static
         */
        public function unique(?string $key = null): static
        {
            return new static(ArrayUtil::Unique($this->list, $key));
        }

        /**
         * re-index the collection with sequential integer keys, discarding the current keys
         * @return static
         */
        public function values(): static
        {
            return new static(array_values($this->list));
        }

        /**
         * get the collection's keys as a new collection
         * @return static
         */
        public function keys(): static
        {
            return new static(array_keys($this->list));
        }

        /**
         * merge another array (or collection) into this one
         * @param array|Collection $items
         * @return static
         */
        public function merge(array | Collection $items): static
        {
            $other = ($items instanceof Collection) ? $items->all() : $items;
            return new static(array_merge($this->list, $other));
        }

        /**
         * return only the given keys
         * @param array $keys
         * @return static
         */
        public function only(array $keys): static
        {
            return new static(Arr::Only($this->list, $keys));
        }

        /**
         * return everything except the given keys
         * @param array $keys
         * @return static
         */
        public function except(array $keys): static
        {
            return new static(Arr::Except($this->list, $keys));
        }

        /**
         * split the collection into chunks of the given size
         * @param int $size
         * @return static a collection of arrays
         */
        public function chunk(int $size): static
        {
            return new static(array_chunk($this->list, max($size, 1)));
        }

        /**
         * take the first (or, with a negative number, the last) $count items
         * @param int $count
         * @return static
         */
        public function take(int $count): static
        {
            $items = array_values($this->list);

            if($count < 0)
            {
                return new static(array_slice($items, $count));
            }
            return new static(array_slice($items, 0, $count));
        }

        /**
         * skip the first $count items and keep the rest
         * @param int $count
         * @return static
         */
        public function skip(int $count): static
        {
            return new static(array_slice(array_values($this->list), $count));
        }

        /**
         * reverse the order of the items
         * @return static
         */
        public function reverse(): static
        {
            return new static(array_reverse($this->list));
        }

        /**
         * flatten a collection of nested arrays into a single flat list of values
         * @return static
         */
        public function flatten(): static
        {
            $ret = [];
            array_walk_recursive($this->list, function($value) use (&$ret) { $ret[] = $value; });

            return new static($ret);
        }

        /**
         * slice the collection into a single page of results, with pagination metadata.
         * @param int $page 1-indexed page number
         * @param int $perPage
         * @return array ["data" => Collection, "page", "perPage", "total", "totalPages", "hasMore"]
         */
        public function paginate(int $page = 1, int $perPage = 15): array
        {
            $result = ArrayUtil::Paginate($this->list, $page, $perPage);
            $result["data"] = new static($result["data"]);

            return $result;
        }
        #endregion


        #region mutation (these modify and return the SAME collection instance, for building one up)

        /**
         * append an item to the end of the collection
         * @param mixed $item
         * @return static
         */
        public function push(mixed $item): static
        {
            $this->list[] = $item;
            return $this;
        }

        /**
         * set a value at a given key
         * @param string|int $key
         * @param mixed $value
         * @return static
         */
        public function put(string | int $key, mixed $value): static
        {
            $this->list[$key] = $value;
            return $this;
        }

        /**
         * remove an item by key
         * @param string|int $key
         * @return static
         */
        public function forget(string | int $key): static
        {
            unset($this->list[$key]);
            return $this;
        }
        #endregion


        #region aggregates

        /**
         * check whether the collection contains a given value (optionally, a value at a given key
         * across all items, or a callback that returns true for a match)
         * @param mixed $value
         * @param string|null $key
         * @return bool
         */
        public function contains(mixed $value, ?string $key = null): bool
        {
            if(is_callable($value) && ($key === null))
            {
                foreach($this->list as $item)
                {
                    if($value($item))
                    {
                        return true;
                    }
                }
                return false;
            }

            if($key !== null)
            {
                foreach($this->list as $item)
                {
                    $itemValue = is_array($item) ? Arr::Get($item, $key) : ($item->$key ?? null);
                    if($itemValue === $value)
                    {
                        return true;
                    }
                }
                return false;
            }
            return in_array($value, $this->list, true);
        }

        /**
         * sum the collection's values (or the values at a given key, for a collection of arrays/objects)
         * @param string|null $key
         * @return int|float
         */
        public function sum(?string $key = null): int | float
        {
            if($key === null)
            {
                return array_sum($this->list);
            }
            return array_sum(Arr::Pluck($this->list, $key));
        }

        /**
         * average the collection's values (or the values at a given key)
         * @param string|null $key
         * @return int|float
         */
        public function avg(?string $key = null): int | float
        {
            $count = count($this->list);
            return ($count === 0) ? 0 : ($this->sum($key) / $count);
        }

        /**
         * get the smallest value in the collection (or at a given key)
         * @param string|null $key
         * @return mixed
         */
        public function min(?string $key = null): mixed
        {
            $values = ($key === null) ? $this->list : Arr::Pluck($this->list, $key);
            return (count($values) > 0) ? min($values) : null;
        }

        /**
         * get the largest value in the collection (or at a given key)
         * @param string|null $key
         * @return mixed
         */
        public function max(?string $key = null): mixed
        {
            $values = ($key === null) ? $this->list : Arr::Pluck($this->list, $key);
            return (count($values) > 0) ? max($values) : null;
        }

        /**
         * join the collection's values into a string (optionally, the values at a given key)
         * @param string $glue
         * @param string|null $key
         * @return string
         */
        public function implode(string $glue, ?string $key = null): string
        {
            $values = ($key === null) ? $this->list : Arr::Pluck($this->list, $key);
            return implode($glue, $values);
        }

        /**
         * find the key of the first item matching a value (or a callback), or null if nothing matches
         * @param mixed $needle a callback ($value, $key) => bool, or a plain value to compare with ===
         * @return mixed
         */
        public function search(mixed $needle): mixed
        {
            return ArrayUtil::Search($this->list, $needle);
        }
        #endregion


        #region flow control

        /**
         * run a callback with the collection (for side effects, e.g. logging/debugging mid-chain),
         * then continue the chain unchanged
         * @param callable $callback
         * @return static
         */
        public function tap(callable $callback): static
        {
            $callback($this);
            return $this;
        }

        /**
         * conditionally apply a callback to the collection
         * @param bool|callable $condition a boolean, or a callback returning one
         * @param callable $callback receives (static $collection) and should return a static
         * @param callable|null $default called instead if the condition is falsy
         * @return static
         */
        public function when(bool | callable $condition, callable $callback, ?callable $default = null): static
        {
            $passed = is_callable($condition) ? $condition($this) : $condition;

            if($passed)
            {
                return $callback($this) ?? $this;
            }
            else if($default !== null)
            {
                return $default($this) ?? $this;
            }
            return $this;
        }

        /**
         * pass the whole collection through a callback and return its result - useful for
         * ending a chain with a transformation that doesn't fit any other method here
         * @param callable $callback
         * @return mixed
         */
        public function pipe(callable $callback): mixed
        {
            return $callback($this);
        }
        #endregion


        #region interface implementations (ArrayAccess, Countable, IteratorAggregate, JsonSerializable)

        public function offsetExists(mixed $offset): bool
        {
            return isset($this->list[$offset]);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->list[$offset] ?? null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            if($offset === null)
            {
                $this->list[] = $value;
            }
            else
            {
                $this->list[$offset] = $value;
            }
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->list[$offset]);
        }

        public function getIterator(): Traversable
        {
            return new ArrayIterator($this->list);
        }

        public function jsonSerialize(): array
        {
            return $this->list;
        }
        #endregion
    }
