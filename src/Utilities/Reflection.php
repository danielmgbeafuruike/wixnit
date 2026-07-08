<?php

    namespace Wixnit\Utilities;

    use ReflectionClass;
    use ReflectionException;

    /**
     * Thin, friendlier wrapper over PHP's native Reflection API for the handful of
     * inspection tasks that come up most often: listing properties/methods, reading
     * PHP 8 attributes, and checking a class's interfaces/ancestry.
     */
    class Reflection
    {
        /**
         * get the names of a class's properties
         * @param string|object $class
         * @param bool $publicOnly when true (default), only public properties are included
         * @return array<string>
         */
        public static function Properties(string | object $class, bool $publicOnly = true): array
        {
            $reflection = new ReflectionClass($class);
            $filter = $publicOnly ? \ReflectionProperty::IS_PUBLIC : null;

            $properties = ($filter !== null) ? $reflection->getProperties($filter) : $reflection->getProperties();

            $ret = [];
            for($i = 0; $i < count($properties); $i++)
            {
                $ret[] = $properties[$i]->getName();
            }
            return $ret;
        }

        /**
         * get the names of a class's methods
         * @param string|object $class
         * @param bool $publicOnly when true (default), only public methods are included
         * @return array<string>
         */
        public static function Methods(string | object $class, bool $publicOnly = true): array
        {
            $reflection = new ReflectionClass($class);
            $filter = $publicOnly ? \ReflectionMethod::IS_PUBLIC : null;

            $methods = ($filter !== null) ? $reflection->getMethods($filter) : $reflection->getMethods();

            $ret = [];
            for($i = 0; $i < count($methods); $i++)
            {
                $ret[] = $methods[$i]->getName();
            }
            return $ret;
        }

        /**
         * get the PHP 8 attributes declared on a class, a specific property, or a specific method.
         * Returns each attribute's class name and its constructor arguments.
         * @param string|object $class
         * @param string|null $property if given, read attributes off this property instead of the class itself
         * @param string|null $method if given, read attributes off this method instead of the class itself
         * @return array<array{name: string, arguments: array}>
         * @throws ReflectionException if $property or $method doesn't exist on the class
         */
        public static function Attributes(string | object $class, ?string $property = null, ?string $method = null): array
        {
            $reflection = new ReflectionClass($class);

            if($property !== null)
            {
                $target = $reflection->getProperty($property);
            }
            else if($method !== null)
            {
                $target = $reflection->getMethod($method);
            }
            else
            {
                $target = $reflection;
            }

            $attributes = $target->getAttributes();
            $ret = [];

            for($i = 0; $i < count($attributes); $i++)
            {
                $ret[] = [
                    "name" => $attributes[$i]->getName(),
                    "arguments" => $attributes[$i]->getArguments(),
                ];
            }
            return $ret;
        }

        /**
         * check whether a class implements a given interface
         * @param string|object $class
         * @param string $interface fully qualified interface name
         * @return bool
         */
        public static function Implements(string | object $class, string $interface): bool
        {
            $className = is_object($class) ? get_class($class) : $class;
            return in_array(ltrim($interface, "\\"), class_implements($className) ?: []);
        }

        /**
         * check whether a class inherits from (extends, anywhere in its ancestry) a given parent class
         * @param string|object $class
         * @param string $parentClass fully qualified parent class name
         * @return bool
         */
        public static function Inherits(string | object $class, string $parentClass): bool
        {
            $className = is_object($class) ? get_class($class) : $class;
            return in_array(ltrim($parentClass, "\\"), class_parents($className) ?: []);
        }
    }
