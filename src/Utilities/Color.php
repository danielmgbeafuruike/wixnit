<?php

    namespace Wixnit\Utilities;

    use stdClass;

    class Color
    {
        public int $Red = 0;
        public int $Green = 0;
        public int $Blue = 0;
        public int $Opacity = 0;
        public int $Alpha = 0;

        public string $Hex = "";

        function __construct($arg=null)
        {
            if(is_object($arg))
            {
                $this->Red = isset($arg->Red) ? Convert::ToInt($arg->Red) : 0;
                $this->Green = isset($arg->Green) ? Convert::ToInt($arg->Green) : 0;
                $this->Blue = isset($arg->Blue) ? Convert::ToInt($arg->Blue) : 0;
                $this->Opacity = isset($arg->Opacity) ? Convert::ToInt($arg->Opacity) : 0;
                $this->Alpha = isset($arg->Alpha) ? Convert::ToInt($arg->Alpha) : 0;
            }
            else if(is_string($arg))
            {
                if(in_array("#", str_split($arg)))
                {
                    $this->Hex = $arg;
                }
                else
                {
                    try{
                        $obj = json_decode($arg);

                        $this->Red = isset($obj->Red) ? Convert::ToInt($obj->Red) : 0;
                        $this->Green = isset($obj->Green) ? Convert::ToInt($obj->Green) : 0;
                        $this->Blue = isset($obj->Blue) ? Convert::ToInt($obj->Blue) : 0;
                        $this->Opacity = isset($obj->Opacity) ? Convert::ToInt($obj->Opacity) : 0;
                        $this->Alpha = isset($obj->Alpha) ? Convert::ToInt($obj->Alpha) : 0;
                    }
                    catch (\Exception $exception) {}
                }
            }
        }

        public static function From(int $red, int $green, int $blue, int $opacity): Color
        {
            $ret = new Color();
            $ret->Red = $red;
            $ret->Green = $green;
            $ret->Blue = $blue;
            $ret->Opacity = $opacity;
            return $ret;
        }

        public function ToHex($r, $g, $b, $a = 1) {
            // Ensure that the alpha value is between 0 and 1
            $a = max(0, min(1, $a));
        
            // Convert the color values to hexadecimal
            $red = str_pad(dechex($r), 2, "0", STR_PAD_LEFT);
            $green = str_pad(dechex($g), 2, "0", STR_PAD_LEFT);
            $blue = str_pad(dechex($b), 2, "0", STR_PAD_LEFT);
            $alpha = str_pad(dechex(round($a * 255)), 2, "0", STR_PAD_LEFT);
        
            return "#" . $red . $green . $blue . $alpha;
        }


        public function ToAHex($r, $g, $b, $a = 1) {
            // Ensure that the alpha value is between 0 and 1
            $a = max(0, min(1, $a));
        
            // Convert the color values to hexadecimal
            $red = str_pad(dechex($r), 2, "0", STR_PAD_LEFT);
            $green = str_pad(dechex($g), 2, "0", STR_PAD_LEFT);
            $blue = str_pad(dechex($b), 2, "0", STR_PAD_LEFT);
            $alpha = str_pad(dechex(round($a * 255)), 2, "0", STR_PAD_LEFT);
        
            return "#" . $alpha . $red . $green . $blue;
        }


        public static function FromHex($hex): ?Color 
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
        
            return Color::From($r, $g, $b, $a);
        }

        public static function FromAHex($hex): ?Color 
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
                $a = hexdec(str_repeat(substr($hex, 0, 1), 2));
                $r = hexdec(str_repeat(substr($hex, 1, 1), 2));
                $g = hexdec(str_repeat(substr($hex, 2, 1), 2));
                $b = hexdec(str_repeat(substr($hex, 3, 1), 2)) / 255;
            } elseif (strlen($hex) == 6) {
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $a = 1; // Default alpha
            } elseif (strlen($hex) == 8) {
                $a = hexdec(substr($hex, 0, 2));
                $r = hexdec(substr($hex, 2, 2));
                $g = hexdec(substr($hex, 4, 2));
                $b = hexdec(substr($hex, 6, 2)) / 255;
            } else {
                // Invalid hex color
                return null;
            }
        
            return Color::From($r, $g, $b, $a);
        }

        function toString()
        {
            $ret = new stdClass();
            $ret->Red = $this->Red;
            $ret->Green = $this->Green;
            $ret->Blue = $this->Blue;
            $ret->Opacity = $this->Opacity;
            $ret->Alpha = $this->Alpha;
            $ret->Hex = $this->Hex;

            return json_encode($ret);
        }
    }