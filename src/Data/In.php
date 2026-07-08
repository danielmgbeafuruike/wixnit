<?php

    namespace Wixnit\Data;

    /**
     * Filter operator representing a "field IN (...)" condition.
     * Usage: new Filter(['status' => new In('active', 'pending')])
     */
    class In
    {
        public array $value = [];

        function __construct(...$values)
        {
            //allow a single array argument to be passed as well as a variadic list
            if((count($values) == 1) && is_array($values[0]))
            {
                $this->value = $values[0];
            }
            else
            {
                $this->value = $values;
            }
        }
    }
