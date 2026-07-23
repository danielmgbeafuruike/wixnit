<?php

    namespace Wixnit\Console;

    use ReflectionClass;
    use ReflectionNamedType;
    use ReflectionProperty;
    use Wixnit\Exception\ConsoleException;
    use Wixnit\Utilities\Str;

    /**
     * Resolves and validates a Command class's declared #[AsCommand]/#[Argument]/
     * #[Option] shape by reflecting over it once and caching the result - the same
     * "reflect once, cache the result" shape RelationMap/PropertyMap/ValuePropertyMap
     * already established for the ORM side of the framework, applied to commands
     * instead of models.
     *
     * Validation happens here, the first time a class is resolved (which Kernel::register()
     * forces eagerly), so a misconfigured command is caught the moment it's registered,
     * not the first time someone happens to run it:
     *
     *   - a required #[Argument] declared after an optional one
     *   - two #[Option]s sharing a --shortcut
     *   - a property carrying both #[Argument] and #[Option]
     *   - a class with no #[AsCommand(...)] attribute, or that doesn't extend Command
     */
    class CommandMap
    {
        /** @var array<string, CommandSignature> */
        private static array $cache = [];

        /**
         * @param string $class fully-qualified Command subclass name
         * @return CommandSignature
         * @throws ConsoleException if the class's declared shape is invalid
         */
        public static function forClass(string $class): CommandSignature
        {
            if(isset(self::$cache[$class]))
            {
                return self::$cache[$class];
            }

            $reflection = new ReflectionClass($class);

            if(!$reflection->isSubclassOf(Command::class))
            {
                throw ConsoleException::NotACommand($class);
            }

            $commandAttributes = $reflection->getAttributes(AsCommand::class);

            if(count($commandAttributes) === 0)
            {
                throw ConsoleException::MissingCommandAttribute($class);
            }

            /** @var AsCommand $asCommand */
            $asCommand = $commandAttributes[0]->newInstance();

            $arguments = [];
            $options = [];

            $lastOptionalArgument = null;
            $shortcuts = [];

            foreach($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property)
            {
                if($property->isStatic())
                {
                    continue;
                }

                $argumentAttributes = $property->getAttributes(Argument::class);
                $optionAttributes = $property->getAttributes(Option::class);

                if((count($argumentAttributes) > 0) && (count($optionAttributes) > 0))
                {
                    throw ConsoleException::ArgumentAndOptionOnSameProperty($class, $property->getName());
                }

                if(count($argumentAttributes) > 0)
                {
                    $definition = self::buildArgument($property, $argumentAttributes[0]->newInstance());
                    $arguments[] = $definition;

                    if(!$definition->required)
                    {
                        $lastOptionalArgument = $definition->property;
                    }
                    else if($lastOptionalArgument !== null)
                    {
                        throw ConsoleException::ArgumentOrderInvalid($class, $definition->property, $lastOptionalArgument);
                    }
                }
                else if(count($optionAttributes) > 0)
                {
                    $definition = self::buildOption($property, $optionAttributes[0]->newInstance());
                    $options[] = $definition;

                    if($definition->shortcut !== null)
                    {
                        if(isset($shortcuts[$definition->shortcut]))
                        {
                            throw ConsoleException::DuplicateShortcut($class, $definition->shortcut, $shortcuts[$definition->shortcut], $definition->property);
                        }
                        $shortcuts[$definition->shortcut] = $definition->property;
                    }
                }
            }

            $signature = new CommandSignature($class, $asCommand->name, $asCommand->description, $arguments, $options);
            self::$cache[$class] = $signature;
            return $signature;
        }

        /**
         * Clear the cache - mainly useful between tests, where the same class name
         * might be redefined with a different shape across test cases.
         * @return void
         */
        public static function flush(): void
        {
            self::$cache = [];
        }

        private static function buildArgument(ReflectionProperty $property, Argument $attribute): ArgumentDefinition
        {
            [$type, $nullable] = self::resolveType($property);

            $required = $attribute->required || (($attribute->default === null) && !$nullable && !$property->hasDefaultValue());

            $default = $attribute->default;
            if(($default === null) && $property->hasDefaultValue())
            {
                $default = $property->getDefaultValue();
            }

            return new ArgumentDefinition(
                property: $property->getName(),
                name: Str::KebabCase($property->getName()),
                description: $attribute->description,
                default: $default,
                required: $required,
                type: $type,
                nullable: $nullable,
            );
        }

        private static function buildOption(ReflectionProperty $property, Option $attribute): OptionDefinition
        {
            [$type] = self::resolveType($property);

            $isFlag = ($type === "bool");
            $repeatable = ($type === "array");

            $default = $property->hasDefaultValue()
                ? $property->getDefaultValue()
                : ($repeatable ? [] : ($isFlag ? false : null));

            return new OptionDefinition(
                property: $property->getName(),
                name: Str::KebabCase($property->getName()),
                shortcut: $attribute->shortcut,
                description: $attribute->description,
                type: $type,
                isFlag: $isFlag,
                repeatable: $repeatable,
                default: $default,
            );
        }

        /**
         * @param ReflectionProperty $property
         * @return array{0: string, 1: bool} the resolved scalar type name ("string"/"int"/
         *   "float"/"bool"/"array", defaulting to "string" for anything untyped or not
         *   a plain named type) and whether the declared type allows null
         */
        private static function resolveType(ReflectionProperty $property): array
        {
            $reflectionType = $property->getType();

            if(!($reflectionType instanceof ReflectionNamedType))
            {
                return ["string", true];
            }

            $name = $reflectionType->getName();
            $nullable = $reflectionType->allowsNull();

            if(in_array($name, ["string", "int", "float", "bool", "array"], true))
            {
                return [$name, $nullable];
            }
            return ["string", $nullable];
        }
    }
