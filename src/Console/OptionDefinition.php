<?php

    namespace Wixnit\Console;

    /**
     * The resolved shape of one #[Option] property, as computed once by CommandMap and
     * handed to ArgvParser to match against --long/-short tokens on the command line.
     * Not constructed directly - see CommandMap::forClass().
     */
    class OptionDefinition
    {
        /**
         * @param string $property the property name on the Command class
         * @param string $name the long option name, e.g. "fresh" for --fresh (kebab-cased property name)
         * @param string|null $shortcut single letter for -x, or null if none was declared
         * @param string $description
         * @param string $type one of "string", "int", "float", "bool", "array"
         * @param bool $isFlag true for bool-typed options - present or not, never takes a value
         * @param bool $repeatable true for array-typed options - collects every occurrence
         * @param mixed $default value used when the option isn't given (false for flags, [] for repeatable, otherwise the property's/attribute's declared default)
         */
        function __construct(
            public readonly string $property,
            public readonly string $name,
            public readonly ?string $shortcut,
            public readonly string $description,
            public readonly string $type,
            public readonly bool $isFlag,
            public readonly bool $repeatable,
            public readonly mixed $default,
        ) {}
    }
