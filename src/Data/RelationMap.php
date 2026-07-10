<?php

    namespace Wixnit\Data;

    use ReflectionClass;
    use ReflectionNamedType;
    use Wixnit\Exception\RelationException;

    /**
     * Resolves and validates every #[HasMany] relation declared on a model class, by
     * reflecting over its properties once and caching the result - every other part of
     * the framework that needs relation metadata (hydration, DBMigrator, hydrate()) goes
     * through this rather than re-reflecting.
     *
     * Validation happens here, the first time a class is resolved, so a misconfigured
     * relation (wrong property type, missing/mismatched #[BelongsTo] on the other side,
     * a foreign key marked unique) is caught the first time the model is touched at all,
     * not silently at query time.
     */
    class RelationMap
    {
        /** @var array<string, RelationDefinition[]> */
        private static array $cache = [];

        /**
         * @param string $class
         * @return RelationDefinition[]
         */
        public static function forClass(string $class): array
        {
            if(isset(self::$cache[$class]))
            {
                return self::$cache[$class];
            }

            $definitions = [];
            $reflection = new ReflectionClass($class);

            foreach($reflection->getProperties() as $property)
            {
                $attributes = $property->getAttributes(HasMany::class);

                if(count($attributes) === 0)
                {
                    continue;
                }

                /** @var HasMany $hasMany */
                $hasMany = $attributes[0]->newInstance();

                $kind = self::resolveKind($class, $property, $hasMany);

                if(!is_subclass_of($hasMany->related, Transactable::class))
                {
                    throw RelationException::InvalidRelatedClass($class, $property->getName(), $hasMany->related);
                }

                self::assertBelongsToMatches($class, $property->getName(), $hasMany);

                $definitions[] = new RelationDefinition(
                    $property->getName(),
                    $hasMany->related,
                    $hasMany->foreignKey,
                    $hasMany->localKey,
                    $kind
                );
            }

            self::$cache[$class] = $definitions;
            return $definitions;
        }

        /**
         * @param string $class
         * @param string $propertyName
         * @return RelationDefinition|null
         */
        public static function get(string $class, string $propertyName): ?RelationDefinition
        {
            foreach(self::forClass($class) as $definition)
            {
                if($definition->propertyName === $propertyName)
                {
                    return $definition;
                }
            }
            return null;
        }

        /**
         * @param string $class
         * @return string[] every relation property name declared on the class
         */
        public static function names(string $class): array
        {
            return array_map(fn(RelationDefinition $d) => $d->propertyName, self::forClass($class));
        }

        /**
         * Clears the cache - intended for test suites that redefine classes between runs,
         * not for normal application use.
         * @return void
         */
        public static function reset(): void
        {
            self::$cache = [];
        }

        private static function resolveKind(string $class, \ReflectionProperty $property, HasMany $hasMany): string
        {
            $type = $property->getType();
            $typeName = ($type instanceof ReflectionNamedType) ? $type->getName() : null;

            if($typeName === "array")
            {
                return "array";
            }
            if($typeName === HasManyCollection::class)
            {
                return "collection";
            }
            throw RelationException::InvalidHasManyPropertyType($class, $property->getName(), $typeName ?? "mixed");
        }

        private static function assertBelongsToMatches(string $ownerClass, string $ownerProperty, HasMany $hasMany): void
        {
            $childReflection = new ReflectionClass($hasMany->related);

            if(!$childReflection->hasProperty($hasMany->foreignKey))
            {
                throw RelationException::MissingBelongsTo($ownerClass, $ownerProperty, $hasMany->related, $hasMany->foreignKey);
            }

            $childProperty = $childReflection->getProperty($hasMany->foreignKey);
            $belongsToAttributes = $childProperty->getAttributes(BelongsTo::class);

            if(count($belongsToAttributes) === 0)
            {
                throw RelationException::MissingBelongsTo($ownerClass, $ownerProperty, $hasMany->related, $hasMany->foreignKey);
            }

            /** @var BelongsTo $belongsTo */
            $belongsTo = $belongsToAttributes[0]->newInstance();

            if(($belongsTo->related !== $ownerClass) && (!is_subclass_of($ownerClass, $belongsTo->related)))
            {
                throw RelationException::MismatchedBelongsTo($ownerClass, $ownerProperty, $hasMany->related, $hasMany->foreignKey, $belongsTo->related);
            }

            $fkType = $childProperty->getType();
            $fkTypeName = ($fkType instanceof ReflectionNamedType) ? $fkType->getName() : null;

            if($fkTypeName !== "string")
            {
                throw RelationException::InvalidBelongsToPropertyType($hasMany->related, $hasMany->foreignKey, $fkTypeName ?? "mixed");
            }

            $defaults = $childReflection->getDefaultProperties();
            $uniqueList = $defaults['unique'] ?? [];

            if(is_array($uniqueList))
            {
                $lowered = array_map('strtolower', $uniqueList);

                if(in_array(strtolower($hasMany->foreignKey), $lowered, true))
                {
                    throw RelationException::BelongsToMarkedUnique($hasMany->related, $hasMany->foreignKey);
                }
            }
        }
    }
