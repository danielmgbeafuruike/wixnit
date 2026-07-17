<?php

    namespace Wixnit\Data;

    use ReflectionClass;
    use ReflectionNamedType;
    use Wixnit\Exception\PropertyException;
    use Wixnit\Interfaces\IMasker;

    /**
     * Resolves, once per class and cached from then on, which properties need special
     * post-construction wiring: Counter and LazyText need a live reference to their
     * parent object (bind()), FlagSet needs its #[Flags(...)] name list if any
     * (bindNames()), and Masked needs its #[Mask(...)] masker class if any
     * (bindMasker()) - all four follow the same "construct blank, then wire up" shape
     * HasManyCollection already uses via bindRelationCollections(), just for plain
     * value-object columns instead of relations.
     *
     * No cross-validation between two models here (unlike RelationMap) - but #[Mask]
     * is validated against the property it's actually on (must be Masked-typed, and
     * must name a class that really implements Masker).
     */
    class ValuePropertyMap
    {
        /** @var array<string, array> */
        private static array $cache = [];

        /**
         * @param string $class
         * @return array<array{property: string, kind: string, names: ?array, masker: ?string}>
         */
        public static function forClass(string $class): array
        {
            if(isset(self::$cache[$class]))
            {
                return self::$cache[$class];
            }

            $result = [];
            $reflection = new ReflectionClass($class);

            foreach($reflection->getProperties() as $property)
            {
                $type = $property->getType();
                $typeName = ($type instanceof ReflectionNamedType) ? $type->getName() : null;

                $maskAttributes = $property->getAttributes(Mask::class);

                if((count($maskAttributes) > 0) && ($typeName !== Masked::class))
                {
                    throw PropertyException::MaskOnWrongType($class, $property->getName(), $typeName ?? "mixed");
                }

                if($typeName === Counter::class)
                {
                    $result[] = ["property" => $property->getName(), "kind" => "counter", "names" => null, "masker" => null];
                }
                else if($typeName === LazyText::class)
                {
                    $result[] = ["property" => $property->getName(), "kind" => "lazyText", "names" => null, "masker" => null];
                }
                else if($typeName === FlagSet::class)
                {
                    $flagsAttributes = $property->getAttributes(Flags::class);
                    $names = (count($flagsAttributes) > 0) ? $flagsAttributes[0]->newInstance()->names : null;

                    $result[] = ["property" => $property->getName(), "kind" => "flagSet", "names" => $names, "masker" => null];
                }
                else if($typeName === Masked::class)
                {
                    $maskerClass = (count($maskAttributes) > 0) ? $maskAttributes[0]->newInstance()->masker : GenericMasker::class;

                    if(!is_subclass_of($maskerClass, IMasker::class))
                    {
                        throw PropertyException::InvalidMasker($class, $property->getName(), $maskerClass);
                    }

                    $result[] = ["property" => $property->getName(), "kind" => "masked", "names" => null, "masker" => $maskerClass];
                }
            }

            self::$cache[$class] = $result;
            return $result;
        }

        /**
         * @param string $class
         * @return string[] every LazyText property name declared on the class - used to
         *                    exclude them from the default SELECT field list
         */
        public static function lazyTextNames(string $class): array
        {
            $names = [];

            foreach(self::forClass($class) as $entry)
            {
                if($entry["kind"] === "lazyText")
                {
                    $names[] = $entry["property"];
                }
            }
            return $names;
        }

        /**
         * Clears the cache - intended for test suites that redefine classes between
         * runs, not for normal application use.
         * @return void
         */
        public static function reset(): void
        {
            self::$cache = [];
        }
    }
