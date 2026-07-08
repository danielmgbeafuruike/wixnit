<?php

    namespace Wixnit\Utilities;

    class Url
    {
        /**
         * build a URL string from its component parts (the same shape returned by Parse()).
         * Any component can be omitted. e.g.:
         *   Url::Build(["scheme" => "https", "host" => "example.com", "path" => "/users", "query" => ["id" => 5]])
         *   -> "https://example.com/users?id=5"
         * @param array $parts
         * @return string
         */
        public static function Build(array $parts): string
        {
            $url = "";

            if(isset($parts["scheme"]))
            {
                $url .= $parts["scheme"]."://";
            }

            if(isset($parts["user"]))
            {
                $url .= $parts["user"];
                if(isset($parts["pass"]))
                {
                    $url .= ":".$parts["pass"];
                }
                $url .= "@";
            }

            if(isset($parts["host"]))
            {
                $url .= $parts["host"];
            }

            if(isset($parts["port"]))
            {
                $url .= ":".$parts["port"];
            }

            if(isset($parts["path"]))
            {
                $path = $parts["path"];
                if(($url != "") && !str_starts_with($path, "/"))
                {
                    $path = "/".$path;
                }
                $url .= $path;
            }

            if(isset($parts["query"]) && (count((array) $parts["query"]) > 0))
            {
                $query = is_array($parts["query"]) ? http_build_query($parts["query"]) : $parts["query"];
                if($query != "")
                {
                    $url .= "?".$query;
                }
            }

            if(isset($parts["fragment"]))
            {
                $url .= "#".$parts["fragment"];
            }

            return $url;
        }

        /**
         * parse a URL into its component parts: scheme, host, port, user, pass, path,
         * query (as an associative array), and fragment.
         * @param string $url
         * @return array
         */
        public static function Parse(string $url): array
        {
            $parts = parse_url($url);

            if($parts === false)
            {
                return [];
            }

            if(isset($parts["query"]))
            {
                parse_str($parts["query"], $query);
                $parts["query"] = $query;
            }
            else
            {
                $parts["query"] = [];
            }
            return $parts;
        }

        /**
         * get the query string parameters of a URL as an associative array
         * @param string $url
         * @return array
         */
        public static function Query(string $url): array
        {
            return Url::Parse($url)["query"] ?? [];
        }

        /**
         * append (or overwrite) query string parameters on a URL, preserving whatever query params already exist
         * @param string $url
         * @param array $params
         * @return string
         */
        public static function Append(string $url, array $params): string
        {
            $parts = Url::Parse($url);
            $parts["query"] = array_merge($parts["query"] ?? [], $params);

            return Url::Build($parts);
        }

        /**
         * remove one or more query string parameters from a URL
         * @param string $url
         * @param string|array $keys a single key, or an array of keys
         * @return string
         */
        public static function RemoveQuery(string $url, string | array $keys): string
        {
            $keys = is_array($keys) ? $keys : [$keys];
            $parts = Url::Parse($url);
            $parts["query"] = array_diff_key($parts["query"] ?? [], array_flip($keys));

            return Url::Build($parts);
        }

        /**
         * check whether a URL uses the https scheme
         * @param string $url
         * @return bool
         */
        public static function IsSecure(string $url): bool
        {
            return strtolower(parse_url($url, PHP_URL_SCHEME) ?? "") === "https";
        }
    }
