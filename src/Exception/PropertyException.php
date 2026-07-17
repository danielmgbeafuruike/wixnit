<?php

    namespace Wixnit\Exception;

    use Exception;

    /**
     * Thrown for mistakes in how a smart value-object property (Money, Counter,
     * LazyText, FlagSet, JsonDocument, HashedPassword) is used or configured - as
     * distinct from a plain PHP TypeError, which already covers "this property isn't
     * typed as one of these classes correctly" for free, since the framework's existing
     * ISerializable-checking code throws that on its own.
     */
    class PropertyException extends Exception
    {
        public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }

        /**
         * @param int $count how many names were passed to #[Flags(...)]
         * @return self
         */
        public static function TooManyFlags(int $count): self
        {
            return new self(
                "#[Flags(...)] was given {$count} names, but a FlagSet's BIGINT column only has 63 usable bit positions.\n".
                "  Why: each flag name maps to one bit in a single integer column - there's no room for more than 63 without a wider column.\n".
                "  Fix: reduce the number of flags, or split them across more than one FlagSet property."
            );
        }

        /**
         * @param string $flag the unrecognized flag name
         * @param array $known the names actually bound via #[Flags(...)]
         * @return self
         */
        public static function UnknownFlag(string $flag, array $known): self
        {
            $suggestion = (count($known) > 0)
                ? "Known flags are: " . implode(", ", $known) . "."
                : "This FlagSet has no #[Flags(...)] attribute bound, so only raw integer bit positions are valid, e.g. ->has(1).";

            return new self(
                "Unknown flag '{$flag}'.\n".
                "  Why: '{$flag}' doesn't match any name in this property's #[Flags(...)] attribute.\n".
                "  Suggestion: {$suggestion}"
            );
        }

        /**
         * @param string $class the model the Counter belongs to
         * @param string $field the Counter property name
         * @return self
         */
        public static function CounterOnUnsavedObject(string $class, string $field): self
        {
            return new self(
                "{$class}::\${$field}->increment()/decrement() was called on an object with no id yet.\n".
                "  Why: Counter persists immediately via an atomic UPDATE against a specific row - there's no row to update until the object has been saved once.\n".
                "  Fix: call save() on the object first, then increment()/decrement() the counter."
            );
        }

        /**
         * @param string $class the model with the conflicting attributes
         * @return self
         */
        public static function MixedFillableAndGuarded(string $class): self
        {
            return new self(
                "{$class} has properties marked both #[Fillable] and #[Guarded].\n".
                "  Why: these are two different mass-assignment strategies - allow-list everything explicitly marked, or deny-list everything explicitly marked - and mixing them leaves no single answer for how an unmarked property should behave.\n".
                "  Fix: pick one strategy for {$class} and mark every relevant property with only that attribute."
            );
        }

        /**
         * @param string $class the model fill() was called on
         * @return self
         */
        public static function NoFillStrategy(string $class): self
        {
            return new self(
                "{$class}::fill() was called, but {$class} has no #[Fillable] or #[Guarded] properties declared.\n".
                "  Why: falling back to \"everything is mass-assignable\" for an unmarked class would silently allow any property - including ones that were never meant to be set from user input - to be assigned through fill().\n".
                "  Fix: mark at least one property #[Fillable] (or #[Guarded], if most of the class should be mass-assignable except a few properties) before calling fill()."
            );
        }

        /**
         * @param string $class the model the property belongs to
         * @param string $property the #[Immutable] property that was changed
         * @param mixed $originalValue the value it had when loaded
         * @param mixed $attemptedValue the value it was changed to
         * @return self
         */
        public static function ImmutablePropertyChanged(string $class, string $property, mixed $originalValue, mixed $attemptedValue): self
        {
            $original = is_scalar($originalValue) ? strval($originalValue) : gettype($originalValue);
            $attempted = is_scalar($attemptedValue) ? strval($attemptedValue) : gettype($attemptedValue);

            return new self(
                "{$class}::\${$property} is #[Immutable] and changed after its first save().\n".
                "  Why: it was loaded as '{$original}' but save() is being called with it set to '{$attempted}' - #[Immutable] properties can only be set up through the object's first save().\n".
                "  Fix: don't change {$property} after the object has been saved once, or remove #[Immutable] if this property is meant to be editable."
            );
        }

        /**
         * @param string $class the model declaring #[Mask]
         * @param string $property the property name
         * @param string $maskerClass the class passed to #[Mask]
         * @return self
         */
        public static function InvalidMasker(string $class, string $property, string $maskerClass): self
        {
            return new self(
                "#[Mask({$maskerClass}::class)] on {$class}::\${$property} doesn't implement Masker.\n".
                "  Why: Masked calls mask() on whatever class #[Mask] names - it has to implement the Masker interface for that call to be valid.\n".
                "  Fix: make {$maskerClass} implement Wixnit\\Data\\Masker, or point #[Mask] at a class that already does (GenericMasker, EmailMasker, PhoneMasker)."
            );
        }

        /**
         * @param string $class the model declaring #[Mask]
         * @param string $property the property name
         * @param string $actualType the property's actual declared type
         * @return self
         */
        public static function MaskOnWrongType(string $class, string $property, string $actualType): self
        {
            return new self(
                "#[Mask(...)] on {$class}::\${$property} is on a property typed '{$actualType}'.\n".
                "  Why: #[Mask] only configures a Masked property.\n".
                "  Fix: change {$class}::\${$property}'s type to Masked, or remove #[Mask] if this property isn't meant to be masked."
            );
        }

        /**
         * @return self
         */
        public static function SodiumExtensionMissing(): self
        {
            return new self(
                "Encrypted requires the sodium extension, which isn't loaded.\n".
                "  Why: Encrypted uses sodium_crypto_secretbox for authenticated encryption - there's no fallback to a weaker algorithm.\n".
                "  Fix: enable the sodium extension (bundled with PHP since 7.2 - check php.ini if it's disabled)."
            );
        }

        /**
         * @param string $operation "set" or "decrypt"
         * @return self
         */
        public static function NoEncryptionKeyAvailable(string $operation): self
        {
            return new self(
                "Encrypted::{$operation}() was called with no key available.\n".
                "  Why: no key was passed directly, and EncryptionConfig::Init() was never called (or this value was set() with an explicit key, so there's no key-id for EncryptionConfig to look up).\n".
                "  Fix: call EncryptionConfig::Init(\$key) once at bootstrap, or pass a key directly to {$operation}()."
            );
        }

        /**
         * @param string $keyId the key-id the value was encrypted under
         * @return self
         */
        public static function EncryptionKeyNotFound(string $keyId): self
        {
            return new self(
                "No configured key matches key-id '{$keyId}'.\n".
                "  Why: this value was encrypted under a key that isn't among the ones passed to EncryptionConfig::Init() - likely a rotation that dropped an old key too early.\n".
                "  Fix: include the old key in EncryptionConfig::Init()'s second argument, or pass the correct key directly to decrypt()."
            );
        }

        /**
         * @return self
         */
        public static function CorruptedEncryptedValue(): self
        {
            return new self(
                "Encrypted value is malformed and can't be decrypted.\n".
                "  Why: the stored value doesn't match the expected format - it may have been corrupted, or come from an unrelated source.\n".
                "  Fix: check where this value actually came from; it isn't a value Encrypted itself produced."
            );
        }

        /**
         * @return self
         */
        public static function DecryptionFailed(): self
        {
            return new self(
                "Decryption failed authentication.\n".
                "  Why: sodium_crypto_secretbox_open() rejected the ciphertext - either the wrong key was used, or the stored value was tampered with or corrupted.\n".
                "  Fix: verify the correct key is being used; if it is, treat this value as compromised or corrupted rather than trying to force a read."
            );
        }
    }
