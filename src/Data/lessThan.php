<?php

    namespace Wixnit\Data;

    use Wixnit\Utilities\Convert;

    class LessThan
    {
        public $value = 0;
        public bool $orEqualTo = false;

        function __construct($value=0, $orEqualTo=false)
        {
            $this->value = is_numeric($value) ? doubleval($value) : $value;
            $this->orEqualTo = Convert::ToBool($orEqualTo);
        }
    }