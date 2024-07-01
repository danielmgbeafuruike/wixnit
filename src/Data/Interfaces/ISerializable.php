<?php

    namespace Wixnit\Data\Interfaces;

    use Wixnit\Data\DBFieldType;

    interface ISerializable
    {
        public function _DBType(): DBFieldType;

        public function _Serialize();

        public  function _Deserialize($data);
    }