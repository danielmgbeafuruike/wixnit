<?php

    namespace Wixnit\Data;

    use Wixnit\Exception\PropertyException;

    /**
     * Holds the application-wide key(s) used by Encrypted properties, analogous to how
     * DBConfig holds the database credentials. Purely a convenience - Encrypted's own
     * set()/decrypt() both accept a key directly as well, for applications that don't
     * want a single process-wide key at all. See docs/ENCRYPTED_DESIGN.md.
     *
     * Usage:
     *   EncryptionConfig::Init($currentKey);                    // no rotation history
     *   EncryptionConfig::Init($currentKey, [$oldKey1, $oldKey2]); // can still decrypt values from before a rotation
     *
     * Key ids are derived deterministically from the key itself (a short hash prefix),
     * not assigned by hand - so rotating just means calling Init() again with the new
     * current key and the previous key(s) added to the second argument.
     */
    class EncryptionConfig
    {
        private static ?string $currentKey = null;
        private static array $keysById = [];

        /**
         * @param string $currentKey the key used for all new encryptions from now on
         * @param array $oldKeys previously-current keys, kept only so values encrypted
         *                        under them can still be decrypted
         * @return void
         */
        public static function Init(string $currentKey, array $oldKeys = []): void
        {
            if(!extension_loaded("sodium"))
            {
                throw PropertyException::SodiumExtensionMissing();
            }

            self::$currentKey = $currentKey;
            self::$keysById = [];

            foreach(array_merge([$currentKey], $oldKeys) as $key)
            {
                self::$keysById[self::keyId($key)] = $key;
            }
        }

        /**
         * @param string $key
         * @return string a short, deterministic id derived from the key itself
         */
        public static function keyId(string $key): string
        {
            return substr(hash("sha256", $key), 0, 8);
        }

        /**
         * @return bool whether Init() has been called
         */
        public static function hasKey(): bool
        {
            return self::$currentKey !== null;
        }

        /**
         * @return string|null the current key, or null if Init() was never called
         */
        public static function currentKey(): ?string
        {
            return self::$currentKey;
        }

        /**
         * @return string|null the current key's id, or null if Init() was never called
         */
        public static function currentKeyId(): ?string
        {
            return (self::$currentKey !== null) ? self::keyId(self::$currentKey) : null;
        }

        /**
         * @param string $keyId
         * @return string|null the key matching this id, if it's still configured
         */
        public static function keyFor(string $keyId): ?string
        {
            return self::$keysById[$keyId] ?? null;
        }

        /**
         * Clears the configured key(s) - intended for test suites, not normal
         * application use.
         * @return void
         */
        public static function reset(): void
        {
            self::$currentKey = null;
            self::$keysById = [];
        }
    }
