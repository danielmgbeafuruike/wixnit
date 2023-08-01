<?php

    namespace wixnit\Data;

    class fieldName
    {
        public string $Name = "";

        function __construct(string $name)
        {
            $this->Name = $name;
        }
    }