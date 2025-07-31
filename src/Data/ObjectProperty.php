<?php

    namespace Wixnit\Data;

    class ObjectProperty
    {
        public string $name = "";
        public string $type = "null";
        public string $baseName = "";
        public bool $isArray = false;
        public bool $isUnique = false;
        public bool $isLong = false;
        public object $value;
        public bool $isHidden = false;
    }