<?php

    namespace Wixnit\Exception;

    /**
     * Thrown for mistakes in how the console kernel or a command's declared signature
     * is put together - a required #[Argument] declared after an optional one, two
     * #[Option]s fighting over the same shortcut, a command name collision, or an
     * interactive prompt that has nowhere to get an answer from under
     * --no-interaction. Every one of these is a configuration mistake a developer
     * makes, not a runtime failure a user of the finished command causes - which is
     * why registration-time problems (bad #[Argument]/#[Option] shape, duplicate
     * command names) are thrown eagerly from Kernel::register(), long before anyone
     * ever runs the command, the same way a misconfigured #[HasMany] is caught by
     * RelationMap the first time the model is touched rather than the first time the
     * relation is queried.
     */
    class ConsoleException extends WixnitException
    {
        /**
         * @param string $class the command class
         * @param string $property the argument that comes after an already-optional one
         * @param string $previousProperty the optional argument declared just before it
         * @return self
         */
        public static function ArgumentOrderInvalid(string $class, string $property, string $previousProperty): self
        {
            return new self(
                "#[Argument] \"{$property}\" on {$class} is required, but comes after \"{$previousProperty}\", which is optional.\n".
                "  Why: positional arguments are matched by position - once one can be skipped, every position after it becomes ambiguous.\n".
                "  Fix: reorder the properties so every required #[Argument] comes before the first optional one, or give \"{$property}\" a default/make it nullable.",
                ["class" => $class, "property" => $property, "previousProperty" => $previousProperty]
            );
        }

        /**
         * @param string $class the command class
         * @param string $shortcut the colliding shortcut letter
         * @param string $first the property that first claimed it
         * @param string $second the property that collided with it
         * @return self
         */
        public static function DuplicateShortcut(string $class, string $shortcut, string $first, string $second): self
        {
            return new self(
                "#[Option] shortcut \"-{$shortcut}\" is used by both \"{$first}\" and \"{$second}\" on {$class}.\n".
                "  Why: a single-letter shortcut can't mean two different options at once.\n".
                "  Fix: give one of them a different shortcut, or drop it and rely on the long form.",
                ["class" => $class, "shortcut" => $shortcut, "first" => $first, "second" => $second]
            );
        }

        /**
         * @param string $name the command name two classes are both claiming
         * @param string $existing the already-registered class
         * @param string $incoming the class attempting to register under the same name
         * @return self
         */
        public static function DuplicateCommandName(string $name, string $existing, string $incoming): self
        {
            return new self(
                "Command name \"{$name}\" is already registered to {$existing}, but {$incoming} also declares #[AsCommand('{$name}')].\n".
                "  Why: two commands can't both claim the same name - the kernel wouldn't know which one \"php wixnit {$name}\" should run.\n".
                "  Fix: rename one of the two commands.",
                ["name" => $name, "existing" => $existing, "incoming" => $incoming]
            );
        }

        /**
         * @param string $name the reserved name ("list" or "help")
         * @return self
         */
        public static function ReservedCommandName(string $name): self
        {
            return new self(
                "\"{$name}\" is a reserved command name - it's built into the kernel and always available.\n".
                "  Why: every fresh Kernel already responds to \"{$name}\", the same way a fresh Router still responds to something.\n".
                "  Fix: give your command a different name.",
                ["name" => $name]
            );
        }

        /**
         * @param string $class the class passed to Kernel::register()
         * @return self
         */
        public static function MissingCommandAttribute(string $class): self
        {
            return new self(
                "{$class} has no #[AsCommand(...)] attribute on it.\n".
                "  Why: the kernel needs a name (and optionally a description) to register and dispatch to a command - there's no fallback derived from the class name.\n".
                "  Fix: add #[AsCommand('your:command-name', description: '...')] above the class declaration.",
                ["class" => $class]
            );
        }

        /**
         * @param string $class the class passed to Kernel::register()
         * @return self
         */
        public static function NotACommand(string $class): self
        {
            return new self(
                "{$class} is not a Wixnit\\Console\\Command - it doesn't extend it.\n".
                "  Why: the kernel constructs the class and calls handle() on it, which only exists on Command.\n".
                "  Fix: make {$class} extend \\Wixnit\\Console\\Command and implement handle(): int.",
                ["class" => $class]
            );
        }

        /**
         * @param string $class the command class
         * @param string $property the property carrying both attributes
         * @return self
         */
        public static function ArgumentAndOptionOnSameProperty(string $class, string $property): self
        {
            return new self(
                "\${$property} on {$class} has both #[Argument] and #[Option] attributes.\n".
                "  Why: a single property can't be both a positional argument and a --flag at once - they're parsed completely differently.\n".
                "  Fix: keep whichever one actually matches how it should be passed on the command line, and remove the other.",
                ["class" => $class, "property" => $property]
            );
        }

        /**
         * @param string $command the command that was invoked
         * @param string $name the argument that had no value and no default
         * @return self
         */
        public static function MissingArgument(string $command, string $name): self
        {
            return new self(
                "Missing required argument \"{$name}\" for \"{$command}\".\n".
                "  Why: this argument has no default, so a value must be given on the command line.\n".
                "  Fix: run \"php wixnit help {$command}\" to see the expected usage.",
                ["command" => $command, "argument" => $name]
            );
        }

        /**
         * @param string $command the command that was invoked
         * @param string $token the surplus positional value
         * @return self
         */
        public static function TooManyArguments(string $command, string $token): self
        {
            return new self(
                "Unexpected argument \"{$token}\" for \"{$command}\" - it doesn't declare that many positional arguments.\n".
                "  Fix: run \"php wixnit help {$command}\" to see the expected usage, or remove the extra value.",
                ["command" => $command, "token" => $token]
            );
        }

        /**
         * @param string $command the command that was invoked
         * @param string $option the unrecognized option, as typed (e.g. "--foo" or "-x")
         * @return self
         */
        public static function UnknownOption(string $command, string $option): self
        {
            return new self(
                "Unknown option \"{$option}\" for \"{$command}\".\n".
                "  Fix: run \"php wixnit help {$command}\" to see the options it accepts.",
                ["command" => $command, "option" => $option]
            );
        }

        /**
         * @param string $command the command that was invoked
         * @param string $option the option that needed a value
         * @return self
         */
        public static function MissingOptionValue(string $command, string $option): self
        {
            return new self(
                "Option \"--{$option}\" for \"{$command}\" requires a value, but none was given.\n".
                "  Fix: pass it as \"--{$option}=value\" or \"--{$option} value\".",
                ["command" => $command, "option" => $option]
            );
        }

        /**
         * @param string $command the command that was invoked
         * @param string $name the argument or option whose value couldn't be coerced
         * @param string $type the declared type it needed to satisfy
         * @param string $value the raw value that was given
         * @return self
         */
        public static function InvalidValue(string $command, string $name, string $type, string $value): self
        {
            return new self(
                "Invalid value for \"{$name}\" on \"{$command}\": \"{$value}\" is not a valid {$type}.",
                ["command" => $command, "name" => $name, "type" => $type, "value" => $value]
            );
        }

        /**
         * @param string $question the prompt text that had no way to get an answer
         * @return self
         */
        public static function PromptRequiresInteraction(string $question): self
        {
            return new self(
                "Cannot prompt \"{$question}\" - running with --no-interaction and no default was given.\n".
                "  Why: there's no way to answer without a human present, and hanging forever isn't an acceptable failure mode for an unattended script.\n".
                "  Fix: pass an explicit default to ask()/confirm()/choice(), or supply the value some other way (an argument/option) when running non-interactively.",
                ["question" => $question]
            );
        }

        /**
         * @param string $name the command name that was looked up
         * @return self
         */
        public static function UnknownCommand(string $name): self
        {
            return new self(
                "Command \"{$name}\" is not defined.\n".
                "  Fix: run \"php wixnit list\" to see every registered command.",
                ["name" => $name]
            );
        }
    }
