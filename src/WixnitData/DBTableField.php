<?php

    namespace Wixnit\Data;

    class DBTableField
    {
        public string $Name = "";

        public string $Type = "";
        public int $Length = 0;
        public bool $IsUnique = false;
        public bool $IsNull = false;
        public bool $IsPrimary = false;
        public bool $IsIndex = false;
        public bool $AutoIncrement = false;
        public $Default;

        public  function getDBType(): string
        {
            if(($this->Type == DBFieldType::Varchar) || ($this->Type == DBFieldType::Char))
            {
                return  $this->Type."(".$this->Length.")";
            }
            return $this->Type;
        }
    }