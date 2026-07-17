<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;

    /**
     * A value that displays obscured (echo, JSON) but stores and reads back whole - not
     * a security control, a display convenience. The real value is always intact in the
     * database and always retrievable via ->value() by anything holding the object; only
     * what's *shown* by default changes. For genuine, recoverable-only-with-a-key
     * protection, see Encrypted instead.
     *
     * Usage:
     *   class User extends Model
     *   {
     *       #[Mask(EmailMasker::class)]
     *       public Masked $email;
     *   }
     *
     *   $user->email->value();   // "ada@example.com" - the real value
     *   echo $user->email;        // "a**@example.com" - masked
     *   json_encode($user);       // masked here too
     *
     * #[Mask(...)] is optional - without it, Masked uses GenericMasker.
     *
     * IMPORTANT: change the value with set(), not by reassigning the property. The
     * masker is bound to this specific instance (the same way FlagSet's #[Flags] names
     * are), right after construction - `$user->email = new Masked()` starts a fresh
     * instance with no masker bound until the object is next loaded from the database.
     *
     *   $user->email->set('new@example.com');   // correct
     *   $user->email = new Masked();              // loses the masker binding
     *
     * Implements ISerializable, the same interface Date/Time/Duration/Color already
     * use - _serialize()/_deserialize() always deal in the real value; masking is
     * applied only at the __toString()/jsonSerialize() boundary.
     */
    class Masked implements ISerializable, \JsonSerializable, \Stringable
    {
        private string $value = "";
        private string $maskerClass = GenericMasker::class;

        function __construct()
        {
        }

        /**
         * Called once by the framework, right after construction, if the property
         * carries #[Mask(...)]. Not intended to be called from application code.
         * @param string $maskerClass
         * @return void
         */
        public function bindMasker(string $maskerClass): void
        {
            $this->maskerClass = $maskerClass;
        }

        /**
         * @return string the real, unmasked value
         */
        public function value(): string
        {
            return $this->value;
        }

        /**
         * @return string the masked value, explicitly
         */
        public function masked(): string
        {
            return ($this->maskerClass)::mask($this->value);
        }

        /**
         * @param string $value
         * @return static
         */
        public function set(string $value): static
        {
            $this->value = $value;
            return $this;
        }

        /**
         * @return string the masked value - same as masked(), so echo/string
         *                  interpolation is safe by default
         */
        public function __toString(): string
        {
            return $this->masked();
        }

        //#region ISerializable

        public function _dbType(): DBFieldType
        {
            return DBFieldType::TEXT;
        }

        public function _serialize()
        {
            return $this->value;
        }

        public function _deserialize($data): void
        {
            $this->value = (string)$data;
        }

        //#endregion

        /**
         * @return string the masked value - same as masked(), so API responses are
         *                  safe by default
         */
        public function jsonSerialize(): mixed
        {
            return $this->masked();
        }
    }
