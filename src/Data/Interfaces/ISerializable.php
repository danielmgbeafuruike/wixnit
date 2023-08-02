<?php

    namespace Wixnit\Data\Interfaces;

    interface ISerializable
    {
        public function _DBType(): string;

        public function _Serialize();

        public  function _Deserialize($data);
    }