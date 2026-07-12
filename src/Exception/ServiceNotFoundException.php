<?php

    namespace Wixnit\Exception;

    use Exception;
    use Throwable;

    class ServiceNotFoundException extends Exception
    {
        public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }

        public static function NotRegistered(string $key): self
        {
            return new self("Service '$key' not found in container");
        }

        public static function TypeMismatch(string $key, string $expectedType, string $actualType): self
        {
            return new self("Service '$key' was expected to be of type '$expectedType', but resolved to '$actualType'");
        }
    }
