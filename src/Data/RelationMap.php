<?php

    namespace Wixnit\Data;

    use ReflectionClass;
    use ReflectionNamedType;
    use ReflectionProperty;
    use Wixnit\Exception\RelationException;

    /**
     * Resolves and validates every declared relation on a model class - #[HasMany],
     * #[BelongsToMany], and #[HasManyThrough] - by reflecting over its properties once
     * and caching the result. Every other part of the framework that needs relation
     * metadata (hydration, DBMigrator, DB::Connect()'s field list, hydrate()) goes
     * through this rather than re-reflecting or special-casing one relation kind at a time.
     *
     * Validation happens here, the first time a class is resolved, so a misconfigured
     * relation is caught the first time the model is touched at all, not silently at
     * query time.
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
                $hasManyAttrs = $property->getAttributes(HasMany::class);
                $belongsToManyAttrs = $property->getAttributes(BelongsToMany::class);
                $throughAttrs = $property->getAttributes(HasManyThrough::class);

                $count = count($hasManyAttrs) + count($belongsToManyAttrs) + count($throughAttrs);

                if($count === 0)
                {
                    continue;
                }
                if($count > 1)
                {
                    throw RelationException::MultipleRelationAttributes($class, $property->getName());
                }

                if(count($hasManyAttrs) > 0)
                {
                    $definitions[] = self::buildHasMany($class, $property, $hasManyAttrs[0]->newInstance());
                }
                else if(count($belongsToManyAttrs) > 0)
                {
                    $definitions[] = self::buildBelongsToMany($class, $property, $belongsToManyAttrs[0]->newInstance());
                }
                else
                {
                    $definitions[] = self::buildHasManyThrough($class, $property, $throughAttrs[0]->newInstance());
                }
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
         * @param string $class
         * @return RelationDefinition[] only the belongsToMany relations declared on the class
         */
        public static function pivotRelations(string $class): array
        {
            return array_values(array_filter(self::forClass($class), fn(RelationDefinition $d) => $d->relationType === "belongsToMany"));
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

        //#region hasMany

        private static function buildHasMany(string $class, ReflectionProperty $property, HasMany $hasMany): RelationDefinition
        {
            $kind = self::resolveArrayOrCollectionKind($class, $property);

            if(!is_subclass_of($hasMany->related, Transactable::class))
            {
                throw RelationException::InvalidRelatedClass($class, $property->getName(), $hasMany->related);
            }

            self::assertBelongsToMatches($class, $property->getName(), $hasMany->related, $hasMany->foreignKey);

            $definition = new RelationDefinition();
            $definition->propertyName = $property->getName();
            $definition->related = $hasMany->related;
            $definition->kind = $kind;
            $definition->relationType = "hasMany";
            $definition->foreignKey = $hasMany->foreignKey;
            $definition->localKey = $hasMany->localKey;
            return $definition;
        }

        private static function assertBelongsToMatches(string $ownerClass, string $ownerProperty, string $relatedClass, string $foreignKey): void
        {
            $childReflection = new ReflectionClass($relatedClass);

            if(!$childReflection->hasProperty($foreignKey))
            {
                throw RelationException::MissingBelongsTo($ownerClass, $ownerProperty, $relatedClass, $foreignKey);
            }

            $childProperty = $childReflection->getProperty($foreignKey);
            $belongsToAttributes = $childProperty->getAttributes(BelongsTo::class);

            if(count($belongsToAttributes) === 0)
            {
                throw RelationException::MissingBelongsTo($ownerClass, $ownerProperty, $relatedClass, $foreignKey);
            }

            /** @var BelongsTo $belongsTo */
            $belongsTo = $belongsToAttributes[0]->newInstance();

            if(($belongsTo->related !== $ownerClass) && (!is_subclass_of($ownerClass, $belongsTo->related)))
            {
                throw RelationException::MismatchedBelongsTo($ownerClass, $ownerProperty, $relatedClass, $foreignKey, $belongsTo->related);
            }

            self::assertStringForeignKeyColumn($relatedClass, $foreignKey, $childProperty, $childReflection);
        }

        private static function assertStringForeignKeyColumn(string $relatedClass, string $foreignKey, ReflectionProperty $childProperty, ReflectionClass $childReflection): void
        {
            $fkType = $childProperty->getType();
            $fkTypeName = ($fkType instanceof ReflectionNamedType) ? $fkType->getName() : null;

            if($fkTypeName !== "string")
            {
                throw RelationException::InvalidBelongsToPropertyType($relatedClass, $foreignKey, $fkTypeName ?? "mixed");
            }

            $defaults = $childReflection->getDefaultProperties();
            $uniqueList = $defaults['unique'] ?? [];

            if(is_array($uniqueList))
            {
                $lowered = array_map('strtolower', $uniqueList);

                if(in_array(strtolower($foreignKey), $lowered, true))
                {
                    throw RelationException::BelongsToMarkedUnique($relatedClass, $foreignKey);
                }
            }
        }

        //#endregion

        //#region belongsToMany

        private static function buildBelongsToMany(string $class, ReflectionProperty $property, BelongsToMany $belongsToMany): RelationDefinition
        {
            $kind = self::resolveArrayOrCollectionKind($class, $property, BelongsToManyCollection::class);

            if(!is_subclass_of($belongsToMany->related, Transactable::class))
            {
                throw RelationException::InvalidRelatedClass($class, $property->getName(), $belongsToMany->related);
            }

            Identifier::assertSafe($belongsToMany->pivot);
            Identifier::assertSafe($belongsToMany->localKey);
            Identifier::assertSafe($belongsToMany->relatedKey);

            if(strtolower($belongsToMany->localKey) === strtolower($belongsToMany->relatedKey))
            {
                throw RelationException::PivotKeysMustDiffer($class, $property->getName(), $belongsToMany->localKey);
            }

            $definition = new RelationDefinition();
            $definition->propertyName = $property->getName();
            $definition->related = $belongsToMany->related;
            $definition->kind = $kind;
            $definition->relationType = "belongsToMany";
            $definition->pivotTable = strtolower($belongsToMany->pivot);
            $definition->pivotLocalKey = strtolower($belongsToMany->localKey);
            $definition->pivotRelatedKey = strtolower($belongsToMany->relatedKey);
            return $definition;
        }

        private static function resolveArrayOrCollectionKind(string $class, ReflectionProperty $property, string $collectionClass = HasManyCollection::class): string
        {
            $type = $property->getType();
            $typeName = ($type instanceof ReflectionNamedType) ? $type->getName() : null;

            if($typeName === "array")
            {
                return "array";
            }
            if($typeName === $collectionClass)
            {
                return "collection";
            }
            throw RelationException::InvalidHasManyPropertyType($class, $property->getName(), $typeName ?? "mixed");
        }

        //#endregion

        //#region hasManyThrough

        private static function buildHasManyThrough(string $class, ReflectionProperty $property, HasManyThrough $through): RelationDefinition
        {
            $type = $property->getType();
            $typeName = ($type instanceof ReflectionNamedType) ? $type->getName() : null;

            if($typeName !== "array")
            {
                throw RelationException::InvalidHasManyThroughPropertyType($class, $property->getName(), $typeName ?? "mixed");
            }

            if(!is_subclass_of($through->related, Transactable::class))
            {
                throw RelationException::InvalidRelatedClass($class, $property->getName(), $through->related);
            }
            if(!is_subclass_of($through->through, Transactable::class))
            {
                throw RelationException::InvalidRelatedClass($class, $property->getName(), $through->through);
            }

            //the "through" model's local-side foreign key must be a real #[BelongsTo] pointing back at $class
            self::assertBelongsToMatches($class, $property->getName(), $through->through, $through->throughLocalKey);

            //the "through" model's related-side foreign key must be a real #[BelongsTo] pointing at $related
            self::assertBelongsToMatches($through->related, $property->getName(), $through->through, $through->throughRelatedKey);

            $definition = new RelationDefinition();
            $definition->propertyName = $property->getName();
            $definition->related = $through->related;
            $definition->kind = "array";
            $definition->relationType = "hasManyThrough";
            $definition->throughClass = $through->through;
            $definition->throughLocalKey = $through->throughLocalKey;
            $definition->throughRelatedKey = $through->throughRelatedKey;
            return $definition;
        }

        //#endregion
    }
