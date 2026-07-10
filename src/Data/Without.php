<?php

    namespace Wixnit\Data;

    /**
     * Defers a #[HasMany] relation that would normally load eagerly for this query - the
     * counterpart to With(). Most useful on an array-typed relation (eager by default);
     * harmless (a no-op) if used on a HasManyCollection-typed relation that's already
     * lazy by default.
     *
     * Usage: User::Get(new Without('orders'))
     */
    class Without
    {
        public array $relations = [];

        function __construct(string ...$relations)
        {
            $this->relations = $relations;
        }
    }
