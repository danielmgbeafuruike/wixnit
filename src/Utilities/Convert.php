<?php

    namespace Wixnit\Utilities;

    use SimpleXMLElement;
    use stdClass;

    class Convert
    {
        /**
         * convert any compatible object to it's integer value
         * @param mixed $arg
         * @return int
         */
        public static function ToInt($arg)
        {
            $ret = 0;
            if($arg instanceof Date)
            {
                $ret = $arg->toEpochSeconds();
            }
            else if($arg instanceof Time)
            {
                $ret = $arg->toSeconds();
            }
            else if(is_bool($arg))
            {
                return $arg ? 1 : 0;
            }
            else if(is_array($arg))
            {
                $ret = count($arg);
            }
            else if(is_object($arg) && (method_exists($arg, "ToInt")))
            {
                $ret = $arg->ToInt();
            }
            else if(is_object($arg) && (method_exists($arg, "toInt")))
            {
                $ret = $arg->toInt();
            }
            else if(is_object($arg) && (method_exists($arg, "getInt")))
            {
                $ret = $arg->getInt();
            }
            else if(is_object($arg) && (method_exists($arg, "GetInt")))
            {
                $ret = $arg->GetInt();
            }
            else
            {
                $ret = $arg;
            }
            return intval($ret);
        }

        /**
         * convert any compatible object to it's boolean value
         * @param mixed $arg
         * @return bool
         */
        public static function ToBool($arg)
        {
            if($arg == "1")
            {
                return true;
            }
            else if($arg == "0")
            {
                return false;
            }
            else if(strtoupper($arg) === "TRUE")
            {
                return true;
            }
            else if(strtoupper($arg) === "FALSE")
            {
                return false;
            }
            else
            {
                return boolval($arg);
            }
        }

        /**
         * convert integers to words
         * @param mixed $number
         * @return string
         */
        public static function NumbersToWords(float $number): string
        {
            return (new NumbersToWords($number))->words();
        }

        /**
         * get the integer value of parsed month
         * @param string $month
         * @return int
         */
        public static function MonthToNumber(string $month): int
        {
            $ret = 0;

            if((strtolower(trim($month)) == "january") || (strtolower(trim($month)) == "jan"))
            {
                $ret = 1;
            }
            if((strtolower(trim($month)) == "february") || (strtolower(trim($month)) == "feb"))
            {
                $ret = 2;
            }
            if((strtolower(trim($month)) == "march") || (strtolower(trim($month)) == "mar"))
            {
                $ret = 3;
            }
            if((strtolower(trim($month)) == "april") || (strtolower(trim($month)) == "apr"))
            {
                $ret = 4;
            }
            if((strtolower(trim($month)) == "may") || (strtolower(trim($month)) == "may"))
            {
                $ret = 5;
            }
            if((strtolower(trim($month)) == "june") || (strtolower(trim($month)) == "jun"))
            {
                $ret = 6;
            }
            if((strtolower(trim($month)) == "july") || (strtolower(trim($month)) == "jul"))
            {
                $ret = 7;
            }
            if((strtolower(trim($month)) == "august") || (strtolower(trim($month)) == "aug"))
            {
                $ret = 8;
            }
            if((strtolower(trim($month)) == "september") || (strtolower(trim($month)) == "september"))
            {
                $ret = 9;
            }
            if((strtolower(trim($month)) == "october") || (strtolower(trim($month)) == "oct"))
            {
                $ret = 10;
            }
            if((strtolower(trim($month)) == "november") || (strtolower(trim($month)) == "nov"))
            {
                $ret = 11;
            }
            if((strtolower(trim($month)) == "december") || (strtolower(trim($month)) == "dec"))
            {
                $ret = 12;
            }
            if($ret === 0)
            {
                $ret = Convert::ToInt($month);
            }

            return $ret;
        }

        /**
         * conert an integer from (1 - 12) to it's months value in short form jan, feb, mar etc.
         * @param int $number
         * @return string
         */
        public static function IntToMonthShort(int $number): string
        {
            $ret = "Unknown";

            if(Convert::ToInt($number) === 1)
            {
                $ret = "jan";
            }
            if(Convert::ToInt($number) === 2)
            {
                $ret = "feb";
            }
            if(Convert::ToInt($number) === 3)
            {
                $ret = "mar";
            }
            if(Convert::ToInt($number) === 4)
            {
                $ret = "apr";
            }
            if(Convert::ToInt($number) === 5)
            {
                $ret = "may";
            }
            if(Convert::ToInt($number) === 6)
            {
                $ret = "jun";
            }
            if(Convert::ToInt($number) === 7)
            {
                $ret = "jul";
            }
            if(Convert::ToInt($number) === 8)
            {
                $ret = "aug";
            }
            if(Convert::ToInt($number) === 9)
            {
                $ret = "sept";
            }
            if(Convert::ToInt($number) === 10)
            {
                $ret = "oct";
            }
            if(Convert::ToInt($number) === 11)
            {
                $ret = "nov";
            }
            if(Convert::ToInt($number) === 12)
            {
                $ret = "dec";
            }

            return $ret;
        }

        /**
         * conert an integer from (1 - 12) to it's months value in full form january, february, march etc.
         * @param int $number
         * @return string
         */
        public static function IntToMonth(int $number): string
        {
            $ret = "Unknown";

            if(Convert::ToInt($number) === 1)
            {
                $ret = "january";
            }
            if(Convert::ToInt($number) === 2)
            {
                $ret = "february";
            }
            if(Convert::ToInt($number) === 3)
            {
                $ret = "march";
            }
            if(Convert::ToInt($number) === 4)
            {
                $ret = "april";
            }
            if(Convert::ToInt($number) === 5)
            {
                $ret = "may";
            }
            if(Convert::ToInt($number) === 6)
            {
                $ret = "june";
            }
            if(Convert::ToInt($number) === 7)
            {
                $ret = "july";
            }
            if(Convert::ToInt($number) === 8)
            {
                $ret = "august";
            }
            if(Convert::ToInt($number) === 9)
            {
                $ret = "september";
            }
            if(Convert::ToInt($number) === 10)
            {
                $ret = "october";
            }
            if(Convert::ToInt($number) === 11)
            {
                $ret = "november";
            }
            if(Convert::ToInt($number) === 12)
            {
                $ret = "december";
            }

            return $ret;
        }

        /**
         * convert any array to a standard stdClass object
         * @param array $array
         * @return stdClass
         */
        public static function ArrayToStdClass(array $array): stdClass
        {
            $object = new stdClass();
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $value = Convert::ArrayToStdClass($value);
                }
                $object->$key = $value;
            }

            return $object;
        }

        /**
         * convert any stdClass to an XMLobject
         * @param mixed $data
         * @param mixed $rootElement
         * @param mixed $xml
         * @return string
         */
        public static function StdClassToXML($data, $rootElement = 'root', $xml = null): string
        {
            if ($xml === null) 
            {
                $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $rootElement . '/>');
            }
        
            foreach ($data as $key => $value) 
            {
                // If value is an array or object, recursively process it
                if (is_array($value) || is_object($value)) 
                {
                    $childNode = $xml->addChild($key);
                    Convert::StdClassToXML($value, $rootElement, $childNode);
                } 
                else 
                {
                    // Add the value as a child element
                    $xml->addChild($key, htmlspecialchars($value));
                }
            }
            return $xml->asXML();
        }
    }