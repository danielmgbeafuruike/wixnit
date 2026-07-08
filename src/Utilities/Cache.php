<?php

    namespace Wixnit\Utilities;

    use Wixnit\Exception\CacheException;

    /**
     * A simple file-based cache. Each entry is stored as its own file (name derived from
     * a hash of the key) containing a serialized ["expires" => timestamp|null, "value" => mixed]
     * payload. Good for single-server setups or low-traffic caching needs; for anything
     * distributed across multiple servers, back this with a real cache service instead.
     */
    class Cache
    {
        private static ?string $directory = null;

        /**
         * set the directory cache files are stored in. If never called, defaults to a
         * "wixnit-cache" folder inside the system's temp directory.
         * @param string $path
         * @return void
         */
        public static function UseDirectory(string $path): void
        {
            Cache::$directory = rtrim($path, "/");
        }

        /**
         * get a value from the cache, or compute and store it if it isn't cached yet (or has expired)
         * @param string $key
         * @param int|null $ttlSeconds how long the computed value should be cached for; null means it never expires
         * @param callable $callback called (with no arguments) to compute the value on a cache miss
         * @return mixed
         */
        public static function Remember(string $key, ?int $ttlSeconds, callable $callback): mixed
        {
            if(Cache::Has($key))
            {
                return Cache::Get($key);
            }

            $value = $callback();
            Cache::Put($key, $value, $ttlSeconds);

            return $value;
        }

        /**
         * store a value in the cache
         * @param string $key
         * @param mixed $value
         * @param int|null $ttlSeconds how long to keep the value for; null means it never expires
         * @return bool
         * @throws CacheException if the key is invalid or the cache can't be written to
         */
        public static function Put(string $key, mixed $value, ?int $ttlSeconds = null): bool
        {
            Cache::validateKey($key);

            $payload = [
                "expires" => ($ttlSeconds !== null) ? (time() + $ttlSeconds) : null,
                "value" => $value,
            ];

            $path = Cache::pathFor($key);
            $directory = dirname($path);

            if(!is_dir($directory))
            {
                if(!mkdir($directory, 0777, true) && !is_dir($directory))
                {
                    throw CacheException::DirectoryNotWritable($directory);
                }
            }

            if(file_put_contents($path, serialize($payload), LOCK_EX) === false)
            {
                throw CacheException::WriteFailed($key);
            }
            return true;
        }

        /**
         * get a value from the cache, or a default if it's missing or expired
         * @param string $key
         * @param mixed $default
         * @return mixed
         */
        public static function Get(string $key, mixed $default = null): mixed
        {
            $path = Cache::pathFor($key);

            if(!is_file($path))
            {
                return $default;
            }

            $raw = file_get_contents($path);
            if($raw === false)
            {
                return $default;
            }

            $payload = @unserialize($raw);
            if(!is_array($payload) || !array_key_exists("value", $payload))
            {
                return $default;
            }

            if(($payload["expires"] !== null) && ($payload["expires"] < time()))
            {
                Cache::Forget($key);
                return $default;
            }
            return $payload["value"];
        }

        /**
         * check whether a (non-expired) value exists in the cache for a key
         * @param string $key
         * @return bool
         */
        public static function Has(string $key): bool
        {
            //using a unique sentinel default lets us tell "missing" apart from a genuinely cached null
            $sentinel = new \stdClass();
            return Cache::Get($key, $sentinel) !== $sentinel;
        }

        /**
         * remove a single value from the cache
         * @param string $key
         * @return bool
         */
        public static function Forget(string $key): bool
        {
            $path = Cache::pathFor($key);

            if(!is_file($path))
            {
                return false;
            }
            return unlink($path);
        }

        /**
         * remove every value from the cache
         * @return bool
         */
        public static function Clear(): bool
        {
            $directory = Cache::directory();

            if(!is_dir($directory))
            {
                return true;
            }

            $files = glob($directory."/*.cache");
            for($i = 0; $i < count($files); $i++)
            {
                unlink($files[$i]);
            }
            return true;
        }


        #region private helpers

        /**
         * validate that a cache key is usable
         * @param string $key
         * @return void
         * @throws CacheException
         */
        private static function validateKey(string $key): void
        {
            if(trim($key) === "")
            {
                throw CacheException::InvalidKey($key);
            }
        }

        /**
         * get the configured (or default) cache directory
         * @return string
         */
        private static function directory(): string
        {
            return Cache::$directory ?? (sys_get_temp_dir()."/wixnit-cache");
        }

        /**
         * get the on-disk path for a cache key
         * @param string $key
         * @return string
         */
        private static function pathFor(string $key): string
        {
            return Cache::directory()."/".hash("sha256", $key).".cache";
        }
        #endregion
    }
