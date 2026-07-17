<?php

    namespace Wixnit\Interfaces;

    /**
     * A masking strategy for Masked - a single static method turning a real value into
     * its display-safe, partially-obscured form.
     *
     * Usage:
     *   class CreditCardMasker implements Masker
     *   {
     *       public static function mask(string $value): string
     *       {
     *           return str_repeat('*', max(strlen($value) - 4, 0)) . substr($value, -4);
     *       }
     *   }
     */
    interface IMasker
    {
        /**
         * @param string $value the real, unmasked value
         * @return string the value as it should be displayed (echo, JSON, etc.)
         */
        public static function mask(string $value): string;
    }
