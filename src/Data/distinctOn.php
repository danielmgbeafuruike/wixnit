<?php

    namespace Wixnit\Data;

    class distinctOn
    {
        public array $Values = [];

        public function __construct(array $vals =[])
        {
            $this->Values = $vals;
        }

        public function getValue()
        {
            return $this->Values;
        }
    }