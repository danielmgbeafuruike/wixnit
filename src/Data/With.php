<?php

    namespace Wixnit\Data;

    /**
     * Forces a #[HasMany] relation to load eagerly for this query, batched across the
     * whole page of results - the counterpart to Without(). Most useful on a
     * HasManyCollection-typed relation (lazy by default), but harmless (a no-op) if used
     * on an array-typed relation that's already eager by default.
     *
     * Usage: User::Get(new With('reviews'))
     */
    class With
    {
        public array $relations = [];

        function __construct(string ...$relations)
        {
            $this->relations = $relations;
        }
    }
