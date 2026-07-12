<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;

    /**
     * A password hash that can't be assigned a plaintext value by mistake, can't be
     * accidentally printed, and can't leak through json_encode() - the type itself
     * enforces this, rather than relying on every developer remembering to hash before
     * assigning and mark the property hidden.
     *
     * Usage:
     *   class User extends Model { public HashedPassword $password; }
     *
     *   $user->password->set('correct horse battery staple');    // hashes via password_hash()
     *   $user->password->verify('correct horse battery staple'); // bool
     *
     * There is no way to assign a raw string to this property - $user->password =
     * $_POST['password'] is a PHP TypeError, not a silently-stored plaintext password.
     * The only way in is set(), which always hashes.
     *
     * Implements ISerializable, the same interface Date/Time/Duration/Color already
     * use - no new framework plumbing needed for this type to work as a model property.
     * Stored as DBFieldType::TEXT rather than VARCHAR, since getDBImage() gives every
     * ISerializable field a fixed length of 100 which is fine for bcrypt (60 chars) but
     * tight for some argon2id configurations - TEXT sidesteps the length limit entirely.
     */
    class HashedPassword implements ISerializable, \JsonSerializable
    {
        private string $hash = "";

        function __construct()
        {
        }

        /**
         * @param string $plaintext
         * @return static
         */
        public function set(string $plaintext): static
        {
            $this->hash = password_hash($plaintext, PASSWORD_DEFAULT);
            return $this;
        }

        /**
         * @param string $plaintext
         * @return bool
         */
        public function verify(string $plaintext): bool
        {
            if($this->hash === "")
            {
                return false;
            }
            return password_verify($plaintext, $this->hash);
        }

        /**
         * @return bool whether the stored hash should be regenerated with today's default
         *              algorithm/cost - check after a successful verify() and re-set() if true
         */
        public function needsRehash(): bool
        {
            if($this->hash === "")
            {
                return false;
            }
            return password_needs_rehash($this->hash, PASSWORD_DEFAULT);
        }

        /**
         * Deliberately redacted - never returns the real hash, so an accidental
         * `echo $user->password` or string interpolation can't leak it.
         * @return string
         */
        public function __toString(): string
        {
            return "[redacted]";
        }

        //#region ISerializable

        public function _dbType(): DBFieldType
        {
            return DBFieldType::TEXT;
        }

        public function _serialize()
        {
            return $this->hash;
        }

        public function _deserialize($data): void
        {
            $this->hash = (string)$data;
        }

        //#endregion

        /**
         * The one deliberate exception among the value-object types - never serializes
         * to anything, redacted on purpose.
         * @return null
         */
        public function jsonSerialize(): mixed
        {
            return null;
        }
    }
