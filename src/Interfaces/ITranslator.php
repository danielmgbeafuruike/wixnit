<?php

    namespace Wixnit\Interfaces;

    Interface ITranslator
    {
        public function getText(string $content): string;
        public function translateBack(string $content): string;
    }