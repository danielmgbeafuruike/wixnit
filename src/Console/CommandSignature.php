<?php

    namespace Wixnit\Console;

    /**
     * The fully resolved shape of one Command class - its name, description, and every
     * #[Argument]/#[Option] it declares, in declaration order. Built once per class by
     * CommandMap and cached there; ArgvParser, ListCommand, and HelpCommand all read
     * from this rather than re-reflecting the class themselves.
     */
    class CommandSignature
    {
        /**
         * @param string $class fully-qualified Command subclass name
         * @param string $name the #[AsCommand] name, e.g. "migrate:run"
         * @param string $description
         * @param ArgumentDefinition[] $arguments in declaration order
         * @param OptionDefinition[] $options in declaration order
         */
        function __construct(
            public readonly string $class,
            public readonly string $name,
            public readonly string $description,
            public readonly array $arguments,
            public readonly array $options,
        ) {}

        /**
         * The part of the name before the first ":", used by `list` to group commands
         * ("make:model" groups under "make"). Commands with no ":" in their name group
         * under "general".
         * @return string
         */
        public function group(): string
        {
            return str_contains($this->name, ":") ? strstr($this->name, ":", true) : "general";
        }

        /**
         * @param string $name long option name, without the leading "--"
         * @return OptionDefinition|null
         */
        public function findOption(string $name): ?OptionDefinition
        {
            foreach($this->options as $option)
            {
                if($option->name === $name)
                {
                    return $option;
                }
            }
            return null;
        }

        /**
         * @param string $shortcut single letter, without the leading "-"
         * @return OptionDefinition|null
         */
        public function findOptionByShortcut(string $shortcut): ?OptionDefinition
        {
            foreach($this->options as $option)
            {
                if($option->shortcut === $shortcut)
                {
                    return $option;
                }
            }
            return null;
        }

        /**
         * A single-line usage synopsis, e.g. "migrate:run [<model>] [--fresh]" - used by
         * both HelpCommand and the error ConsoleIO prints when a command is invoked
         * with a bad signature.
         * @return string
         */
        public function usage(): string
        {
            $parts = [$this->name];

            foreach($this->arguments as $argument)
            {
                $parts[] = $argument->required ? "<{$argument->name}>" : "[<{$argument->name}>]";
            }
            foreach($this->options as $option)
            {
                $flag = $option->isFlag ? "--{$option->name}" : "--{$option->name}=<value>";
                $parts[] = "[{$flag}]";
            }
            return implode(" ", $parts);
        }
    }
