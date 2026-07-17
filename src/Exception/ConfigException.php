<?php

    namespace Wixnit\Exception;

    use Exception;
    use Throwable;

    class ConfigException extends Exception
    {
        public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }

        public static function FileNotFound(string $path): self
        {
            return new self("Unable to load config. The config file \"$path\" does not exist");
        }

        public static function DirectoryNotFound(string $directory): self
        {
            return new self("Unable to load config. The config directory \"$directory\" does not exist");
        }

        public static function InvalidJson(string $path, string $error): self
        {
            return new self("Unable to parse config file \"$path\" as JSON: $error");
        }

        public static function MissingRequiredKey(string $key): self
        {
            return new self("Missing required config key \"$key\"");
        }

        public static function CacheWriteFailed(string $path): self
        {
            return new self("Unable to write config cache to \"$path\" - check the directory is writable");
        }
    }
