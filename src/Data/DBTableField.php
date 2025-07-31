<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\DBFieldType;

    class DBTableField
    {
        public string $name = "";
        public DBFieldType $type = DBFieldType::VARCHAR;
        public int $length = 0;
        public bool $isUnique = false;
        public bool $isNull = false;
        public bool $isPrimary = false;
        public bool $isIndex = false;
        public bool $autoIncrement = false;
        public $default;

        /**
         * Get the field type as a string
         * @return string
         */
        public  function getDBType(): string
        {
            if(($this->type == DBFieldType::VARCHAR) || ($this->type == DBFieldType::CHAR))
            {
                return  $this->type->value."(".$this->length.")";
            }
            return $this->type->value;
        }
    }