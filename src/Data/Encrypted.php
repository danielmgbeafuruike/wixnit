<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;
    use Wixnit\Exception\PropertyException;

    /**
     * A value that's genuinely unreadable without the key - the real security control
     * Masked explicitly isn't. Stores authenticated ciphertext (sodium_crypto_secretbox),
     * unreadable from a database dump or backup alone, recoverable by the application
     * only because it holds the key.
     *
     * Usage, with a globally configured key (EncryptionConfig::Init() run once at bootstrap):
     *   $user->apiKey->set('sk_live_abc123...');
     *   $user->save();
     *   $user->apiKey->decrypt();   // "sk_live_abc123..."
     *
     * Or, without ever configuring one globally - pass a key directly per call:
     *   $user->apiKey->set('sk_live_abc123...', $key);
     *   $user->apiKey->decrypt($key);
     *
     * A value set() with an explicit key is stored without a key-id - there's nothing
     * for EncryptionConfig to look up later, so decrypt() must be given the same key
     * again. A value set() using the global config is tagged with that key's id, so a
     * plain decrypt() (no argument) can resolve the right key automatically, including
     * after a rotation, as long as the old key is still passed to EncryptionConfig::Init().
     *
     * echo/json_encode() never reveal the real value, the same posture as HashedPassword.
     *
     * IMPORTANT: cannot be filtered by Filter equality. sodium_crypto_secretbox uses a
     * fresh random nonce every time, so encrypting the same plaintext twice produces
     * different ciphertext both times - a deliberate security property, but it means
     * there is no way to find a matching row by an Encrypted column's plaintext value.
     * See docs/ENCRYPTED_DESIGN.md §4.
     *
     * Implements ISerializable, the same interface Date/Time/Duration/Color already
     * use - but unlike those, encryption happens eagerly inside set(), not lazily in
     * _serialize() - by the time the framework calls _serialize(), set() has already
     * decided which key to use and there's nothing left to compute.
     */
    class Encrypted implements ISerializable, \JsonSerializable, \Stringable
    {
        private const MANAGED_MARKER = "K";
        private const CUSTOM_MARKER = "C";

        private string $stored = "";

        function __construct()
        {
        }

        /**
         * @return bool whether a value has been set/loaded at all
         */
        public function isSet(): bool
        {
            return $this->stored !== "";
        }

        /**
         * Encrypts $plaintext immediately, using $key if given, or EncryptionConfig's
         * current key otherwise.
         * @param string $plaintext
         * @param string|null $key if given, used directly and EncryptionConfig is never
         *                          consulted - the resulting value is stored without a
         *                          key-id, and this same $key must be passed to decrypt() later
         * @return static
         */
        public function set(string $plaintext, ?string $key = null): static
        {
            $this->assertSodiumAvailable();

            $usedKey = $key ?? EncryptionConfig::currentKey();

            if($usedKey === null)
            {
                throw PropertyException::NoEncryptionKeyAvailable("set");
            }

            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $usedKey);

            if($key !== null)
            {
                $this->stored = base64_encode(self::CUSTOM_MARKER . $nonce . $ciphertext);
            }
            else
            {
                $keyId = EncryptionConfig::currentKeyId();
                $this->stored = base64_encode(self::MANAGED_MARKER . $keyId . $nonce . $ciphertext);
            }
            return $this;
        }

        /**
         * @param string|null $key if given, used directly regardless of whether the
         *                          stored value carries a key-id or not. If omitted,
         *                          resolves the key from EncryptionConfig using the
         *                          stored key-id (managed values), or throws (custom-key values)
         * @return string the real, decrypted value
         */
        public function decrypt(?string $key = null): string
        {
            $this->assertSodiumAvailable();

            if($this->stored === "")
            {
                return "";
            }

            $raw = base64_decode($this->stored);
            $marker = $raw[0] ?? "";

            if($marker === self::MANAGED_MARKER)
            {
                $keyId = substr($raw, 1, 8);
                $body = substr($raw, 9);
            }
            else if($marker === self::CUSTOM_MARKER)
            {
                $keyId = null;
                $body = substr($raw, 1);
            }
            else
            {
                throw PropertyException::CorruptedEncryptedValue();
            }

            $usedKey = $key;

            if($usedKey === null)
            {
                if($keyId !== null)
                {
                    $usedKey = EncryptionConfig::keyFor($keyId);

                    if($usedKey === null)
                    {
                        throw PropertyException::EncryptionKeyNotFound($keyId);
                    }
                }
                else
                {
                    throw PropertyException::NoEncryptionKeyAvailable("decrypt");
                }
            }

            $nonceLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $nonce = substr($body, 0, $nonceLength);
            $ciphertext = substr($body, $nonceLength);

            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $usedKey);

            if($plaintext === false)
            {
                throw PropertyException::DecryptionFailed();
            }
            return $plaintext;
        }

        /**
         * @return string "[encrypted]" - never the real value
         */
        public function __toString(): string
        {
            return "[encrypted]";
        }

        private function assertSodiumAvailable(): void
        {
            if(!extension_loaded("sodium"))
            {
                throw PropertyException::SodiumExtensionMissing();
            }
        }

        //#region ISerializable

        public function _dbType(): DBFieldType
        {
            return DBFieldType::TEXT;
        }

        public function _serialize()
        {
            //set() already did the encryption work - this just returns the result.
            return $this->stored;
        }

        public function _deserialize($data): void
        {
            $this->stored = (string)$data;
        }

        //#endregion

        /**
         * @return null - redacted, the same posture as HashedPassword
         */
        public function jsonSerialize(): mixed
        {
            return null;
        }
    }
