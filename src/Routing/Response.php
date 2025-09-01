<?php

    namespace Wixnit\Routing;

    use Wixnit\Enum\HTTPResponseCode;
    use Wixnit\Interfaces\ITranslator;

    class Response
    {
        public $content = "";
        public HTTPResponseCode $statusCode = HTTPResponseCode::OK;
        public array $headers = [];
        public array $cookies = [];

        public ITranslator $translator;

        function __construct(HTTPResponseCode $code= HTTPResponseCode::OK, string $content="")
        {
            $this->statusCode = $code;
            $this->content = $content;
        }


        /**
         * Set the content of the response
         * @param string $content
         * @return static
         */
        public function setContent($content): static
        {
            $this->content = $content;
            return $this;
        }

        /**
         * Set a header for the response
         * @param string $name
         * @param string $value
         * @return static
         */
        public function setHeader($name, $value): static
        {
            $this->headers[$name] = $value;
            return $this;
        }

        /**
         * Set the status code for the response
         * @param HTTPResponseCode $code
         * @return static
         */
        public function setStatusCode(HTTPResponseCode $code): static
        {
            $this->statusCode = $code;
            return $this;
        }

        /**
         * Set a cookie for the response
         * @param string $name
         * @param string $value
         * @param int $expires
         * @param string $path
         * @param string $domain
         * @param bool $secure
         * @param bool $httponly
         * @return static
         */
        public function setCookie($name, $value, $expires=0, $path="/", $domain="", $secure=false, $httponly=false): static
        {
            $this->cookies[$name] = [
                'value' => $value,
                'expires' => $expires,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly
            ];
            return $this;
        }

        

        /**
         * Send the response to the client
         * @return void
         */
        public function send(): void
        {
            http_response_code($this->statusCode->value);

             $global_headers = Response::GetGlobalHeaders();
            foreach ($global_headers as $name => $value) {
                header("$name: $value");
            }

            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }

            foreach ($this->cookies as $name => $cookie) {
                setcookie($name, $cookie['value'], $cookie['expires'], $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
            }
            //echo isset($this->translator) ? $this->translator->translate($this->content) : $this->content;
            echo $this->content;
        }

        /**
         * Send the response as JSON
         * @param mixed $data
         * @return static
         */
        public function json($data): static
        {
            $this->setHeader('content-Type', 'application/json');
            $this->content = json_encode($data);
            return $this;
        }

        public function text($text): static
        {
            $this->setHeader('content-Type', 'text/plain');
            $this->content = $text;
            return $this;
        }

        /**
         * Set the content of the response
         * @param string $content
         * @return static
         */
        public function html($content): static
        {
            $this->content = $content;
            $this->setHeader('content-Type', 'text/html; charset=utf-8');
            return $this;   
        }

        /**
         * Set the content of the response as JSONP
         * @param string $callback
         * @param mixed $data
         * @return static
         */
        public function jsonp($callback, $data): static
        {
            $this->setHeader('content-Type', 'application/javascript');
            $this->content = $callback . '(' . json_encode($data) . ');';
            return $this;
        }

        /**
         * Set the content of the response as XML
         * @param string $xml
         * @return static
         */
        public function xml($xml): static
        {
            $this->setHeader('content-Type', 'application/xml; charset=utf-8');
            $this->content = $xml;
            return $this;
        }

        /**
         * Set the content of the response as plain text
         * @param string $text
         * @return static
         */
        public function plainText($text): static
        {
            $this->setHeader('content-Type', 'text/plain; charset=utf-8');
            $this->content = $text;
            return $this;
        }

        /**
         * Set the content of the response as a file download
         * @param string $filename
         * @param string $filecontent
         * @return static
         */
        public function fileDownload($filename, $filecontent): static
        {
            $this->setHeader('content-Type', 'application/octet-stream');
            $this->setHeader('content-Disposition', 'attachment; filename="' . $filename . '"');
            $this->content = $filecontent;
            return $this;
        }

        /**
         * Set the content of the response as a redirect
         * @param string $url
         * @param int $statusCode
         * @return static
         */
        public function redirect($url, $statusCode=302): static
        {
            $this->setHeader('Location', $url);
            $this->setStatusCode(HTTPResponseCode::from($statusCode));
            $this->content = '';
            return $this;
        }



        #region static Methods
        /**
         * set a session value
         * @param mixed $name
         * @param mixed $value
         * @return void
         */
        public static function SetSession($name, $value): void
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION[$name] = $value;
        }
        #endregion


        #region global methods

        public static function SetGlobalHeaders(array $headers): void
        {
            $prep = [];
            
            if(getenv("Wixnit-Global-Headers")) {
                $prep = json_decode(getenv("Wixnit-Global-Headers"), true);
            }

            $keys = array_keys($headers);
            for($i = 0; $i < count($keys); $i++)
            {
                $prep[$keys[$i]] = $headers[$keys[$i]];
            }
            putenv('Wixnit-Global-Headers='. json_encode($prep));
        }

        public static function SetGlobalCorsHeaders(string $allow="*", array $methods=['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']): void
        {
            header("Access-Control-Allow-Origin: $allow");
            header("Access-Control-Allow-Headers: X-Requested-With, content-Type, Authorization");
            header("Access-Control-Max-Age: 86400");

            if (!empty($methods)) {
                header("Access-Control-Allow-Methods: ".implode(', ', $methods));
            }

            if ($_SERVER["REQUEST_METHOD"] === 'OPTIONS') {
                header('HTTP/1.1 200 OK');
                exit();
            }
        }

        public static function GetGlobalHeaders(): array
        {
            $headers = [];
            if(getenv("Wixnit-Global-Headers")) {
                $headers = json_decode(getenv("Wixnit-Global-Headers"), true);
            }
            return $headers;
        }
        #endregion
    }