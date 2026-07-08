<?php

    namespace Wixnit\Utilities;

    class Arr
    {
        /**
         * get a value from a nested array using dot notation, e.g. Arr::Get($data, "user.address.city")
         * @param array $array
         * @param string $key
         * @param mixed $default
         * @return mixed
         */
        public static function Get(array $array, string $key, mixed $default = null): mixed
        {
            if(array_key_exists($key, $array))
            {
                return $array[$key];
            }

            $segments = explode(".", $key);
            $current = $array;

            for($i = 0; $i < count($segments); $i++)
            {
                if(is_array($current) && array_key_exists($segments[$i], $current))
                {
                    $current = $current[$segments[$i]];
                }
                else
                {
                    return $default;
                }
            }
            return $current;
        }

        /**
         * set a value in a nested array using dot notation, creating intermediate arrays as needed
         * @param array $array
         * @param string $key
         * @param mixed $value
         * @return array
         */
        public static function Set(array $array, string $key, mixed $value): array
        {
            $segments = explode(".", $key);
            $current = &$array;

            for($i = 0; $i < count($segments); $i++)
            {
                $segment = $segments[$i];

                if($i === (count($segments) - 1))
                {
                    $current[$segment] = $value;
                }
                else
                {
                    if(!isset($current[$segment]) || !is_array($current[$segment]))
                    {
                        $current[$segment] = [];
                    }
                    $current = &$current[$segment];
                }
            }
            return $array;
        }

        /**
         * check if a nested key exists in the array using dot notation
         * @param array $array
         * @param string $key
         * @return bool
         */
        public static function Has(array $array, string $key): bool
        {
            if(array_key_exists($key, $array))
            {
                return true;
            }

            $segments = explode(".", $key);
            $current = $array;

            for($i = 0; $i < count($segments); $i++)
            {
                if(is_array($current) && array_key_exists($segments[$i], $current))
                {
                    $current = $current[$segments[$i]];
                }
                else
                {
                    return false;
                }
            }
            return true;
        }

        /**
         * remove a nested key from the array using dot notation
         * @param array $array
         * @param string $key
         * @return array
         */
        public static function Forget(array $array, string $key): array
        {
            $segments = explode(".", $key);
            $current = &$array;

            for($i = 0; $i < count($segments); $i++)
            {
                $segment = $segments[$i];

                if($i === (count($segments) - 1))
                {
                    unset($current[$segment]);
                }
                else
                {
                    if(!isset($current[$segment]) || !is_array($current[$segment]))
                    {
                        return $array;
                    }
                    $current = &$current[$segment];
                }
            }
            return $array;
        }

        /**
         * flatten a multi-dimensional array into a single-level array with dot-notated keys
         * e.g. ["user" => ["name" => "Ada"]] -> ["user.name" => "Ada"]
         * @param array $array
         * @param string $prepend
         * @return array
         */
        public static function Flatten(array $array, string $prepend = ""): array
        {
            $ret = [];

            $keys = array_keys($array);
            for($i = 0; $i < count($keys); $i++)
            {
                $key = $keys[$i];
                $value = $array[$key];

                if(is_array($value) && count($value) > 0)
                {
                    $ret = array_merge($ret, Arr::Flatten($value, $prepend.$key."."));
                }
                else
                {
                    $ret[$prepend.$key] = $value;
                }
            }
            return $ret;
        }

        /**
         * pluck a single column of values from an array of arrays/objects,
         * optionally keyed by another column, e.g. Arr::Pluck($users, "name", "id")
         * @param array $array
         * @param string $value
         * @param string|null $key
         * @return array
         */
        public static function Pluck(array $array, string $value, ?string $key = null): array
        {
            $ret = [];

            $items = array_values($array);
            for($i = 0; $i < count($items); $i++)
            {
                $item = $items[$i];
                $v = is_array($item) ? Arr::Get($item, $value) : ($item->$value ?? null);

                if($key !== null)
                {
                    $k = is_array($item) ? Arr::Get($item, $key) : ($item->$key ?? null);
                    $ret[$k] = $v;
                }
                else
                {
                    $ret[] = $v;
                }
            }
            return $ret;
        }

        /**
         * return only the given keys from an array
         * @param array $array
         * @param array $keys
         * @return array
         */
        public static function Only(array $array, array $keys): array
        {
            return array_intersect_key($array, array_flip($keys));
        }

        /**
         * return the array without the given keys
         * @param array $array
         * @param array $keys
         * @return array
         */
        public static function Except(array $array, array $keys): array
        {
            return array_diff_key($array, array_flip($keys));
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
            if($callback === null)
            {
                return (count($array) > 0) ? reset($array) : $default;
            }

            $values = array_values($array);
            for($i = 0; $i < count($values); $i++)
            {
                if($callback($values[$i]))
                {
                    return $values[$i];
                }
            }
            return $default;
        }

        /**
         * get the last element of an array, or a default value
         * @param array $array
         * @param mixed $default
         * @return mixed
         */
        public static function Last(array $array, mixed $default = null): mixed
        {
            return (count($array) > 0) ? end($array) : $default;
        }

        /**
         * check whether an array is associative (has at least one non-integer or non-sequential key)
         * @param array $array
         * @return bool
         */
        public static function IsAssoc(array $array): bool
        {
            if($array === [])
            {
                return false;
            }
            return array_keys($array) !== range(0, count($array) - 1);
        }

        /**
         * wrap a value in an array if it isn't one already; null becomes an empty array
         * @param mixed $value
         * @return array
         */
        public static function Wrap(mixed $value): array
        {
            if($value === null)
            {
                return [];
            }
            return is_array($value) ? $value : [$value];
        }

        /**
         * group an array of arrays/objects by the value of a given key
         * e.g. Arr::GroupBy($users, "role") -> ["admin" => [...], "member" => [...]]
         * @param array $array
         * @param string $key
         * @return array
         */
        public static function GroupBy(array $array, string $key): array
        {
            $ret = [];

            $items = array_values($array);
            for($i = 0; $i < count($items); $i++)
            {
                $item = $items[$i];
                $group = is_array($item) ? Arr::Get($item, $key) : ($item->$key ?? null);

                if(!isset($ret[$group]))
                {
                    $ret[$group] = [];
                }
                $ret[$group][] = $item;
            }
            return $ret;
        }
    }
