<?php

    namespace Wixnit\Exception;

    use Exception;

    /**
     * Thrown for mistakes in how a relation is *described* (Filter::On(), WhereHas()) -
     * as distinct from DatabaseException, which covers failures the database itself reports
     * once a query actually runs. RelationException catches misconfiguration earlier, before
     * a query is even built, with a message aimed at the developer wiring up the relation.
     */
    class RelationException extends Exception
    {
        public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }

        /**
         * @param string $context which call this happened in, e.g. "Filter::On()" or "WhereHas()"
         * @param string $argument which argument was left empty, e.g. "relation" or "childTable"
         * @return self
         */
        public static function EmptyRelationTarget(string $context, string $argument): self
        {
            return new self(
                "Empty '{$argument}' passed to {$context}.\n".
                "  Why: relation/table names can't be blank - an empty string can't be resolved to a real property or table.\n".
                "  Fix: pass the related property's name (for Filter::On(), e.g. 'wallet' for a `public Wallet \$wallet` property) ".
                "or the child table's real name (for WhereHas(), e.g. 'wallettransaction')."
            );
        }

        /**
         * @param string $relation the property/relation name that was used
         * @param string $modelClass the parent model class it was used against
         * @return self
         */
        public static function NoConditionsProvided(string $relation): self
        {
            return new self(
                "Filter::On('{$relation}', []) was called with no conditions.\n".
                "  Why: an empty condition array doesn't restrict anything, so this call has no effect and is almost certainly a mistake.\n".
                "  Fix: pass at least one field=>value pair, e.g. Filter::On('{$relation}', ['amount' => new GreaterThan(100)])."
            );
        }

        /**
         * @param string $class the model declaring the #[HasMany] property
         * @param string $property the property name
         * @param string $actualType the property's actual declared type
         * @return self
         */
        public static function InvalidHasManyPropertyType(string $class, string $property, string $actualType): self
        {
            return new self(
                "#[HasMany] on {$class}::\${$property} is on a property typed '{$actualType}'.\n".
                "  Why: #[HasMany] only supports two property types - 'array' (eager by default) or HasManyCollection (lazy by default).\n".
                "  Fix: change {$class}::\${$property}'s type to 'array' or 'HasManyCollection', or remove the #[HasMany] attribute if this property isn't a relation."
            );
        }

        /**
         * @param string $class the model declaring the #[HasMany] property
         * @param string $property the property name
         * @param string $relatedClass the class passed as #[HasMany]'s first argument
         * @return self
         */
        public static function InvalidRelatedClass(string $class, string $property, string $relatedClass): self
        {
            return new self(
                "#[HasMany] on {$class}::\${$property} targets '{$relatedClass}', which isn't a Transactable model.\n".
                "  Why: HasMany relations can only point at another model that extends Transactable.\n".
                "  Fix: pass a Transactable subclass as #[HasMany]'s first argument, e.g. #[HasMany({$relatedClass}::class, '...')] only if {$relatedClass} extends Model/Transactable."
            );
        }

        /**
         * @param string $ownerClass the model declaring #[HasMany]
         * @param string $ownerProperty the #[HasMany] property name
         * @param string $relatedClass the related (child) class
         * @param string $foreignKey the foreign key name #[HasMany] expects on the child
         * @return self
         */
        public static function MissingBelongsTo(string $ownerClass, string $ownerProperty, string $relatedClass, string $foreignKey): self
        {
            return new self(
                "#[HasMany] on {$ownerClass}::\${$ownerProperty} expects {$relatedClass}::\${$foreignKey} to exist and carry #[BelongsTo({$ownerClass}::class)], but it doesn't.\n".
                "  Why: HasMany and BelongsTo describe the two sides of the same relation - the child needs its own declared foreign key property for HasMany to hydrate or write through.\n".
                "  Fix: add 'public string \${$foreignKey} = \"\";' to {$relatedClass}, decorated with #[BelongsTo({$ownerClass}::class)]."
            );
        }

        /**
         * @param string $ownerClass the model declaring #[HasMany]
         * @param string $ownerProperty the #[HasMany] property name
         * @param string $relatedClass the related (child) class
         * @param string $foreignKey the foreign key property name
         * @param string $actualTarget what #[BelongsTo] on the child actually points at
         * @return self
         */
        public static function MismatchedBelongsTo(string $ownerClass, string $ownerProperty, string $relatedClass, string $foreignKey, string $actualTarget): self
        {
            return new self(
                "{$relatedClass}::\${$foreignKey} carries #[BelongsTo({$actualTarget}::class)], but {$ownerClass}::\${$ownerProperty}'s #[HasMany] expects it to point back at {$ownerClass}.\n".
                "  Why: both sides of a relation need to agree on which model they're relating to.\n".
                "  Fix: change {$relatedClass}::\${$foreignKey}'s #[BelongsTo] to target {$ownerClass}::class, or check whether {$ownerProperty} was meant to point at {$actualTarget} instead."
            );
        }

        /**
         * @param string $relatedClass the class carrying the #[BelongsTo] property
         * @param string $foreignKey the property name
         * @param string $actualType the property's actual declared type
         * @return self
         */
        public static function InvalidBelongsToPropertyType(string $relatedClass, string $foreignKey, string $actualType): self
        {
            return new self(
                "#[BelongsTo] on {$relatedClass}::\${$foreignKey} is on a property typed '{$actualType}'.\n".
                "  Why: a foreign key column always stores another model's business id, which is a plain string (matching the VARCHAR(64) every {table}id column already uses).\n".
                "  Fix: change {$relatedClass}::\${$foreignKey}'s type to 'string'."
            );
        }

        /**
         * @param string $relatedClass the class carrying the #[BelongsTo] property
         * @param string $foreignKey the property name
         * @return self
         */
        public static function BelongsToMarkedUnique(string $relatedClass, string $foreignKey): self
        {
            return new self(
                "{$relatedClass}::\${$foreignKey} is both #[BelongsTo] and marked unique.\n".
                "  Why: a unique foreign key column would cap this relation at exactly one child per parent, since no two rows could share the same value - almost certainly not what a HasMany relation is meant to do.\n".
                "  Fix: remove '{$foreignKey}' from {$relatedClass}'s \$unique list, or reconsider whether this should be a HasMany relation at all if one-child-per-parent really is intended."
            );
        }

        /**
         * @param string $class the model the relation was looked up on
         * @param string $relation the relation name that wasn't found
         * @param string $context where the lookup happened, e.g. "With()", "Without()", "hydrate()"
         * @return self
         */
        public static function UnknownRelation(string $class, string $relation, string $context): self
        {
            $known = \Wixnit\Data\RelationMap::names($class);
            $suggestion = (count($known) > 0)
                ? "Declared relations on {$class} are: " . implode(", ", $known) . "."
                : "{$class} has no #[HasMany] relations declared at all.";

            return new self(
                "{$context} referenced unknown relation '{$relation}' on {$class}.\n".
                "  Why: {$context} can only name a property carrying #[HasMany].\n".
                "  Suggestion: {$suggestion}"
            );
        }
    }
