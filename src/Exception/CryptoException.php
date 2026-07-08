<?php

    namespace Wixnit\Exception;

    class CryptoException extends WixnitException
    {
        public static function OpenSSLNotAvailable(): self
        {
            return new self("The OpenSSL extension is not available, but is required for encryption. Enable the 'openssl' PHP extension.");
        }

        public static function InvalidKey(string $reason = ""): self
        {
            $message = "Invalid encryption key.".($reason != "" ? " ".$reason : "");
            return new self($message);
        }

        public static function UnsupportedCipher(string $cipher): self
        {
            return new self("Unsupported cipher method: '$cipher'.", ["cipher" => $cipher]);
        }

        public static function EncryptionFailed(string $reason = ""): self
        {
            $message = "Encryption failed.".($reason != "" ? " ".$reason : "");
            return new self($message);
        }

        public static function DecryptionFailed(string $reason = ""): self
        {
            $message = "Decryption failed - the data may be corrupt, truncated, or encrypted with a different key.".($reason != "" ? " ".$reason : "");
            return new self($message);
        }

        public static function InvalidSignature(): self
        {
            return new self("Signature verification failed - the data may have been tampered with, or was signed with a different key.");
        }
    }
