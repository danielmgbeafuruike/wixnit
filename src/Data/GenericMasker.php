<?php

    namespace Wixnit\Data;

    use Wixnit\Interfaces\IMasker;

    /**
     * The default Masker for a Masked property with no #[Mask(...)] attribute - keeps
     * the first and last character, masks everything between. Too short (2 characters
     * or fewer) to safely reveal both ends without effectively showing the whole value,
     * so it masks fully instead.
     */
    class GenericMasker implements IMasker
    {
        public static function mask(string $value): string
        {
            $length = strlen($value);

            if($length <= 2)
            {
                return str_repeat("*", $length);
            }
            return $value[0] . str_repeat("*", $length - 2) . $value[$length - 1];
        }
    }
