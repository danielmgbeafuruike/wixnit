<?php

    namespace Wixnit\Data;

    use Wixnit\Interfaces\IMasker;

    /**
     * Keeps the first character of the local part and the whole domain, masks the rest
     * of the local part - "ada@example.com" -> "a**@example.com". Falls back to
     * GenericMasker for a value that doesn't contain "@" at all.
     */
    class EmailMasker implements IMasker
    {
        public static function mask(string $value): string
        {
            $atPosition = strpos($value, "@");

            if($atPosition === false)
            {
                return GenericMasker::mask($value);
            }

            $local = substr($value, 0, $atPosition);
            $domain = substr($value, $atPosition + 1);

            $maskedLocal = (strlen($local) > 0)
                ? ($local[0] . str_repeat("*", strlen($local) - 1))
                : "";

            return $maskedLocal . "@" . $domain;
        }
    }
