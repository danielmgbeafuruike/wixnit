<?php

    namespace Wixnit\Utilities;

    class Str
    {
        /**
         * convert a string into a URL-friendly slug, e.g. "Hello World!" -> "hello-world"
         * @param string $string
         * @param string $separator
         * @return string
         */
        public static function Slug(string $string, string $separator = "-"): string
        {
            $string = strtolower(trim($string));
            $string = preg_replace('/[^a-z0-9]+/', $separator, $string);
            return trim($string, $separator);
        }

        /**
         * convert a string to camelCase, e.g. "hello world" / "hello-world" / "hello_world" -> "helloWorld"
         * @param string $string
         * @return string
         */
        public static function CamelCase(string $string): string
        {
            $studly = Str::StudlyCase($string);
            return lcfirst($studly);
        }

        /**
         * convert a string to StudlyCase / PascalCase, e.g. "hello world" -> "HelloWorld"
         * @param string $string
         * @return string
         */
        public static function StudlyCase(string $string): string
        {
            $words = preg_split('/[\s\-_]+/', trim($string));
            $ret = "";

            for($i = 0; $i < count($words); $i++)
            {
                if($words[$i] != "")
                {
                    $ret .= ucfirst(strtolower($words[$i]));
                }
            }
            return $ret;
        }

        /**
         * convert a string to snake_case, e.g. "Hello World" / "helloWorld" -> "hello_world"
         * @param string $string
         * @param string $delimiter
         * @return string
         */
        public static function SnakeCase(string $string, string $delimiter = "_"): string
        {
            $string = preg_replace('/\s+/', '', $string);
            $string = preg_replace('/(.)(?=[A-Z])/', '$1'.$delimiter, $string);
            return strtolower($string);
        }

        /**
         * convert a string to kebab-case, e.g. "Hello World" / "helloWorld" -> "hello-world"
         * @param string $string
         * @return string
         */
        public static function KebabCase(string $string): string
        {
            return Str::SnakeCase($string, "-");
        }

        /**
         * convert a string to Title Case, e.g. "hello world" -> "Hello World"
         * @param string $string
         * @return string
         */
        public static function Title(string $string): string
        {
            return ucwords(strtolower(trim($string)));
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
            if(strlen($string) <= $length)
            {
                return $string;
            }

            $truncated = substr($string, 0, $length);
            $lastSpace = strrpos($truncated, " ");

            if($lastSpace !== false)
            {
                $truncated = substr($truncated, 0, $lastSpace);
            }
            return rtrim($truncated).$suffix;
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
            $parts = preg_split('/\s+/', trim($string));

            if(count($parts) <= $words)
            {
                return $string;
            }
            return implode(" ", array_slice($parts, 0, $words)).$suffix;
        }

        /**
         * mask part of a string, keeping some characters visible at the start and/or end
         * e.g. Str::Mask("4111111111111111", 0, 4) -> "************1111"
         * @param string $string
         * @param int $visibleStart
         * @param int $visibleEnd
         * @param string $maskChar
         * @return string
         */
        public static function Mask(string $string, int $visibleStart = 0, int $visibleEnd = 4, string $maskChar = "*"): string
        {
            $len = strlen($string);

            if($len <= ($visibleStart + $visibleEnd))
            {
                return str_repeat($maskChar, $len);
            }

            $start = substr($string, 0, $visibleStart);
            $end = $visibleEnd > 0 ? substr($string, -$visibleEnd) : "";
            $maskedLength = $len - $visibleStart - $visibleEnd;

            return $start.str_repeat($maskChar, $maskedLength).$end;
        }

        /**
         * mask an email address, e.g. "john.doe@example.com" -> "jo******@example.com"
         * @param string $email
         * @param int $visible
         * @param string $maskChar
         * @return string
         */
        public static function MaskEmail(string $email, int $visible = 2, string $maskChar = "*"): string
        {
            $parts = explode("@", $email);

            if(count($parts) != 2)
            {
                return $email;
            }

            [$name, $domain] = $parts;
            $visible = min($visible, strlen($name));
            $masked = substr($name, 0, $visible).str_repeat($maskChar, max(strlen($name) - $visible, 3));

            return $masked."@".$domain;
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
            if(!$caseSensitive)
            {
                $haystack = strtolower($haystack);
                $needle = strtolower($needle);
            }
            return str_contains($haystack, $needle);
        }

        /**
         * check if a string starts with a given substring
         * @param string $haystack
         * @param string $needle
         * @return bool
         */
        public static function StartsWith(string $haystack, string $needle): bool
        {
            return str_starts_with($haystack, $needle);
        }

        /**
         * check if a string ends with a given substring
         * @param string $haystack
         * @param string $needle
         * @return bool
         */
        public static function EndsWith(string $haystack, string $needle): bool
        {
            return str_ends_with($haystack, $needle);
        }

        /**
         * extract the substring found between two delimiters, e.g. Str::Between("[hi]", "[", "]") -> "hi"
         * @param string $string
         * @param string $start
         * @param string $end
         * @return string
         */
        public static function Between(string $string, string $start, string $end): string
        {
            $startPos = strpos($string, $start);

            if($startPos === false)
            {
                return "";
            }
            $startPos += strlen($start);

            $endPos = strpos($string, $end, $startPos);

            if($endPos === false)
            {
                return "";
            }
            return substr($string, $startPos, $endPos - $startPos);
        }

        /**
         * remove all whitespace from a string
         * @param string $string
         * @return string
         */
        public static function RemoveWhitespace(string $string): string
        {
            return preg_replace('/\s+/', '', $string);
        }

        /**
         * get the initials from a name, e.g. "John Ronald Reuel Tolkien" -> "JT" (or "JRRT" with $all=true)
         * @param string $name
         * @param bool $all
         * @return string
         */
        public static function Initials(string $name, bool $all = false): string
        {
            $words = preg_split('/\s+/', trim($name));
            $words = array_filter($words, fn($w) => $w !== "");

            if(count($words) == 0)
            {
                return "";
            }

            if($all)
            {
                return strtoupper(implode("", array_map(fn($w) => $w[0], $words)));
            }

            $first = strtoupper($words[array_key_first($words)][0]);
            $last = count($words) > 1 ? strtoupper($words[array_key_last($words)][0]) : "";
            return $first.$last;
        }

        /**
         * check if a string is empty once trimmed
         * @param string|null $string
         * @return bool
         */
        public static function IsBlank(?string $string): bool
        {
            return $string === null || trim($string) === "";
        }

        /**
         * count the number of words in a string
         * @param string $string
         * @return int
         */
        public static function WordCount(string $string): int
        {
            return str_word_count($string);
        }
    }
