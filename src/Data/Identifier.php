<?php

    namespace Wixnit\Data;

    use Wixnit\Exception\DatabaseException;

    /**
     * Guards raw SQL identifiers (table/column/relation names) that get concatenated
     * directly into query strings. Values passed through Filter, Search, aggregates
     * etc. are always bound as prepared-statement parameters - this class protects
     * the *names* (columns, tables, relations) that can't be bound the same way.
     */
    class Identifier
    {
        /**
         * Checks that an identifier only contains safe characters, optionally in
         * "relation.field" form (used for filtering/ordering across an auto-joined
         * property). Throws if the identifier looks unsafe.
         * @param string $identifier
         * @return string the same identifier, once validated
         */
        public static function assertSafe(string $identifier): string
        {
            if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $identifier))
            {
                throw DatabaseException::UnsafeIdentifier($identifier);
            }
            return $identifier;
        }

        /**
         * Checks an identifier against a known list of allowed field names (case-insensitive).
         * The relation-qualified part ("relation.field"), if present, is only checked for
         * safe characters since the related model's own fields aren't known at this point.
         * @param string $identifier
         * @param array $knownFields
         * @return string the same identifier, once validated
         */
        public static function assertKnownField(string $identifier, array $knownFields): string
        {
            self::assertSafe($identifier);

            $parts = explode(".", $identifier);
            $field = strtolower(end($parts));

            //relation-qualified fields ("wallet.amount") can't be checked against $knownFields
            //(that array only describes the base table), so only the character-safety check applies.
            if(count($parts) > 1)
            {
                return $identifier;
            }

            $lowered = array_map('strtolower', $knownFields);

            if(!in_array($field, $lowered))
            {
                throw DatabaseException::InvalidFieldName($identifier, $knownFields);
            }
            return $identifier;
        }
    }
