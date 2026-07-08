<?php

    namespace Wixnit\Utilities;

    /**
     * Static helpers for one-way hashing and password storage. For reversible encryption
     * (data you need to decrypt later), see the `Crypto` class instead.
     */
    class Hash
    {
        /**
         * calculate the MD5 digest of a string. MD5 is fast but cryptographically broken -
         * fine for checksums, not for anything security-sensitive (use Sha256() or Bcrypt() instead).
         * @param string $data
         * @return string
         */
        public static function Md5(string $data): string
        {
            return md5($data);
        }

        /**
         * calculate the SHA-1 digest of a string. Like MD5, SHA-1 is considered weak for
         * security purposes these days - prefer Sha256() for anything sensitive.
         * @param string $data
         * @return string
         */
        public static function Sha1(string $data): string
        {
            return sha1($data);
        }

        /**
         * calculate the SHA-256 digest of a string
         * @param string $data
         * @return string
         */
        public static function Sha256(string $data): string
        {
            return hash("sha256", $data);
        }

        /**
         * hash a password for storage using PHP's bcrypt algorithm (via password_hash()).
         * This is what you should use for storing user passwords - never Md5()/Sha1()/Sha256() directly.
         * @param string $password
         * @param int $cost the bcrypt work factor (higher = slower to compute, more resistant to brute force)
         * @return string
         */
        public static function Bcrypt(string $password, int $cost = 12): string
        {
            return password_hash($password, PASSWORD_BCRYPT, ["cost" => $cost]);
        }

        /**
         * verify a plain-text password against a hash previously produced by Bcrypt()
         * @param string $password
         * @param string $hash
         * @return bool
         */
        public static function Verify(string $password, string $hash): bool
        {
            return password_verify($password, $hash);
        }

        /**
         * calculate a keyed HMAC digest of a string, e.g. for signing webhook payloads
         * @param string $data
         * @param string $key
         * @param string $algorithm any algorithm accepted by PHP's hash_hmac(), defaults to sha256
         * @return string
         */
        public static function Hmac(string $data, string $key, string $algorithm = "sha256"): string
        {
            return hash_hmac($algorithm, $data, $key);
        }
    }
