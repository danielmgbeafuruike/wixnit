<?php

    namespace Wixnit\Data;

    class groupBy
    {
        public $Value = "";

        function __construct(string $field)
        {
            $this->Value = $field;
        }
    }