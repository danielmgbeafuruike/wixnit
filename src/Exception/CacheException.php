<?php

    namespace Wixnit\Exception;

    class CacheException extends WixnitException
    {
        public static function WriteFailed(string $key): self
        {
            return new self("Failed to write cache entry for key: '$key'.", ["key" => $key]);
        }

        public static function ReadFailed(string $key): self
        {
            return new self("Failed to read cache entry for key: '$key'.", ["key" => $key]);
        }

        public static function InvalidKey(string $key): self
        {
            return new self("Invalid cache key: '$key'. Keys must be non-empty strings.", ["key" => $key]);
        }

        public static function DirectoryNotWritable(string $path): self
        {
            return new self("Cache directory is not writable: '$path'.", ["path" => $path]);
        }
    }
