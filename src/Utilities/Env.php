<?php

    namespace Wixnit\Utilities;

    /**
     * Static helpers for reading and setting environment variables, with typed
     * getters so you don't have to manually parse "true"/"false"/"1"/"0" strings yourself.
     */
    class Env
    {
        /**
         * get an environment variable's raw string value, or a default if it isn't set
         * @param string $key
         * @param mixed $default
         * @return mixed
         */
        public static function Get(string $key, mixed $default = null): mixed
        {
            $value = getenv($key);

            if($value === false)
            {
                $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
            }
            return ($value !== null) ? $value : $default;
        }

        /**
         * set an environment variable for the current process
         * @param string $key
         * @param string $value
         * @return void
         */
        public static function Set(string $key, string $value): void
        {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }

        /**
         * check whether an environment variable is set
         * @param string $key
         * @return bool
         */
        public static function Has(string $key): bool
        {
            return (getenv($key) !== false) || isset($_ENV[$key]) || isset($_SERVER[$key]);
        }

        /**
         * get an environment variable as a boolean. Recognizes "true"/"1"/"yes"/"on" as true
         * and "false"/"0"/"no"/"off"/"" as false (case-insensitive); anything else falls back to $default.
         * @param string $key
         * @param bool $default
         * @return bool
         */
        public static function Bool(string $key, bool $default = false): bool
        {
            $value = Env::Get($key);

            if($value === null)
            {
                return $default;
            }

            $normalized = strtolower(trim((string) $value));

            if(in_array($normalized, ["true", "1", "yes", "on"]))
            {
                return true;
            }
            if(in_array($normalized, ["false", "0", "no", "off", ""]))
            {
                return false;
            }
            return $default;
        }

        /**
         * get an environment variable as an integer
         * @param string $key
         * @param int $default
         * @return int
         */
        public static function Int(string $key, int $default = 0): int
        {
            $value = Env::Get($key);
            return ($value === null) ? $default : (int) $value;
        }
    }
