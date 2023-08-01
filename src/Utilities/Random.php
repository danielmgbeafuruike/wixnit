<?php
    namespace Wixnit\Utilities;

    class Random
    {
        const Numeric = "numeric";
        const Alphanumeric = "alphanumeric";
        const Alphabetic = "alphabetic";
        public static function Generate($length = 8, $type="alphanumeric"): string
        {
            $alphabets = ["a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y"];

            $ret = "";
            for($i = 0; $i < $length; $i++)
            {
                if(strtolower($type) == "alphanumeric")
                {
                    $ch = mt_rand(0, 2);
                    if($ch == 0)
                    {
                        $ret .= Random::Pick($alphabets);
                    }
                    else if($ch == 1)
                    {
                        $ret .= Random::Pick($alphabets);
                    }
                    else
                    {
                        $ret .= mt_rand(1, 9);
                    }
                }
                if(strtolower($type) == "numeric")
                {
                    $ret .= mt_rand(0, 9);
                }
                if(strtolower($type) == "alphabetic")
                {
                    $ret .= Random::Pick($alphabets);
                }
            }
            return $ret;
        }

        public static function Pick($list)
        {
            if(is_array($list))
            {
                return $list[mt_rand(0, (count($list) - 1))];
            }
            return  $list;
        }
    }