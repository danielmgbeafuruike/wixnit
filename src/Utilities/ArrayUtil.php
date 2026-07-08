<?php

    namespace Wixnit\Utilities;

    /**
     * A convenience facade over common array operations. Where the operation already
     * exists on `Arr`, this class simply forwards to it (kept as a separate class so it
     * can offer this library's shorter, camelCase-first-letter naming convention).
     */
    class ArrayUtil
    {
        /**
         * flatten a multi-dimensional array into a single-level array with dot-notated keys
         * e.g. ["user" => ["name" => "Ada"]] -> ["user.name" => "Ada"]
         * @param array $array
         * @return array
         */
        public static function Flatten(array $array): array
        {
            return Arr::Flatten($array);
        }

        /**
         * group an array of arrays/objects by the value of a given key
         * e.g. ArrayUtil::GroupBy($users, "role") -> ["admin" => [...], "member" => [...]]
         * @param array $array
         * @param string $key
         * @return array
         */
        public static function GroupBy(array $array, string $key): array
        {
            return Arr::GroupBy($array, $key);
        }

        /**
         * sort an array of arrays/objects by the value of a given key
         * @param array $array
         * @param string $key
         * @param string $direction "asc" or "desc"
         * @return array
         */
        public static function SortBy(array $array, string $key, string $direction = "asc"): array
        {
            $items = array_values($array);

            usort($items, function($a, $b) use ($key)
            {
                $valA = is_array($a) ? Arr::Get($a, $key) : ($a->$key ?? null);
                $valB = is_array($b) ? Arr::Get($b, $key) : ($b->$key ?? null);

                return $valA <=> $valB;
            });

            if(strtolower($direction) === "desc")
            {
                $items = array_reverse($items);
            }
            return $items;
        }

        /**
         * pluck a single column of values from an array of arrays/objects,
         * optionally keyed by another column, e.g. ArrayUtil::Pluck($users, "name", "id")
         * @param array $array
         * @param string $value
         * @param string|null $key
         * @return array
         */
        public static function Pluck(array $array, string $value, ?string $key = null): array
        {
            return Arr::Pluck($array, $value, $key);
        }

        /**
         * get the first element of an array (optionally the first that passes a callback), or a default value
         * @param array $array
         * @param callable|null $callback
         * @param mixed $default
         * @return mixed
         */
        public static function First(array $array, ?callable $callback = null, mixed $default = null): mixed
        {
            return Arr::First($array, $callback, $default);
        }

        /**
         * get the last element of an array, or a default value
         * @param array $array
         * @param mixed $default
         * @return mixed
         */
        public static function Last(array $array, mixed $default = null): mixed
        {
            return Arr::Last($array, $default);
        }

        /**
         * remove duplicate values from an array. If $key is given, the array is treated as a
         * list of arrays/objects and de-duplicated by that key instead of by whole-value equality.
         * @param array $array
         * @param string|null $key
         * @return array
         */
        public static function Unique(array $array, ?string $key = null): array
        {
            if($key === null)
            {
                return array_values(array_unique($array, SORT_REGULAR));
            }

            $seen = [];
            $ret = [];

            $items = array_values($array);
            for($i = 0; $i < count($items); $i++)
            {
                $item = $items[$i];
                $val = is_array($item) ? Arr::Get($item, $key) : ($item->$key ?? null);

                if(!in_array($val, $seen, true))
                {
                    $seen[] = $val;
                    $ret[] = $item;
                }
            }
            return $ret;
        }

        /**
         * remove all null values from an array. Recurses into nested arrays.
         * @param array $array
         * @return array
         */
        public static function RemoveNulls(array $array): array
        {
            $ret = [];

            $keys = array_keys($array);
            for($i = 0; $i < count($keys); $i++)
            {
                $key = $keys[$i];
                $value = $array[$key];

                if($value === null)
                {
                    continue;
                }
                $ret[$key] = is_array($value) ? ArrayUtil::RemoveNulls($value) : $value;
            }
            return $ret;
        }

        /**
         * split an array into chunks of the given size
         * @param array $array
         * @param int $size
         * @param bool $preserveKeys
         * @return array
         */
        public static function Chunk(array $array, int $size, bool $preserveKeys = false): array
        {
            return array_chunk($array, max($size, 1), $preserveKeys);
        }

        /**
         * slice an array into a single page of results, along with pagination metadata.
         * Returns ["data" => [...], "page" => n, "perPage" => n, "total" => n, "totalPages" => n,
         * "hasMore" => bool].
         * @param array $array
         * @param int $page 1-indexed page number
         * @param int $perPage
         * @return array
         */
        public static function Paginate(array $array, int $page = 1, int $perPage = 15): array
        {
            $items = array_values($array);
            $total = count($items);
            $page = max($page, 1);
            $perPage = max($perPage, 1);
            $totalPages = (int) max(ceil($total / $perPage), 1);

            $offset = ($page - 1) * $perPage;

            return [
                "data" => array_slice($items, $offset, $perPage),
                "page" => $page,
                "perPage" => $perPage,
                "total" => $total,
                "totalPages" => $totalPages,
                "hasMore" => $page < $totalPages,
            ];
        }

        /**
         * find the first key whose value matches the given callback (or equals the given value),
         * searching recursively into nested arrays if $recursive is true.
         * @param array $array
         * @param callable|mixed $needle a callback ($value, $key) => bool, or a plain value to compare with ===
         * @param bool $recursive
         * @return mixed the matching key, or null if nothing matched
         */
        public static function Search(array $array, mixed $needle, bool $recursive = false): mixed
        {
            $keys = array_keys($array);

            for($i = 0; $i < count($keys); $i++)
            {
                $key = $keys[$i];
                $value = $array[$key];

                $matches = is_callable($needle) ? $needle($value, $key) : ($value === $needle);

                if($matches)
                {
                    return $key;
                }

                if($recursive && is_array($value))
                {
                    $found = ArrayUtil::Search($value, $needle, true);
                    if($found !== null)
                    {
                        return $found;
                    }
                }
            }
            return null;
        }

        /**
         * rename a key in an array, preserving its value and the array's original key order
         * @param array $array
         * @param string $from
         * @param string $to
         * @return array
         */
        public static function RenameKey(array $array, string $from, string $to): array
        {
            if(!array_key_exists($from, $array))
            {
                return $array;
            }

            $ret = [];
            $keys = array_keys($array);

            for($i = 0; $i < count($keys); $i++)
            {
                $key = $keys[$i];
                $newKey = ($key === $from) ? $to : $key;
                $ret[$newKey] = $array[$key];
            }
            return $ret;
        }
    }
