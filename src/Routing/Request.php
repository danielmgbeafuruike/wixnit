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
         * @comment get the IP address of the client
         * @return string
         */
        public function getClientIP(): string
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
        public function getServer(ServerArgs | string $arg = null): string | array
        {
            $val = (($arg != null) ? (($arg instanceof ServerArgs) ? $arg->value : $arg) : null);
            return ($val != null) ? $_SERVER[$val] : $_SERVER;
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

        /**
         * Get the base URL of the request
         * @return string
         */
        public function getBaseURL(): string
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
        public function getFullURL(): string
        {
            return $this->getBaseURL() . $_SERVER['REQUEST_URI'];
        }

        /**
         * Get the request headers as an associative array
         * @return string
         */
        public function getRequestHeaders(): array
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
        public function getRequestBody(): string
        {
            return file_get_contents('php://input');
        }

        /**
         * Get a specific cookie value by name, null will be returned if it does not exist
         * @return string
         */
        public function getCookie($name): ?string
        {
            return $_COOKIE[$name] ?? null;
        }

        /**
         * get a specific session value by name, null will be returned if it does not exist
         * @param mixed $name
         * @return mixed
         */
        public function getSession($name): mixed
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            return $_SESSION[$name] ?? null;
        }

        /**
         * get the current server info
         * @return array{document_root: string, request_time: int, script_name: string, server_addr: string, server_name: string, server_port: mixed}
         */
        public function getServerInfo(): array
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
        public function getUserAgent(): string
        {
            return $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        /**
         * get the referrer of the request
         * @return string
         */
        public function getReferrer(): string
        {
            return $_SERVER['HTTP_REFERER'] ?? '';
        }

        /**
         * check if the request is an AJAX request
         * @return bool
         */
        public function isAJAXRequest(): bool
        {
            return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        }

        /**
         * get the request ID, this is a unique identifier for the request
         * @return string
         */
        public function getRequestID(): string
        {
            return uniqid('req_', true);
        }

        /**
         * get the request time, this is the time when the request was made
         * @return int
         */
        public function getRequestTime(): int
        {
            return $_SERVER['REQUEST_TIME'] ?? time();
        }

        /**
         * get the request URI
         * @return string
         */
        public function getRequestURI(): string
        {
            return $_SERVER['REQUEST_URI'] ?? '';
        }

        /**
         * get the request scheme (http or https)
         * @return string
         */
        public function getRequestScheme(): string
        {
            return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        }

        /**
         * get the request port
         * @return int
         */
        public function getRequestPort(): int
        {
            return $_SERVER['SERVER_PORT'] ?? 80;
        }

        /**
         * get the request path
         * @return string
         */
        public function getRequestPath(): string
        {
            return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
        }

        /**
         * get the request query string
         * @return string
         */
        public function getRequestQuery(): string
        {
            return parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '';
        }

        /**
         * get the request fragment
         * @return string
         */
        public function getRequestFragment(): string
        {
            return parse_url($_SERVER['REQUEST_URI'], PHP_URL_FRAGMENT) ?? '';
        }

        /**
         * Get the request headers as an associative array
         * @return array
         */
        public function getRequestHeadersAsArray(): array
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
        public function getRequestHeader($name): ?string
        {
            $headers = $this->getRequestHeadersAsArray();
            return $headers[strtolower($name)] ?? null;
        }

        /**
         * Get the request content type, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestContentType(): ?string
        {
            return $this->getRequestHeader('content-type');
        }

        /**
         * Get the request content length, null will be returned if it does not exist
         * @return int|null
         */
        public function getRequestContentLength(): ?int
        {
            return isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : null;
        }

        /**
         * Get the request accept header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestAccept(): ?string
        {
            return $this->getRequestHeader('accept');
        }

        /**
         * Get the request accept language header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestAcceptLanguage(): ?string
        {
            return $this->getRequestHeader('accept-language');
        }

        /**
         * Get the request accept encoding header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestAcceptEncoding(): ?string
        {
            return $this->getRequestHeader('accept-encoding');
        }

        /**
         * Get the request authorization header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestAuthorization(): ?string
        {
            return $this->getRequestHeader('authorization');
        }

        /**
         * Get the request cache control header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestCacheControl(): ?string
        {
            return $this->getRequestHeader('cache-control');
        }

        /**
         * Get the request if modified since header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestIfModifiedSince(): ?string
        {
            return $this->getRequestHeader('if-modified-since');
        }

        /**
         * Get the request if none match header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestIfNoneMatch(): ?string
        {
            return $this->getRequestHeader('if-none-match');
        }

        /**
         * Get the request forwarded for header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestForwardedFor(): ?string
        {
            return $this->getRequestHeader('x-forwarded-for');
        }

        /**
         * Get the request forwarded proto header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestForwardedProto(): ?string
        {
            return $this->getRequestHeader('x-forwarded-proto');
        }

        /**
         * Get the request forwarded host header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestForwardedHost(): ?string
        {
            return $this->getRequestHeader('x-forwarded-host');
        }

        /**
         * Get the request forwarded port header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestForwardedPort(): ?string
        {
            return $this->getRequestHeader('x-forwarded-port');
        }

        /**
         * Get the request real IP header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestXRequestedWith(): ?string
        {
            return $this->getRequestHeader('x-requested-with');
        }

        /**
         * Get the request x forwarded for header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestXForwardedFor(): ?string
        {
            return $this->getRequestHeader('x-forwarded-for');
        }

        /**
         * Get the request x forwarded host header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestXForwardedHost(): ?string
        {
            return $this->getRequestHeader('x-forwarded-host');
        }

        /**
         * Get the request x forwarded proto header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestXForwardedProto(): ?string
        {
            return $this->getRequestHeader('x-forwarded-proto');
        }

        /**
         * Get the request x forwarded port header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestXForwardedPort(): ?string
        {
            return $this->getRequestHeader('x-forwarded-port');
        }

        /**
         * Get the request x real ip header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestXRealIP(): ?string
        {
            return $this->getRequestHeader('x-real-ip');
        }

        /**
         * Get the request x http method override header, null will be returned if it does not exist
         * @return string|null
         */
        public function getRequestXHTTPMethodOverride(): ?string
        {
            return $this->getRequestHeader('x-http-method-override');
        }



        
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