<?php

    namespace Wixnit\Console;

    /**
     * The resolved shape of one #[Argument] property, as computed once by CommandMap
     * and handed to ArgvParser to match against positional values on the command line.
     * Not constructed directly - see CommandMap::forClass().
     */
    class ArgumentDefinition
    {
        /**
         * @param string $property the property name on the Command class
         * @param string $name the display name shown in help/usage (kebab-cased property name)
         * @param string $description
         * @param mixed $default value used when the argument isn't given and it isn't required
         * @param bool $required
         * @param string $type one of "string", "int", "float", "bool"
         * @param bool $nullable whether the property's own type allows null
         */
        function __construct(
            public readonly string $property,
            public readonly string $name,
            public readonly string $description,
            public readonly mixed $default,
            public readonly bool $required,
            public readonly string $type,
            public readonly bool $nullable,
        ) {}
    }
