<?php

    namespace Wixnit\Routing;

    use ArrayAccess;
    use stdClass;
    use Wixnit\Enum\HTTPMethod;
    use Wixnit\Enum\ServerArgs;
    use Wixnit\Exception\RouterException;
    use Wixnit\Validation\Validation;

    class Request implements ArrayAccess
    {
        public HTTPMethod $method = HTTPMethod::ANY;

        private FormData $getData;
        private FormData $postData;
        private FormData $jsonData;
        private FormData $routedData;

        function __construct(HTTPMethod $method =null, ?FormData $routed_data = null, ?FormData $get_data = null, ?FormData $post_data = null, ?FormData $json_data = null)
        {
            $this->method = $method ?? HTTPMethod::ANY;
            $this->getData = $get_data ?? new FormData();
            $this->postData = $post_data ?? new FormData();
            $this->jsonData = $json_data ?? new FormData();
            $this->routedData = $routed_data ?? new FormData();
        }


        /**
         * Validate the request data using the provided validation arguments
         * @param array $validation_args
         * @return Validation
         */
        public function validate(array $validation_args = []): Validation
        {
            $validation = new Validation(array_merge($this->postData->args, $this->getData->args, $this->jsonData->args));
            $validation->addValues($validation_args);
            return $validation;
        }

        /**
         * Get the post form data as an associative array
         * @return array
         */
        public function getPost(): array
        {
            return $this->postData->args;
        }

        /**
         * Get the get data received from the request
         * @return array
         */
        public function getGet(): array
        {
            return $this->getData->args;
        }

        /**
         * Get the json data received from the request
         * @return array
         */
        public function getJson(): ?stdClass
        {
            return $this->jsonData->toJson();
        }

        /**
         * Get the routed data received from the request
         * @return array
         */
        public function getRoutedArgs(): array
        {
            return $this->routedData->args;
        }

        /**
         * Get the HTTP method of the request
         * @return HTTPMethod
         */
        public function getMethod(): HTTPMethod
        {
            return $this->method;
        }




       




        #region static methods

         /**
         * @comment get the IP address of the client
         * @return string
         */
        public static function GetClientIP(): string
        {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) 
            {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            }
            elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) 
            {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                $ip = explode(',', $ip)[0];
            }
            else 
            {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            return trim($ip);
        }

        /**
         * Get the server variable by name, null will be returned if it does not exist
         * @param ServerArgs|string|null $arg
         * @return string|array
         */
        public static function GetServer(ServerArgs | string $arg = null): string | array
        {
            $val = (($arg != null) ? (($arg instanceof ServerArgs) ? $arg->value : $arg) : null);
            return ($val != null) ? $_SERVER[$val] : $_SERVER;
        }

        /**
         * Get the base URL of the request
         * @return string
         */
        public static function GetBaseURL(): string
        {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $scriptName = dirname($_SERVER['SCRIPT_NAME']);
            return $protocol . $host . $scriptName;
        }

        /**
         * Get the full URL of the request
         * @return string
         */
        public static function GetFullURL(): string
        {
            return Request::GetBaseURL() . $_SERVER['REQUEST_URI'];
        }

        /**
         * Get the request headers as an associative array
         * @return string
         */
        public static function GetRequestHeaders(): array
        {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (strpos($name, 'HTTP_') === 0) {
                    $headerName = str_replace('_', '-', strtolower(substr($name, 5)));
                    $headers[$headerName] = $value;
                }
            }
            return $headers;
        }

        /**
         * Get the request content as a string
         * @return string
         */
        public static function GetRequestBody(): string
        {
            return file_get_contents('php://input');
        }

        /**
         * Get a specific cookie value by name, null will be returned if it does not exist
         * @return string
         */
        public static function GetCookie($name): string | null
        {
            return $_COOKIE[$name] ?? null;
        }

        /**
         * get a specific session value by name, null will be returned if it does not exist
         * @param mixed $name
         * @return void
         */
        public static function GetSession(string $name): mixed
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            return $_SESSION[$name] ?? null;
        }

        /**
         * Check if a session exist
         * @param mixed $name
         * @return void
         */
        public static function HasSession(string $name): bool
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            return isset($_SESSION[$name]);
        }

        /**
         * destroy the current session
         * @return void
         */
        public static function DestroySession(): void
        {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_unset();
                session_destroy();
            }
        }

        /**
         * get the current server info
         * @return array{document_root: string, request_time: int, script_name: string, server_addr: string, server_name: string, server_port: mixed}
         */
        public static function GetServerInfo(): array
        {
            return [
                'server_name' => $_SERVER['SERVER_NAME'] ?? '',
                'server_addr' => $_SERVER['SERVER_ADDR'] ?? '',
                'server_port' => $_SERVER['SERVER_PORT'] ?? '',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
                'script_name' => $_SERVER['SCRIPT_NAME'] ?? '',
                'request_time' => $_SERVER['REQUEST_TIME'] ?? time(),
            ];
        }

        /**
         * get the user agent of the request
         * @return string
         */
        public static function GetUserAgent(): string
        {
            return $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        /**
         * get the referrer of the request
         * @return string
         */
        public static function GetReferrer(): string
        {
            return $_SERVER['HTTP_REFERER'] ?? '';
        }

        /**
         * check if the request is an AJAX request
         * @return bool
         */
        public static function IsAJAXRequest(): bool
        {
            return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        }

        /**
         * get the request ID, this is a unique identifier for the request
         * @return string
         */
        public static function GetRequestID(): string
        {
            return uniqid('req_', true);
        }

        /**
         * get the request time, this is the time when the request was made
         * @return int
         */
        public static function GetRequestTime(): int
        {
            return $_SERVER['REQUEST_TIME'] ?? time();
        }

        /**
         * get the request URI
         * @return string
         */
        public static function GetRequestURI(): string
        {
            return $_SERVER['REQUEST_URI'] ?? '';
        }

        /**
         * get the request scheme (http or https)
         * @return string
         */
        public static function GetRequestScheme(): string
        {
            return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        }

        /**
         * get the request port
         * @return int
         */
        public static function GetRequestPort(): int
        {
            return $_SERVER['SERVER_PORT'] ?? 80;
        }

        /**
         * get the request path
         * @return string
         */
        public static function GetRequestPath(): string
        {
            return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
        }

        /**
         * get the request query string
         * @return string
         */
        public static function GetRequestQuery(): string
        {
            return parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '';
        }

        /**
         * get the request fragment
         * @return string
         */
        public static function GetRequestFragment(): string
        {
            return parse_url($_SERVER['REQUEST_URI'], PHP_URL_FRAGMENT) ?? '';
        }

        /**
         * Get the request headers as an associative array
         * @return array
         */
        public static function GetRequestHeadersAsArray(): array
        {
            $headers = [];
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower($name)] = $value;
            }
            return $headers;
        }

        /**
         * Get a specific request header by name, null will be returned if it does not exist
         * @param string $name
         * @return string|null
         */
        public static function GetRequestHeader($name): string | null
        {
            $headers = Request::GetRequestHeadersAsArray();
            return $headers[strtolower($name)] ?? null;
        }

        /**
         * Get the request content type, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestContentType(): string | null
        {
            return Request::GetRequestHeader('content-type');
        }

        /**
         * Get the request content length, null will be returned if it does not exist
         * @return int|null
         */
        public static function GetRequestContentLength(): ?int
        {
            return isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : null;
        }

        /**
         * Get the request accept header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestAccept(): string | null
        {
            return Request::GetRequestHeader('accept');
        }

        /**
         * Get the request accept language header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestAcceptLanguage(): string | null
        {
            return Request::GetRequestHeader('accept-language');
        }

        /**
         * Get the request accept encoding header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestAcceptEncoding(): string | null
        {
            return Request::GetRequestHeader('accept-encoding');
        }

        /**
         * Get the request authorization header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestAuthorization(): string | null
        {
            $auth = Request::GetRequestHeader('authorization') ?? Request::GetRequestHeader('HTTP_AUTHORIZATION');

            if(($auth == null) && (function_exists('apache_request_headers')))
            {
                $requestHeaders = apache_request_headers();
                // Look for the Authorization header case-insensitively
                if (isset($requestHeaders['Authorization'])) 
                {
                    $auth = trim($requestHeaders['Authorization']);
                }
            }
            return $auth ?: null;
        }

        /**
         * Get the Auth Bearer token in the request
         * @return string|null
         */
        public static function GetBearerToken(): string | null
        {
            $auth = Request::GetRequestAuthorization();

            if(($auth != null) && preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
                return $matches[1];
            }
            return null;
        }

        /**
         * Get the request cache control header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestCacheControl(): string | null
        {
            return Request::GetRequestHeader('cache-control');
        }

        /**
         * Get the request if modified since header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestIfModifiedSince(): string | null
        {
            return Request::GetRequestHeader('if-modified-since');
        }

        /**
         * Get the request if none match header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestIfNoneMatch(): string | null
        {
            return Request::GetRequestHeader('if-none-match');
        }

        /**
         * Get the request forwarded for header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestForwardedFor(): string | null
        {
            return Request::GetRequestHeader('x-forwarded-for');
        }

        /**
         * Get the request forwarded proto header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestForwardedProto(): string | null
        {
            return Request::GetRequestHeader('x-forwarded-proto');
        }

        /**
         * Get the request forwarded host header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestForwardedHost(): string | null
        {
            return Request::GetRequestHeader('x-forwarded-host');
        }

        /**
         * Get the request forwarded port header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestForwardedPort(): string | null
        {
            return Request::GetRequestHeader('x-forwarded-port');
        }

        /**
         * Get the request real IP header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestXRequestedWith(): string | null
        {
            return Request::GetRequestHeader('x-requested-with');
        }

        /**
         * Get the request x forwarded for header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestXForwardedFor(): string | null
        {
            return Request::GetRequestHeader('x-forwarded-for');
        }

        /**
         * Get the request x forwarded host header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestXForwardedHost(): string | null
        {
            return Request::GetRequestHeader('x-forwarded-host');
        }

        /**
         * Get the request x forwarded proto header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestXForwardedProto(): string | null
        {
            return Request::GetRequestHeader('x-forwarded-proto');
        }

        /**
         * Get the request x forwarded port header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestXForwardedPort(): string | null
        {
            return Request::GetRequestHeader('x-forwarded-port');
        }

        /**
         * Get the request x real ip header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestXRealIP(): string | null
        {
            return Request::GetRequestHeader('x-real-ip');
        }

        /**
         * Get the request x http method override header, null will be returned if it does not exist
         * @return string|null
         */
        public static function GetRequestXHTTPMethodOverride(): string | null
        {
            return Request::GetRequestHeader('x-http-method-override');
        }
        #endregion



        
        #region ArrayAccess Interface Implimentation

        /**
         * Check if the request has a specific argument
         * @param mixed $offset
         * @return bool
         */ 
        public function offsetExists(mixed $offset): bool
        {
            return $this->postData->has($offset) || $this->getData->has($offset) || $this->jsonData->has($offset) || $this->routedData->has($offset);
        }

        /**
         * Get a specific argument from the request, null will be returned if it does not exist
         * @param mixed $offset
         * @return mixed
         */
        public function offsetGet(mixed $offset): mixed
        {
            if($this->postData->has($offset))
            {
                return $this->postData[$offset];
            }
            if($this->jsonData->has($offset))
            {
                return $this->jsonData[$offset];
            }
            if($this->getData->has($offset))
            {
                return $this->getData[$offset];
            }
            if($this->routedData->has($offset))
            {
                return $this->routedData[$offset];
            }
            return  null;
        }

        /**
         * Set a specific argument in the request, this will throw an exception as request arguments cannot be set directly
         * @param mixed $offset
         * @param mixed $value
         * @throws RouterException
         */
        public function offsetSet(mixed $offset, mixed $value): void
        {
            //request arguments cannot be set directly
            throw(new RouterException());
        }

        /**
         * Unset a specific argument in the request, this will throw an exception as request arguments cannot be unset directly
         * @param mixed $offset
         * @throws RouterException
         */
        public function offsetUnset(mixed $offset): void
        {
            //request arguments cannot be unset directly
            throw(new RouterException());
        }
        #endregion
    }