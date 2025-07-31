<?php
    namespace Wixnit\Utilities;

    use Wixnit\Enum\CharacterType;

    class Random
    {
        /**
         * generate random characters
         * @param mixed $length
         * @param \Wixnit\Enum\CharacterType $type
         * @return string
         */
        public static function Characters($length = 8, CharacterType $type = CharacterType::ALPHANUMERIC): string
        {
            $alphabets = ["a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y"];

            $ret = "";
            for($i = 0; $i < $length; $i++)
            {
                if($type == CharacterType::ALPHANUMERIC)
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
                if($type == CharacterType::NUMERIC)
                {
                    $ret .= mt_rand(0, 9);
                }
                if($type == CharacterType::ALPHABETIC)
                {
                    $ret .= Random::Pick($alphabets);
                }
            }
            return $ret;
        }

        /**
         * pick a random item from an array of items
         * @param mixed $list
         * @return mixed
         */
        public static function Pick($list): mixed
        {
            if(is_array($list))
            {
                return $list[mt_rand(0, (count($list) - 1))];
            }
            return  $list;
        }
    }
    