<?php

    namespace Wixnit\Interfaces;

    interface IResponse extends IBase
    {
        public function GetStatus();

        public function ToBool();

        public function ToInt();

        public function ToString();

        public function GetType();
    }