<?php

    namespace Wixnit\Exception;

    /**
     * General purpose exception thrown by the small conversion/formatting utilities
     * (Convert, Random, etc) when they're given data they can't work with.
     */
    class UtilityException extends WixnitException
    {
        public static function InvalidJson(string $error): self
        {
            return new self("Invalid JSON: $error.", ["error" => $error]);
        }

        public static function InvalidXml(string $error): self
        {
            return new self("Invalid XML: $error.", ["error" => $error]);
        }

        public static function InvalidBase64(): self
        {
            return new self("The given string is not valid base64-encoded data.");
        }

        public static function InvalidArgument(string $expected, string $got): self
        {
            return new self("Invalid argument: expected $expected, got $got.", ["expected" => $expected, "got" => $got]);
        }
    }
