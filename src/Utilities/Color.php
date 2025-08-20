<?php

    namespace Wixnit\Utilities;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;

    class Color implements ISerializable
    {
        public int $red = 0;
        public int $green = 0;
        public int $blue = 0;
        public float $opacity = 1;

        function __construct(mixed $arg=null)
        {
            $this->init($arg);
        }

        /**
         * hydrate the object from parameter
         * @param mixed $arg
         * @return void
         */
        private function init($arg=null): void
        {
            if(is_object($arg))
            {
                $this->red = isset($arg->red) ? Convert::ToInt($arg->red) : 0;
                $this->green = isset($arg->green) ? Convert::ToInt($arg->green) : 0;
                $this->blue = isset($arg->blue) ? Convert::ToInt($arg->blue) : 0;
                $this->opacity = isset($arg->opacity) ? Convert::ToInt($arg->opacity) : 0;
            }
            else if(is_string($arg))
            {
                if(strpos($arg, "#", ) !== false)
                {
                    $col = Color::FromHex($arg);

                    $this->red = $col->red;
                    $this->green = $col->green;
                    $this->blue = $col->blue;
                    $this->opacity = $col->opacity;
                }
                else
                {
                    try{
                        $obj = json_decode($arg);

                        $this->red = isset($obj->red) ? Convert::ToInt($obj->red) : 0;
                        $this->green = isset($obj->green) ? Convert::ToInt($obj->green) : 0;
                        $this->blue = isset($obj->blue) ? Convert::ToInt($obj->blue) : 0;
                        $this->opacity = isset($obj->opacity) ? Convert::ToInt($obj->opacity) : 0;
                        $this->alpha = isset($obj->alpha) ? Convert::ToInt($obj->alpha) : 0;
                    }
                    catch (\Exception $exception) {}
                }
            }
        }

        /**
         * convert the color to hex string
         * @return string
         */
        public function toHex(): string 
        {
            // Ensure that the opacity value is between 0 and 1
            $o = max(0, min(1, $this->opacity));
        
            // Convert the color values to hexadecimal
            $red = str_pad(dechex($this->red), 2, "0", STR_PAD_LEFT);
            $green = str_pad(dechex($this->green), 2, "0", STR_PAD_LEFT);
            $blue = str_pad(dechex($this->blue), 2, "0", STR_PAD_LEFT);
            $opacity = str_pad(dechex(round($o * 255)), 2, "0", STR_PAD_LEFT);
        
            return "#" . $red . $green . $blue . $opacity;
        }

        /**
         * convert the color to ahex string
         * @return string
         */
        public function toAHex(): string 
        {
            // Ensure that the alpha value is between 0 and 1
            $o = max(0, min(1, $this->opacity));
        
            // Convert the color values to hexadecimal
            $red = str_pad(dechex($this->red), 2, "0", STR_PAD_LEFT);
            $green = str_pad(dechex($this->green), 2, "0", STR_PAD_LEFT);
            $blue = str_pad(dechex($this->blue), 2, "0", STR_PAD_LEFT);
            $opacity = str_pad(dechex(round($o * 255)), 2, "0", STR_PAD_LEFT);
        
            return "#" . $opacity . $red . $green . $blue;
        }

        /**
         * Get a cloned color object with opacity set opacity
         * @param float $opacity
         * @return Color
         */
        public function withOpacity(float $opacity): Color
        {
            return Color::FromRGBO($this->red, $this->green, $this->blue, $opacity);
        }


        

        #region static method

        /**
         * create Color object from red, green & blue values
         * @param mixed $name
         */
        public static function FromRGBO(int $red, int $green, int $blue, float $opacity=1): Color
        {
            $ret = new Color();
            $ret->red = $red;
            $ret->green = $green;
            $ret->blue = $blue;
            $ret->opacity = $opacity;
            return $ret;
        }

        /**
         * create Color object from ahex string
         * @param string $hex
         * @return Color | null
         */
        public static function FromHex(string $hex): ?Color 
        {
            // Remove the hash if present
            $hex = ltrim($hex, '#');
        
            // Handle short (3 or 4 character) and full (6 or 8 character) hex codes
            if (strlen($hex) == 3) {
                $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
                $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
                $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
                $a = 1; // Default alpha
            } elseif (strlen($hex) == 4) {
                $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
                $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
                $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
                $a = hexdec(str_repeat(substr($hex, 3, 1), 2)) / 255;
            } elseif (strlen($hex) == 6) {
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $a = 1; // Default alpha
            } elseif (strlen($hex) == 8) {
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $a = hexdec(substr($hex, 6, 2)) / 255;
            } else {
                // Invalid hex color
                return null;
            }
        
            return Color::FromRGBO($r, $g, $b, $a);
        }

        /**
         * create Color object from ahex string
         * @param string $ahex
         * @return Color | null
         */
        public static function FromAHex(string $ahex): ?Color 
        {
            // Remove the hash if present
            $ahex = ltrim($ahex, '#');
        
            // Handle short (3 or 4 character) and full (6 or 8 character) hex codes
            if (strlen($ahex) == 3) {
                $r = hexdec(str_repeat(substr($ahex, 0, 1), 2));
                $g = hexdec(str_repeat(substr($ahex, 1, 1), 2));
                $b = hexdec(str_repeat(substr($ahex, 2, 1), 2));
                $a = 1; // Default alpha
            } elseif (strlen($ahex) == 4) {
                $a = hexdec(str_repeat(substr($ahex, 0, 1), 2));
                $r = hexdec(str_repeat(substr($ahex, 1, 1), 2));
                $g = hexdec(str_repeat(substr($ahex, 2, 1), 2));
                $b = hexdec(str_repeat(substr($ahex, 3, 1), 2)) / 255;
            } elseif (strlen($ahex) == 6) {
                $r = hexdec(substr($ahex, 0, 2));
                $g = hexdec(substr($ahex, 2, 2));
                $b = hexdec(substr($ahex, 4, 2));
                $a = 1; // Default alpha
            } elseif (strlen($ahex) == 8) {
                $a = hexdec(substr($ahex, 0, 2));
                $r = hexdec(substr($ahex, 2, 2));
                $g = hexdec(substr($ahex, 4, 2));
                $b = hexdec(substr($ahex, 6, 2)) / 255;
            } else {
                // Invalid hex color
                return null;
            }
        
            return Color::FromRGBO($r, $g, $b, $a);
        }
        #endregion


        #region implement ISerializable methods

        /**
         * get db field type for creating the appropriate db field type for saving the class to db
         * @return DBFieldType
         */
        public function _dbType(): DBFieldType
        {
            return DBFieldType::VARCHAR;
        }

        /**
         * prepare the object for saving to db
         * @return int
         */
        public function _serialize(): string
        {
            return $this->toHex();
        }

        /**
         * re-populate object from data rceived from db
         * @param mixed $data
         * @return void
         */
        public function _deserialize($data): void
        {
            $this->init($data);
        }
        #endregion
    }