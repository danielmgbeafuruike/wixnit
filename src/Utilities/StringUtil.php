<?php

    namespace Wixnit\Utilities;

    /**
     * A convenience facade over common string operations. Where the operation already
     * exists on `Str`, this class simply forwards to it (kept as a separate class so it
     * can offer this library's shorter, camelCase-first-letter naming convention).
     */
    class StringUtil
    {
        /**
         * convert a string to camelCase, e.g. "hello world" -> "helloWorld"
         * @param string $string
         * @return string
         */
        public static function CamelCase(string $string): string
        {
            return Str::CamelCase($string);
        }

        /**
         * convert a string to snake_case, e.g. "Hello World" -> "hello_world"
         * @param string $string
         * @return string
         */
        public static function SnakeCase(string $string): string
        {
            return Str::SnakeCase($string);
        }

        /**
         * convert a string to kebab-case, e.g. "Hello World" -> "hello-world"
         * @param string $string
         * @return string
         */
        public static function KebabCase(string $string): string
        {
            return Str::KebabCase($string);
        }

        /**
         * convert a string into a URL-friendly slug, e.g. "Hello World!" -> "hello-world"
         * @param string $string
         * @param string $separator
         * @return string
         */
        public static function Slug(string $string, string $separator = "-"): string
        {
            return Str::Slug($string, $separator);
        }

        /**
         * truncate a string to a maximum length, breaking on the nearest whole word where possible
         * @param string $string
         * @param int $length
         * @param string $suffix
         * @return string
         */
        public static function Truncate(string $string, int $length = 100, string $suffix = "..."): string
        {
            return Str::Truncate($string, $length, $suffix);
        }

        /**
         * check if a string contains a given substring
         * @param string $haystack
         * @param string $needle
         * @param bool $caseSensitive
         * @return bool
         */
        public static function Contains(string $haystack, string $needle, bool $caseSensitive = true): bool
        {
            return Str::Contains($haystack, $needle, $caseSensitive);
        }

        /**
         * check if a string starts with a given substring
         * @param string $haystack
         * @param string $needle
         * @return bool
         */
        public static function StartsWith(string $haystack, string $needle): bool
        {
            return Str::StartsWith($haystack, $needle);
        }

        /**
         * check if a string ends with a given substring
         * @param string $haystack
         * @param string $needle
         * @return bool
         */
        public static function EndsWith(string $haystack, string $needle): bool
        {
            return Str::EndsWith($haystack, $needle);
        }

        /**
         * mask part of a string, keeping some characters visible at the start and/or end.
         * e.g. StringUtil::Mask("4111111111111111", 0, 4) -> "************1111"
         * @param string $string
         * @param int $visibleStart
         * @param int $visibleEnd
         * @param string $maskChar
         * @return string
         */
        public static function Mask(string $string, int $visibleStart = 0, int $visibleEnd = 4, string $maskChar = "*"): string
        {
            return Str::Mask($string, $visibleStart, $visibleEnd, $maskChar);
        }

        /**
         * reverse a string. Multi-byte safe, so accented/unicode characters aren't mangled
         * the way PHP's native strrev() would mangle them.
         * @param string $string
         * @return string
         */
        public static function Reverse(string $string): string
        {
            $chars = mb_str_split($string);
            return implode("", array_reverse($chars));
        }

        /**
         * generate a random string of the given length, made up of letters and digits
         * @param int $length
         * @return string
         */
        public static function Random(int $length = 16): string
        {
            return Random::Token($length);
        }

        /**
         * convert a string to Title Case, e.g. "hello world" -> "Hello World"
         * @param string $string
         * @return string
         */
        public static function Title(string $string): string
        {
            return Str::Title($string);
        }

        /**
         * naively pluralize an English word, e.g. "box" -> "boxes", "city" -> "cities", "cat" -> "cats".
         * Covers the common regular cases plus a handful of frequent irregulars; for anything
         * more exotic, consider a dedicated inflector library.
         * @param string $word
         * @return string
         */
        public static function Plural(string $word): string
        {
            $irregulars = [
                "person" => "people", "man" => "men", "woman" => "women", "child" => "children",
                "tooth" => "teeth", "foot" => "feet", "mouse" => "mice", "goose" => "geese",
            ];

            $lower = strtolower($word);
            if(isset($irregulars[$lower]))
            {
                return StringUtil::matchCase($word, $irregulars[$lower]);
            }

            if(preg_match('/(s|x|z|ch|sh)$/i', $word))
            {
                return $word."es";
            }
            if(preg_match('/[^aeiou]y$/i', $word))
            {
                return substr($word, 0, -1)."ies";
            }
            if(preg_match('/fe?$/i', $word))
            {
                return preg_replace('/fe?$/i', 'ves', $word);
            }
            return $word."s";
        }

        /**
         * naively singularize an English word, e.g. "boxes" -> "box", "cities" -> "city", "cats" -> "cat".
         * Covers the common regular cases plus a handful of frequent irregulars.
         * @param string $word
         * @return string
         */
        public static function Singular(string $word): string
        {
            $irregulars = [
                "people" => "person", "men" => "man", "women" => "woman", "children" => "child",
                "teeth" => "tooth", "feet" => "foot", "mice" => "mouse", "geese" => "goose",
            ];

            $lower = strtolower($word);
            if(isset($irregulars[$lower]))
            {
                return StringUtil::matchCase($word, $irregulars[$lower]);
            }

            if(preg_match('/ies$/i', $word))
            {
                return substr($word, 0, -3)."y";
            }
            if(preg_match('/ves$/i', $word))
            {
                return substr($word, 0, -3)."fe";
            }
            if(preg_match('/(ses|xes|zes|ches|shes)$/i', $word))
            {
                return substr($word, 0, -2);
            }
            if(preg_match('/s$/i', $word) && !preg_match('/ss$/i', $word))
            {
                return substr($word, 0, -1);
            }
            return $word;
        }

        /**
         * limit a string to a maximum number of words
         * @param string $string
         * @param int $words
         * @param string $suffix
         * @return string
         */
        public static function LimitWords(string $string, int $words = 20, string $suffix = "..."): string
        {
            return Str::LimitWords($string, $words, $suffix);
        }

        /**
         * strip accents/diacritics from a string, e.g. "café crème brûlée" -> "cafe creme brulee"
         * @param string $string
         * @return string
         */
        public static function RemoveAccents(string $string): string
        {
            $map = [
                'á'=>'a','à'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','ā'=>'a',
                'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ė'=>'e','ę'=>'e',
                'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ī'=>'i',
                'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ō'=>'o','ø'=>'o',
                'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ū'=>'u',
                'ç'=>'c','ć'=>'c','č'=>'c','ñ'=>'n','ń'=>'n','ý'=>'y','ÿ'=>'y',
                'ß'=>'ss','œ'=>'oe','æ'=>'ae',
                'Á'=>'A','À'=>'A','Â'=>'A','Ä'=>'A','Ã'=>'A','Å'=>'A','Ā'=>'A',
                'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','Ē'=>'E','Ė'=>'E','Ę'=>'E',
                'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','Ī'=>'I',
                'Ó'=>'O','Ò'=>'O','Ô'=>'O','Ö'=>'O','Õ'=>'O','Ō'=>'O','Ø'=>'O',
                'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','Ū'=>'U',
                'Ç'=>'C','Ć'=>'C','Č'=>'C','Ñ'=>'N','Ń'=>'N','Ý'=>'Y',
                'Œ'=>'OE','Æ'=>'AE',
            ];
            return strtr($string, $map);
        }

        /**
         * check whether a string is a syntactically valid email address
         * @param string $string
         * @return bool
         */
        public static function IsEmail(string $string): bool
        {
            return filter_var($string, FILTER_VALIDATE_EMAIL) !== false;
        }

        /**
         * check whether a string is a syntactically valid URL
         * @param string $string
         * @return bool
         */
        public static function IsUrl(string $string): bool
        {
            return filter_var($string, FILTER_VALIDATE_URL) !== false;
        }

        /**
         * loosely check whether a string looks like a phone number
         * (digits, and the usual separators: spaces, dashes, dots, parentheses, a leading +).
         * This is a format check only - it does not verify the number is real or reachable.
         * @param string $string
         * @return bool
         */
        public static function IsPhone(string $string): bool
        {
            $trimmed = trim($string);
            if(!preg_match('/^\+?[0-9\s().-]{7,20}$/', $trimmed))
            {
                return false;
            }
            $digitCount = preg_match_all('/\d/', $trimmed);
            return $digitCount >= 7;
        }

        /**
         * apply the capitalization pattern of $source onto $replacement -
         * used internally so Plural()/Singular() respect the input's original casing
         * (all caps, capitalized, or lowercase).
         * @param string $source
         * @param string $replacement
         * @return string
         */
        private static function matchCase(string $source, string $replacement): string
        {
            if(ctype_upper($source))
            {
                return strtoupper($replacement);
            }
            if(ctype_upper($source[0] ?? ""))
            {
                return ucfirst($replacement);
            }
            return $replacement;
        }
    }
