<?php

    namespace Wixnit\Exception;

    use Exception;

    /**
     * Thrown for mistakes in how a smart value-object property (Counter,
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
    }
