<?php

    namespace Wixnit\Interfaces;

    use Wixnit\Enum\DBFieldType;

    interface ISerializable
    {
        public function _dbType(): DBFieldType;

        public function _serialize();

        public  function _deserialize($data);
    }