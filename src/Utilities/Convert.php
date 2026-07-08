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
            if(($arg instanceof Date) || ($arg instanceof DateTime))
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
         * convert an integer from (1 - 12) to it's months value in full form january, february, march etc.
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
         * convert a byte count into a human readable string, e.g. 1536 -> "1.5 KB"
         * @param float $bytes
         * @param int $precision
         * @return string
         */
        public static function BytesToHuman(float $bytes, int $precision = 2): string
        {
            $units = ["B", "KB", "MB", "GB", "TB", "PB"];
            $bytes = max($bytes, 0);
            $power = ($bytes > 0) ? floor(log($bytes, 1024)) : 0;
            $power = min($power, count($units) - 1);

            $value = $bytes / (1024 ** $power);

            return round($value, $precision)." ".$units[(int) $power];
        }

        /**
         * convert a human readable byte string back into a raw byte count, e.g. "1.5 KB" -> 1536
         * @param string $value
         * @return float
         */
        public static function HumanToBytes(string $value): float
        {
            $value = trim($value);

            if(preg_match('/^([\d.]+)\s*([A-Za-z]+)$/', $value, $m))
            {
                $units = ["B" => 0, "KB" => 1, "MB" => 2, "GB" => 3, "TB" => 4, "PB" => 5];
                $unit = strtoupper($m[2]);

                if(isset($units[$unit]))
                {
                    return ((float) $m[1]) * (1024 ** $units[$unit]);
                }
            }
            return (float) $value;
        }

        /**
         * convert any compatible object to it's float value
         * @param mixed $arg
         * @return float
         */
        public static function ToFloat($arg): float
        {
            if(($arg instanceof Date) || ($arg instanceof DateTime))
            {
                return (float) $arg->toEpochSeconds();
            }
            else if($arg instanceof Time)
            {
                return (float) $arg->toSeconds();
            }
            else if(is_bool($arg))
            {
                return $arg ? 1.0 : 0.0;
            }
            else if(is_array($arg))
            {
                return (float) count($arg);
            }
            else if(is_string($arg))
            {
                //strip anything that isn't part of a valid float (currency symbols, thousands separators, etc)
                $clean = preg_replace('/[^0-9.\-]/', '', $arg);
                return floatval($clean);
            }
            return floatval($arg);
        }

        /**
         * convert any value to a readable string representation.
         * Booleans become "true"/"false", null becomes "", arrays/objects are JSON encoded.
         * @param mixed $arg
         * @return string
         */
        public static function ToString($arg): string
        {
            if($arg === null)
            {
                return "";
            }
            else if(is_bool($arg))
            {
                return $arg ? "true" : "false";
            }
            else if(($arg instanceof Date) || ($arg instanceof DateTime))
            {
                return $arg->format("Y-m-d H:i:s");
            }
            else if(is_array($arg) || ($arg instanceof stdClass))
            {
                return json_encode($arg);
            }
            else if(is_object($arg) && method_exists($arg, "__toString"))
            {
                return (string) $arg;
            }
            return strval($arg);
        }

        /**
         * convert any value into an array. Objects are converted using their public
         * properties, JSON strings are decoded, and scalars are wrapped in a single-item array.
         * @param mixed $arg
         * @return array
         */
        public static function ToArray($arg): array
        {
            if(is_array($arg))
            {
                return $arg;
            }
            else if($arg === null)
            {
                return [];
            }
            else if(is_object($arg))
            {
                return get_object_vars($arg);
            }
            else if(is_string($arg))
            {
                $decoded = json_decode($arg, true);
                if((json_last_error() === JSON_ERROR_NONE) && is_array($decoded))
                {
                    return $decoded;
                }
            }
            return [$arg];
        }

        /**
         * convert any value into a stdClass object. Alias-style wrapper around
         * ArrayToStdClass() that also accepts JSON strings and existing objects.
         * @param mixed $arg
         * @return stdClass
         */
        public static function ToObject($arg): stdClass
        {
            if($arg instanceof stdClass)
            {
                return $arg;
            }
            else if(is_object($arg))
            {
                return Convert::ArrayToStdClass(get_object_vars($arg));
            }
            else if(is_string($arg))
            {
                $decoded = json_decode($arg);
                if((json_last_error() === JSON_ERROR_NONE) && ($decoded instanceof stdClass))
                {
                    return $decoded;
                }
            }
            return Convert::ArrayToStdClass(Convert::ToArray($arg));
        }

        /**
         * convert any value (array, object, scalar) to a JSON string
         * @param mixed $arg
         * @param int $flags optional json_encode() flags, e.g. JSON_PRETTY_PRINT
         * @return string
         */
        public static function ToJson($arg, int $flags = 0): string
        {
            return json_encode($arg, $flags);
        }

        /**
         * parse a JSON string back into an array (or stdClass if $asObject is true)
         * @param string $json
         * @param bool $asObject
         * @return mixed
         * @throws \Wixnit\Exception\UtilityException if the JSON is invalid
         */
        public static function FromJson(string $json, bool $asObject = false): mixed
        {
            $ret = json_decode($json, !$asObject);

            if(json_last_error() !== JSON_ERROR_NONE)
            {
                throw \Wixnit\Exception\UtilityException::InvalidJson(json_last_error_msg());
            }
            return $ret;
        }

        /**
         * convert an array or object to an XML string. Thin, friendlier wrapper around StdClassToXML().
         * @param mixed $data
         * @param string $rootElement
         * @return string
         */
        public static function ToXml($data, string $rootElement = "root"): string
        {
            $obj = is_array($data) ? Convert::ArrayToStdClass($data) : $data;
            return Convert::StdClassToXML($obj, $rootElement);
        }

        /**
         * parse an XML string into a nested array
         * @param string $xml
         * @return array
         * @throws \Wixnit\Exception\UtilityException if the XML is invalid
         */
        public static function FromXml(string $xml): array
        {
            $previousSetting = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $parsed = simplexml_load_string($xml);

            if($parsed === false)
            {
                $errors = libxml_get_errors();
                $message = count($errors) > 0 ? trim($errors[0]->message) : "unable to parse XML";
                libxml_clear_errors();
                libxml_use_internal_errors($previousSetting);
                throw \Wixnit\Exception\UtilityException::InvalidXml($message);
            }

            libxml_use_internal_errors($previousSetting);

            $json = json_encode($parsed);
            return json_decode($json, true) ?? [];
        }

        /**
         * base64-encode a string
         * @param string $data
         * @return string
         */
        public static function ToBase64(string $data): string
        {
            return base64_encode($data);
        }

        /**
         * decode a base64-encoded string
         * @param string $data
         * @param bool $strict when true, throws if the input isn't valid base64 instead of returning garbage
         * @return string
         * @throws \Wixnit\Exception\UtilityException if $strict is true and the input isn't valid base64
         */
        public static function FromBase64(string $data, bool $strict = false): string
        {
            $ret = base64_decode($data, $strict);

            if($ret === false)
            {
                throw \Wixnit\Exception\UtilityException::InvalidBase64();
            }
            return $ret;
        }

        /**
         * get the raw byte length of a value. Strings are measured directly (byte length,
         * not character count); arrays/objects are measured via their JSON representation.
         * @param mixed $value
         * @return int
         */
        public static function ToBytes($value): int
        {
            if(is_string($value))
            {
                return strlen($value);
            }
            else if(is_array($value) || is_object($value))
            {
                return strlen(json_encode($value));
            }
            return strlen(strval($value));
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