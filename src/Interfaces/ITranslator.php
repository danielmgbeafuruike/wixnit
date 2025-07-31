<?php

    namespace Wixnit\Interfaces;

    /**
     * Objects implementing ITranslator can be used for language translations
     */
    Interface ITranslator
    {
        /**
         * get the translation for the provided content
         * @param string $content
         * @return string
         */
        public function translate(string $content): string;

        /**
         * reverse the translation to it's original state
         * @param string $content
         * @return string
         */
        public function translateBack(string $content): string;
    }