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
    }
