<?php

    namespace Wixnit\Console;

    use Wixnit\Exception\ConsoleException;

    /**
     * Matches the tokens left over after GlobalOptions::extract() (and after the
     * command name itself has been shifted off) against one command's declared
     * CommandSignature - conventional Unix-style parsing, nothing invented:
     *
     *   - positional arguments matched to #[Argument] properties in declaration order
     *   - long options: --fresh, --name=value, or --name value
     *   - short options: -f, and short flags combine: -fv means -f -v
     *   - a literal "--" terminator: everything after it is positional, even if it
     *     starts with a dash - the standard escape hatch for a value that happens to
     *     look like a flag
     *
     * Every value is type-coerced to match its property's declared type before it's
     * handed back, so Kernel can mass-assign the result straight onto the command
     * instance without any further conversion.
     */
    class ArgvParser
    {
        /**
         * @param CommandSignature $signature
         * @param string[] $tokens tokens for this command only - the command name
         *   itself, and any global options, must already be stripped out
         * @return array{arguments: array<string, mixed>, options: array<string, mixed>}
         *   keyed by property name, ready to be mass-assigned onto a Command instance
         * @throws ConsoleException on an unknown option, a missing option value, a
         *   value that can't be coerced to its declared type, a missing required
         *   argument, or more positional values than the command declares
         */
        public static function parse(CommandSignature $signature, array $tokens): array
        {
            $arguments = [];
            $options = [];
            $positionalIndex = 0;
            $pastTerminator = false;

            $count = count($tokens);

            for($i = 0; $i < $count; $i++)
            {
                $token = $tokens[$i];

                if(!$pastTerminator && ($token === "--"))
                {
                    $pastTerminator = true;
                    continue;
                }

                if(!$pastTerminator && (strlen($token) > 2) && (substr($token, 0, 2) === "--"))
                {
                    $i = self::consumeLongOption($signature, $tokens, $i, $options);
                    continue;
                }

                if(!$pastTerminator && (strlen($token) > 1) && ($token[0] === "-") && ($token !== "-") && !preg_match('/^-[0-9]+(\.[0-9]+)?$/', $token))
                {
                    $i = self::consumeShortOptionCluster($signature, $tokens, $i, $options);
                    continue;
                }

                // positional
                if(!array_key_exists($positionalIndex, $signature->arguments))
                {
                    throw ConsoleException::TooManyArguments($signature->name, $token);
                }

                $definition = $signature->arguments[$positionalIndex];
                $arguments[$definition->property] = self::coerce($signature->name, $definition->name, $definition->type, $token);
                $positionalIndex++;
            }

            foreach($signature->arguments as $index => $definition)
            {
                if(array_key_exists($definition->property, $arguments))
                {
                    continue;
                }
                if($definition->required)
                {
                    throw ConsoleException::MissingArgument($signature->name, $definition->name);
                }
                $arguments[$definition->property] = $definition->default;
            }

            foreach($signature->options as $definition)
            {
                if(!array_key_exists($definition->property, $options))
                {
                    $options[$definition->property] = $definition->default;
                }
            }

            return ["arguments" => $arguments, "options" => $options];
        }

        /**
         * @param CommandSignature $signature
         * @param string[] $tokens
         * @param int $i index of the "--name" / "--name=value" token
         * @param array $options accumulator, keyed by property name, passed by reference
         * @return int the index to resume parsing from (advanced past a consumed value token)
         */
        private static function consumeLongOption(CommandSignature $signature, array $tokens, int $i, array &$options): int
        {
            $token = $tokens[$i];
            $body = substr($token, 2);

            $equalsPosition = strpos($body, "=");
            $hasInlineValue = ($equalsPosition !== false);

            $name = $hasInlineValue ? substr($body, 0, $equalsPosition) : $body;
            $inlineValue = $hasInlineValue ? substr($body, $equalsPosition + 1) : null;

            $definition = $signature->findOption($name);

            if($definition === null)
            {
                throw ConsoleException::UnknownOption($signature->name, "--{$name}");
            }

            if($definition->isFlag)
            {
                $value = $hasInlineValue ? self::parseBool($inlineValue) : true;
                $options[$definition->property] = $value;
                return $i;
            }

            if($hasInlineValue)
            {
                $raw = $inlineValue;
            }
            else if(($i + 1 < count($tokens)) && (($tokens[$i + 1] !== "--")))
            {
                $raw = $tokens[$i + 1];
                $i++;
            }
            else
            {
                throw ConsoleException::MissingOptionValue($signature->name, $name);
            }

            $coerced = self::coerce($signature->name, "--{$name}", $definition->type, $raw);

            if($definition->repeatable)
            {
                $options[$definition->property] = array_merge($options[$definition->property] ?? [], [$coerced]);
            }
            else
            {
                $options[$definition->property] = $coerced;
            }
            return $i;
        }

        /**
         * @param CommandSignature $signature
         * @param string[] $tokens
         * @param int $i index of the "-fv" / "-o" / "-oValue" token
         * @param array $options accumulator, keyed by property name, passed by reference
         * @return int the index to resume parsing from
         */
        private static function consumeShortOptionCluster(CommandSignature $signature, array $tokens, int $i, array &$options): int
        {
            $token = $tokens[$i];
            $letters = substr($token, 1);
            $length = strlen($letters);

            for($position = 0; $position < $length; $position++)
            {
                $letter = $letters[$position];
                $definition = $signature->findOptionByShortcut($letter);

                if($definition === null)
                {
                    throw ConsoleException::UnknownOption($signature->name, "-{$letter}");
                }

                if($definition->isFlag)
                {
                    $options[$definition->property] = true;
                    continue;
                }

                // a value-taking short option consumes the rest of this token (-oValue),
                // or the next token entirely if nothing follows it in this cluster (-o Value)
                $rest = substr($letters, $position + 1);

                if($rest !== "")
                {
                    $raw = $rest;
                    $position = $length; // stop scanning this token, its remainder was the value
                }
                else if(($i + 1 < count($tokens)) && ($tokens[$i + 1] !== "--"))
                {
                    $raw = $tokens[$i + 1];
                    $i++;
                }
                else
                {
                    throw ConsoleException::MissingOptionValue($signature->name, $definition->name);
                }

                $coerced = self::coerce($signature->name, "-{$letter}", $definition->type, $raw);

                if($definition->repeatable)
                {
                    $options[$definition->property] = array_merge($options[$definition->property] ?? [], [$coerced]);
                }
                else
                {
                    $options[$definition->property] = $coerced;
                }
            }
            return $i;
        }

        /**
         * @param string $command command name, for error messages
         * @param string $name argument/option name, for error messages
         * @param string $type "string"/"int"/"float"/"bool"/"array"
         * @param string $raw
         * @return mixed
         * @throws ConsoleException if $raw can't be coerced to $type
         */
        private static function coerce(string $command, string $name, string $type, string $raw): mixed
        {
            switch($type)
            {
                case "int":
                    if(!preg_match('/^-?[0-9]+$/', trim($raw)))
                    {
                        throw ConsoleException::InvalidValue($command, $name, "int", $raw);
                    }
                    return (int) $raw;

                case "float":
                    if(!is_numeric(trim($raw)))
                    {
                        throw ConsoleException::InvalidValue($command, $name, "float", $raw);
                    }
                    return (float) $raw;

                case "bool":
                    return self::parseBool($raw);

                default:
                    return $raw;
            }
        }

        private static function parseBool(string $raw): bool
        {
            $normalized = strtolower(trim($raw));
            return in_array($normalized, ["true", "1", "yes", "on", ""], true);
        }
    }
