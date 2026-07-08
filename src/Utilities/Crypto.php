<?php

    namespace Wixnit\Utilities;

    use Wixnit\Exception\CryptoException;

    /**
     * Static helpers for reversible encryption (AES-256-GCM via OpenSSL) and HMAC-based
     * signing. For one-way hashing (passwords, checksums), see the `Hash` class instead.
     */
    class Crypto
    {
        private const CIPHER = "aes-256-gcm";

        /**
         * encrypt a string with a key, returning a single base64 string that bundles the
         * IV, the authentication tag, and the ciphertext together - everything Decrypt() needs.
         * @param string $data
         * @param string $key any string; internally it's hashed down to the cipher's required key length
         * @return string
         * @throws CryptoException if OpenSSL isn't available or encryption fails
         */
        public static function Encrypt(string $data, string $key): string
        {
            if(!extension_loaded("openssl"))
            {
                throw CryptoException::OpenSSLNotAvailable();
            }

            $binaryKey = hash("sha256", $key, true);
            $ivLength = openssl_cipher_iv_length(Crypto::CIPHER);
            $iv = openssl_random_pseudo_bytes($ivLength);
            $tag = "";

            $ciphertext = openssl_encrypt($data, Crypto::CIPHER, $binaryKey, OPENSSL_RAW_DATA, $iv, $tag);

            if($ciphertext === false)
            {
                throw CryptoException::EncryptionFailed();
            }

            return base64_encode($iv.$tag.$ciphertext);
        }

        /**
         * decrypt a string previously produced by Encrypt(), using the same key
         * @param string $encrypted
         * @param string $key
         * @return string
         * @throws CryptoException if OpenSSL isn't available, the payload is malformed, or decryption fails
         */
        public static function Decrypt(string $encrypted, string $key): string
        {
            if(!extension_loaded("openssl"))
            {
                throw CryptoException::OpenSSLNotAvailable();
            }

            $binaryKey = hash("sha256", $key, true);
            $raw = base64_decode($encrypted, true);

            if($raw === false)
            {
                throw CryptoException::DecryptionFailed("The input is not valid base64.");
            }

            $ivLength = openssl_cipher_iv_length(Crypto::CIPHER);
            $tagLength = 16;

            if(strlen($raw) < ($ivLength + $tagLength))
            {
                throw CryptoException::DecryptionFailed("The input is too short to contain a valid payload.");
            }

            $iv = substr($raw, 0, $ivLength);
            $tag = substr($raw, $ivLength, $tagLength);
            $ciphertext = substr($raw, $ivLength + $tagLength);

            $plaintext = openssl_decrypt($ciphertext, Crypto::CIPHER, $binaryKey, OPENSSL_RAW_DATA, $iv, $tag);

            if($plaintext === false)
            {
                throw CryptoException::DecryptionFailed();
            }
            return $plaintext;
        }

        /**
         * produce an HMAC-SHA256 signature for a piece of data, so its integrity can be
         * verified later without needing to keep the data itself secret
         * @param string $data
         * @param string $key
         * @return string
         */
        public static function Sign(string $data, string $key): string
        {
            return hash_hmac("sha256", $data, $key);
        }

        /**
         * verify that a signature produced by Sign() matches the given data and key.
         * Uses a timing-safe comparison to avoid leaking information through response timing.
         * @param string $data
         * @param string $signature
         * @param string $key
         * @return bool
         */
        public static function Verify(string $data, string $signature, string $key): bool
        {
            $expected = Crypto::Sign($data, $key);
            return hash_equals($expected, $signature);
        }

        /**
         * generate a cryptographically secure random key, suitable for use with Encrypt()/Decrypt() or Sign()/Verify()
         * @param int $length length in bytes before encoding (the returned hex string will be twice this length)
         * @return string a hex-encoded key
         */
        public static function GenerateKey(int $length = 32): string
        {
            return bin2hex(random_bytes($length));
        }
    }
