<?php

    namespace Wixnit\Data;
    class notEqual
    {
        public ?array $Value = null;

        function __construct()
        {
            $this->Value = func_get_args();
        }
    }