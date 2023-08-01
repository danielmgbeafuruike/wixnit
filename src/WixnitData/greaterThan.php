<?php

    namespace wixnit\Data;

    use wixnit\Utilities\Convert;

    class greaterThan
    {
        public $Value = 0;
        public bool $orEqualTo = false;

        function __construct($value=0, $orEqualTo=false)
        {
            $this->Value = is_numeric($value) ? doubleval($value) : $value;
            $this->orEqualTo = Convert::ToBool($orEqualTo);
        }
    }