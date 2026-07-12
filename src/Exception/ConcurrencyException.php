<?php

    namespace Wixnit\Exception;

    class ConcurrencyException extends WixnitException
    {
        public static function StaleWrite(string $class, string $id): self
        {
            return new self(
                "Failed to save '$class' (id: $id) - it was modified by someone else since it was ".
                "loaded. Reload the record, re-apply your changes, and try again.",
                ["class" => $class, "id" => $id]
            );
        }
    }
