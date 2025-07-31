<?php

    namespace Wixnit\Data;
    class NotEqual
    {
        public ?array $value = null;

        function __construct()
        {
            $this->value = func_get_args();
        }
    }