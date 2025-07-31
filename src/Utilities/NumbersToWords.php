<?php

    namespace Wixnit\Utilities;

    class NumbersToWords
    {
        private $value = 0;

        function __construct(int $value = 0)
        {
            $this->value= $value;
        }

        /**
         * convert number to words
         * @return string
         */
        public function words(): string
        {
            $ret = "";

            $rem = $this->value;

            if($rem > 999999999999999999)
            {
                return "uncountable";
            }
            if(($rem > 999999999999999) && ($rem <= 999999999999999999))
            {
                if(strlen(strval($this->trimEnd($rem, 15))) == 3)
                {
                    $ret .= $this->processHundred($this->trimEnd($rem,15));
                }
                else
                {
                    $ret .= $this->simpleNumber($this->trimEnd($rem,15));
                }
                $ret .= " quadrillion";
                $rem = $this->trimStart($rem, (strlen(strval($rem)) - 15));
            }
            if(($rem > 999999999999) && ($rem <= 999999999999999))
            {
                if($ret != ""){ $ret .= ", ";}

                if(strlen(strval($this->trimEnd($rem, 12))) == 3)
                {
                    $ret .= $this->processHundred($this->trimEnd($rem,12));
                }
                else
                {
                    $ret .= $this->simpleNumber($this->trimEnd($rem,12));
                }
                $ret .= " trillion";
                $rem = $this->trimStart($rem, (strlen(strval($rem)) - 12));
            }
            if(($rem > 999999999) && ($rem <= 999999999999))
            {
                if($ret != ""){ $ret .= ", ";}

                if(strlen(strval($this->trimEnd($rem, 9))) == 3)
                {
                    $ret .= $this->processHundred($this->trimEnd($rem,9));
                }
                else
                {
                    $ret .= $this->simpleNumber($this->trimEnd($rem,9));
                }
                $ret .= " billion";
                $rem = $this->trimStart($rem, (strlen(strval($rem)) - 9));
            }
            if(($rem > 999999) && ($rem <= 999999999))
            {
                if($ret != ""){ $ret .= ", ";}

                if(strlen(strval($this->trimEnd($rem, 6))) == 3)
                {
                    $ret .= $this->processHundred($this->trimEnd($rem, 6));
                }
                else
                {
                    $ret .= $this->simpleNumber($this->trimEnd($rem,6));
                }
                $ret .= " million";
                $rem = $this->trimStart($rem, (strlen(strval($rem)) - 6));
            }
            if(($rem > 999) && ($rem <= 999999))
            {
                if($ret != ""){ $ret .= ", ";}

                if(strlen(strval($this->trimEnd($rem, 3))) == 3)
                {
                    $ret .= $this->processHundred($this->trimEnd($rem,3));
                }
                else
                {
                    $ret .= $this->simpleNumber($this->trimEnd($rem,3));
                }
                $ret .= " thousand";
                $rem = $this->trimStart($rem, (strlen(strval($rem)) - 3));
            }
            if(($rem >= 100) && ($rem <= 999))
            {
                if($ret != ""){ $ret .= ", ";}

                $ret .= $this->processHundred($rem);

                $rem = null;
            }
            if(($rem != null) && ($rem < 100))
            {
                if($ret != ""){ $ret .= ", ";}

                $ret .= $this->simpleNumber($rem);
            }
            return trim($ret);
        }

        #region private methods

        /**
         * process numbers under 100
         * @param int $num
         * @return string
         */
        private function simpleNumber(int $num): string
        {
            switch (Convert::ToInt($num))
            {
                case 0:
                    return "zero";
                case 1:
                    return "one";
                case 2:
                    return "two";
                case 3:
                    return "three";
                case 4:
                    return "four";
                case 5:
                    return "five";
                case 6:
                    return "six";
                case 7:
                    return "seven";
                case 8:
                    return "eight";
                case 9:
                    return "nine";
                case 10:
                    return "ten";
                case 11:
                    return "eleven";
                case 12:
                    return "twelve";
                case 13:
                    return "thirteen";
                case 14:
                    return "fourteen";
                case 15:
                    return "fifteen";
                case 16:
                    return "sixteen";
                case 17:
                    return "seventeen";
                case 18:
                    return "eighteen";
                case 19:
                    return "nineteen";
                case 20:
                    return "twenty";
                case 21:
                    return "twenty one";
                case 22:
                    return "twenty two";
                case 23:
                    return "twenty three";
                case 24:
                    return "twenty four";
                case 25:
                    return "twenty five";
                case 26:
                    return "twenty six";
                case 27:
                    return "twenty seven";
                case 28:
                    return "twenty eight";
                case 29:
                    return "twenty nine";
                case 30:
                    return "thirty";
                case 31:
                    return "thirty one";
                case 32:
                    return "thirty two";
                case 33:
                    return "thirty three";
                case 34:
                    return "thirty four";
                case 35:
                    return "thirty five";
                case 36:
                    return "thirty six";
                case 37:
                    return "thirty seven";
                case 38:
                    return "thirty eight";
                case 39:
                    return "thirty nine";
                case 40:
                    return "fourty";
                case 41:
                    return "fourty one";
                case 42:
                    return "fourty two";
                case 43:
                    return "fourty three";
                case 44:
                    return "fourty four";
                case 45:
                    return "fourty five";
                case 46:
                    return "fourty six";
                case 47:
                    return "fourty seven";
                case 48:
                    return "fourty eight";
                case 49:
                    return "fourty nine";
                case 50:
                    return "fifty";
                case 51:
                    return "fifty one";
                case 52:
                    return "fifty two";
                case 53:
                    return "fifty two";
                case 54:
                    return "fifty three";
                case 55:
                    return "fifty five";
                case 56:
                    return "fifty six";
                case 57:
                    return "fifty seven";
                case 58:
                    return "fifty eight";
                case 59:
                    return "fifty nine";
                case 60:
                    return "sixty";
                case 61:
                    return "sixty one";
                case 62:
                    return "sixty two";
                case 63:
                    return "sixty three";
                case 64:
                    return "sixty four";
                case 65:
                    return "sixty five";
                case 66:
                    return "sixty six";
                case 67:
                    return "sixty seven";
                case 68:
                    return "sixty eight";
                case 69:
                    return "sixty nine";
                case 70:
                    return "seventy";
                case 71:
                    return "seventy one";
                case 72:
                    return "seventy two";
                case 73:
                    return "seventy three";
                case 74:
                    return "seventy four";
                case 75:
                    return "seventy five";
                case 76:
                    return "seventy six";
                case 77:
                    return "seventy seven";
                case 78:
                    return "seventy eight";
                case 79:
                    return "seventy nine";
                case 80:
                    return "eighty";
                case 81:
                    return "eighty one";
                case 82:
                    return "eighty two";
                case 83:
                    return "eighty three";
                case 84:
                    return "eighty four";
                case 85:
                    return "eighty five";
                case 86:
                    return "eighty six";
                case 87:
                    return "eighty seven";
                case 88:
                    return "eighty eight";
                case 89:
                    return "eighty nine";
                case 90:
                    return "ninety";
                case 91:
                    return "ninety one";
                case 92:
                    return "ninety two";
                case 93:
                    return "ninety three";
                case 94:
                    return "ninety four";
                case 95:
                    return "ninety five";
                case 96:
                    return "ninety six";
                case 97:
                    return "ninety seven";
                case 98:
                    return "ninety eight";
                case 99:
                    return "ninety nine";
                default:
                    return "";
            }
        }

        /**
         * process numbers below one thousand and above 999
         * @param int $num
         * @return string
         */
        private function processHundred(int $num): string
        {
            if(strlen(strval($num)) == 3)
            {
                $l2 = $this->trimStart($num, 1);
                return $this->simpleNumber($this->trimEnd($num, 2))." hundred". (($l2 > 0) ? (" and ".$this->simpleNumber($l2)) : "");
            }
            return "";
        }

        /**
         * remove numbers off the end of an integer
         * @param int $val
         * @param int $trimLength
         * @return int|null
         */
        private function trimEnd(int $val, int $trimLength=3): ?int
        {
            $str = strval($val);

            if(strlen($str) > $trimLength)
            {
                return intval(substr($str, 0, (strlen($str) - $trimLength)));
            }
            return null;
        }

        /**
         * remove numbers off the start of an integer
         * @param int $val
         * @param int $trimLength
         * @return int|null
         */
        private function trimStart(int $val, int $trimLength=3): ?int
        {
            $str = strval($val);

            if(strlen($str) > $trimLength)
            {
                return intval(substr($str, $trimLength));
            }
            return null;
        }
        #endregion
    }