<?php

    namespace Wixnit\Data;

    use Wixnit\Interfaces\IMasker;

    /**
     * Keeps the last 4 characters, masks everything before them -
     * "+2348012345678" -> "**********5678". Too short (4 characters or fewer) to
     * safely reveal any of it without showing the whole value, so it masks fully instead.
     */
    class PhoneMasker implements IMasker
    {
        public static function mask(string $value): string
        {
            $length = strlen($value);

            if($length <= 4)
            {
                return str_repeat("*", $length);
            }
            return str_repeat("*", $length - 4) . substr($value, -4);
        }
    }
