<?php

    namespace Wixnit\Data;

    class ObjectProperty
    {
        public string $Name = "";
        public string $Type = "null";
        public string $baseName = "";
        public bool $IsArray = false;
        public bool $IsUnique = false;
        public bool $IsLong = false;
        public object $Value;
        public bool $IsHidden = false;
    }