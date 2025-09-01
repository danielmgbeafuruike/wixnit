<?php

    namespace Wixnit\App;

    class Container
    {
        private static array $instances = [];

        public static function set(string $key, mixed $service): void
        {
            self::$instances[$key] = $service;
        }

        public static function get(string $key): mixed
        {
            if (!isset(self::$instances[$key])) {
                throw new \RuntimeException("Service '$key' not found in container");
            }
            return self::$instances[$key];
        }

        public static function has(string $key): bool
        {
            return isset(self::$instances[$key]);
        }
    }