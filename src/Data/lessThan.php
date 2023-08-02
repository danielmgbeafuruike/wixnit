<?php

    namespace Wixnit\Data;

    use Wixnit\Utilities\Convert;

    class lessThan
    {
        public $Value = 0;
        public bool $orEqualTo = false;

        function __construct($value=0, $orEqualTo=false)
        {
            $this->Value = is_numeric($value) ? doubleval($value) : $value;
            $this->orEqualTo = Convert::ToBool($orEqualTo);
        }
    }