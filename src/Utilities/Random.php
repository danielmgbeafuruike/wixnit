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
            $alphabets = ["a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z"];

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

        /**
         * generate a random integer between min and max (inclusive)
         * @param int $min
         * @param int $max
         * @return int
         */
        public static function Integer(int $min = 0, int $max = PHP_INT_MAX): int
        {
            return random_int($min, $max);
        }

        /**
         * generate a cryptographically random, RFC 4122 version 4 UUID
         * @return string
         */
        public static function UUID(): string
        {
            $data = random_bytes(16);

            // set version to 0100
            $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
            // set bits 6-7 to 10
            $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }

        /**
         * generate a short, URL-safe unique token (not cryptographically ordered, good for slugs/ids)
         * @param int $length
         * @return string
         */
        public static function Token(int $length = 21): string
        {
            $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
            $ret = "";
            $max = strlen($alphabet) - 1;

            for($i = 0; $i < $length; $i++)
            {
                $ret .= $alphabet[random_int(0, $max)];
            }
            return $ret;
        }

        /**
         * generate a classic Windows-style GUID, e.g. "{3F2504E0-4F89-11D3-9A0C-0305E82C3301}".
         * Functionally the same random UUID v4 as UUID(), just formatted with braces and uppercased.
         * @return string
         */
        public static function Guid(): string
        {
            return "{".strtoupper(Random::UUID())."}";
        }

        /**
         * generate a random password containing a mix of lowercase, uppercase, digits and symbols.
         * Guarantees at least one character from each category (when the length allows it).
         * @param int $length
         * @param bool $symbols include symbol characters in the pool
         * @return string
         */
        public static function Password(int $length = 16, bool $symbols = true): string
        {
            $lower = "abcdefghijklmnopqrstuvwxyz";
            $upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            $digits = "0123456789";
            $symbolChars = "!@#$%^&*()-_=+[]{}";

            $pools = [$lower, $upper, $digits];
            if($symbols)
            {
                $pools[] = $symbolChars;
            }

            $all = implode("", $pools);
            $ret = [];

            //guarantee at least one character from each pool, so short passwords still meet complexity rules
            for($i = 0; $i < count($pools); $i++)
            {
                if(count($ret) < $length)
                {
                    $ret[] = $pools[$i][random_int(0, strlen($pools[$i]) - 1)];
                }
            }

            for($i = count($ret); $i < $length; $i++)
            {
                $ret[] = $all[random_int(0, strlen($all) - 1)];
            }

            shuffle($ret);
            return implode("", array_slice($ret, 0, $length));
        }

        /**
         * generate a numeric one-time-passcode of the given number of digits, e.g. "042817".
         * Returned as a string so leading zeros are preserved.
         * @param int $digits
         * @return string
         */
        public static function Otp(int $digits = 6): string
        {
            $ret = "";
            for($i = 0; $i < $digits; $i++)
            {
                $ret .= (string) random_int(0, 9);
            }
            return $ret;
        }

        /**
         * generate a random hexadecimal string of the given length (characters, not bytes)
         * @param int $length
         * @return string
         */
        public static function Hex(int $length = 32): string
        {
            //random_bytes gives us 2 hex characters per byte, so round up and trim to the exact length asked for
            $bytes = (int) ceil($length / 2);
            return substr(bin2hex(random_bytes($bytes)), 0, $length);
        }

        /**
         * generate a random integer between min and max (inclusive). Alias of Integer() under the
         * shorter name requested by the "int()" convention used elsewhere in this library.
         * @param int $min
         * @param int $max
         * @return int
         */
        public static function Int(int $min = 0, int $max = PHP_INT_MAX): int
        {
            return Random::Integer($min, $max);
        }

        /**
         * generate a random float between min and max (inclusive of min, exclusive of max)
         * @param float $min
         * @param float $max
         * @return float
         */
        public static function Float(float $min = 0.0, float $max = 1.0): float
        {
            //random_int works on the full precision range to avoid mt_rand()'s low-bit bias
            $rand = random_int(0, PHP_INT_MAX) / PHP_INT_MAX;
            return $min + ($rand * ($max - $min));
        }

        /**
         * generate a random hex color string, e.g. "#3fae12"
         * @return string
         */
        public static function Color(): string
        {
            return sprintf("#%06x", random_int(0, 0xFFFFFF));
        }

        /**
         * return a shuffled copy of an array without mutating the original (unlike PHP's shuffle())
         * @param array $array
         * @return array
         */
        public static function Shuffle(array $array): array
        {
            $copy = $array;
            shuffle($copy);
            return $copy;
        }

        /**
         * pick a random item from an array, biased by weight.
         * $weightedItems can either be an associative array of [item => weight], or a
         * list of ["item" => mixed, "weight" => number] arrays/objects.
         *
         * e.g. Random::WeightedPick(["gold" => 1, "silver" => 5, "bronze" => 20])
         *
         * @param array $weightedItems
         * @return mixed
         */
        public static function WeightedPick(array $weightedItems): mixed
        {
            $items = [];
            $weights = [];

            $keys = array_keys($weightedItems);
            for($i = 0; $i < count($keys); $i++)
            {
                $entry = $weightedItems[$keys[$i]];

                if(is_array($entry) && isset($entry["item"]) && isset($entry["weight"]))
                {
                    $items[] = $entry["item"];
                    $weights[] = $entry["weight"];
                }
                else if(is_object($entry) && isset($entry->item) && isset($entry->weight))
                {
                    $items[] = $entry->item;
                    $weights[] = $entry->weight;
                }
                else
                {
                    $items[] = $keys[$i];
                    $weights[] = $entry;
                }
            }

            $total = array_sum($weights);
            if($total <= 0)
            {
                return Random::Pick($items);
            }

            $point = Random::Float(0, $total);
            $cumulative = 0;

            for($i = 0; $i < count($items); $i++)
            {
                $cumulative += $weights[$i];
                if($point <= $cumulative)
                {
                    return $items[$i];
                }
            }
            return $items[count($items) - 1];
        }
    }