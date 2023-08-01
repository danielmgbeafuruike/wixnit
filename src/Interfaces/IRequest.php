<?php

    namespace wixnit\Interfaces;

    interface IRequest
    {
        public function AddParameter($name_valuePair, $value=null);

        public function Execute();

        public function AddRange($name_valuePair_array);

        public function GetURL();
    }