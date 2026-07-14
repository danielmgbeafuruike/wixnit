<?php

    namespace Wixnit\Data;

    use ReflectionClass;
    use Wixnit\Exception\PropertyException;

    /**
     * Resolves, once per class and cached from then on, every property-level attribute
     * covered in this file: #[Unique], #[Fillable]/#[Guarded], #[Redacted], #[Cast],
     * #[Exclude], #[Searchable], #[Immutable]. One reflection pass per class rather than
     * one per attribute, mirroring RelationMap/ValuePropertyMap's shape.
     */
    class PropertyMap
    {
        private static array $cache = [];

        /**
         * @param string $class
         * @return array
         */
        private static function forClass(string $class): array
        {
            if(isset(self::$cache[$class]))
            {
                return self::$cache[$class];
            }

            $result = [
                "unique" => [],
                "fillable" => [],
                "guarded" => [],
                "redacted" => [],
                "excluded" => [],
                "searchable" => [],
                "immutable" => [],
                "casts" => [],
            ];

            $reflection = new ReflectionClass($class);

            foreach($reflection->getProperties() as $property)
            {
                $name = $property->getName();

                if(count($property->getAttributes(Unique::class)) > 0)
                {
                    $result["unique"][] = $name;
                }
                if(count($property->getAttributes(Fillable::class)) > 0)
                {
                    $result["fillable"][] = $name;
                }
                if(count($property->getAttributes(Guarded::class)) > 0)
                {
                    $result["guarded"][] = $name;
                }
                if(count($property->getAttributes(Redacted::class)) > 0)
                {
                    $result["redacted"][] = $name;
                }
                if(count($property->getAttributes(Exclude::class)) > 0)
                {
                    $result["excluded"][] = $name;
                }
                if(count($property->getAttributes(Searchable::class)) > 0)
                {
                    $result["searchable"][] = $name;
                }
                if(count($property->getAttributes(Immutable::class)) > 0)
                {
                    $result["immutable"][] = $name;
                }

                $castAttributes = $property->getAttributes(Cast::class);

                if(count($castAttributes) > 0)
                {
                    $result["casts"][$name] = $castAttributes[0]->newInstance()->caster;
                }
            }

            if((count($result["fillable"]) > 0) && (count($result["guarded"]) > 0))
            {
                throw PropertyException::MixedFillableAndGuarded($class);
            }

            self::$cache[$class] = $result;
            return $result;
        }

        public static function isUnique(string $class, string $property): bool
        {
            return in_array($property, self::forClass($class)["unique"], true);
        }

        public static function isExcluded(string $class, string $property): bool
        {
            return in_array($property, self::forClass($class)["excluded"], true);
        }

        public static function isRedacted(string $class, string $property): bool
        {
            return in_array($property, self::forClass($class)["redacted"], true);
        }

        public static function isImmutable(string $class, string $property): bool
        {
            return in_array($property, self::forClass($class)["immutable"], true);
        }

        /**
         * @param string $class
         * @return string[] every property name marked #[Immutable]
         */
        public static function immutableNames(string $class): array
        {
            return self::forClass($class)["immutable"];
        }

        /**
         * @param string $class
         * @return string|null the fully-qualified Caster class for this property, if any
         */
        public static function casterFor(string $class, string $property): ?string
        {
            return self::forClass($class)["casts"][$property] ?? null;
        }

        /**
         * @param string $class
         * @return string[] property names marked #[Searchable] - empty if none are, in
         *                    which case the caller should fall back to every field
         */
        public static function searchableNames(string $class): array
        {
            return self::forClass($class)["searchable"];
        }

        /**
         * Determines whether a given property name is allowed to be mass-assigned via
         * fill(), per the class's Fillable/Guarded strategy (mixed use of both was
         * already rejected when the class was first resolved).
         * @param string $class
         * @param string $property
         * @return bool
         */
        public static function isFillable(string $class, string $property): bool
        {
            $map = self::forClass($class);

            if(count($map["fillable"]) > 0)
            {
                return in_array($property, $map["fillable"], true);
            }
            if(count($map["guarded"]) > 0)
            {
                return !in_array($property, $map["guarded"], true);
            }
            return false;
        }

        /**
         * @param string $class
         * @return bool whether this class has declared a mass-assignment strategy at all
         *               (at least one #[Fillable] or #[Guarded] property somewhere)
         */
        public static function hasFillStrategy(string $class): bool
        {
            $map = self::forClass($class);
            return (count($map["fillable"]) > 0) || (count($map["guarded"]) > 0);
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
