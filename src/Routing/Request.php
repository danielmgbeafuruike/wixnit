<?php

    namespace Wixnit\Routing;

    use ArrayAccess;
    use stdClass;
    use Wixnit\Enum\HTTPMethod;
    use Wixnit\Enum\HTTPResponseCode;
    use Wixnit\Enum\ServerArgs;
    use Wixnit\Exception\RouterException;
    use Wixnit\Validation\Validation;

    class Request implements ArrayAccess
    {
        public HTTPMethod $Method = HTTPMethod::ANY;

        private FormData $getData;
        private FormData $postData;
        private FormData $jsonData;
        private FormData $routedData;

        function __construct(HTTPMethod $method =null, ?FormData $routed_data = null, ?FormData $get_data = null, ?FormData $post_data = null, ?FormData $json_data = null)
        {
            $this->Method = $method ?? HTTPMethod::ANY;
            $this->getData = $get_data ?? new FormData();
            $this->postData = $post_data ?? new FormData();
            $this->jsonData = $json_data ?? new FormData();
            $this->routedData = $routed_data ?? new FormData();
        }


        /**
         * @comment get the IP address of the sender
         */
        public function GetIPAddress(): string
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

        public function GetServer(ServerArgs | string $arg = null): string | array
        {
            $val = (($arg != null) ? (($arg instanceof ServerArgs) ? $arg->value : $arg) : null);
            return ($val != null) ? $_SERVER[$val] : $_SERVER;
        }

        public function Validate(array $validation_args = []): Validation
        {
            $validation = new Validation(array_merge($this->postData->Args, $this->getData->Args, $this->jsonData->Args));
            $validation->addValues($validation_args);
            return $validation;
        }


        /*
        // Send a response back to the caller
        */
        public function Respond(HTTPResponseCode $status, $content)
        {

        }


        /*
        // Get the post form data as an associative array
        */
        public function GetPost(): array
        {
            return $this->postData->Args;
        }


        /*
        // Get the get data received from the request
        */
        public function GetGet(): array
        {
            return $this->getData->Args;
        }


        /*
        // Get the json data received from the request
        */
        public function GetJson(): ?stdClass
        {
            return $this->jsonData->ToJson();
        }


        /*
        // Get the json data received from the request
        */
        public function GetRoutedArgs(): array
        {
            return $this->routedData->Args;
        }



        /*
        // ArrayAccess Interface Implimentation 
        */

        public function offsetExists(mixed $offset): bool
        {
            return $this->postData->Has($offset) || $this->getData->Has($offset) || $this->jsonData->Has($offset) || $this->routedData->Has($offset);
        }
        public function offsetGet(mixed $offset): mixed
        {
            if($this->postData->Has($offset))
            {
                return $this->postData[$offset];
            }
            if($this->jsonData->Has($offset))
            {
                return $this->jsonData[$offset];
            }
            if($this->getData->Has($offset))
            {
                return $this->getData[$offset];
            }
            if($this->routedData->Has($offset))
            {
                return $this->routedData[$offset];
            }
            return  null;
        }
        public function offsetSet(mixed $offset, mixed $value): void
        {
            //request arguments cannot be set directly
            throw(new RouterException());
        }
        public function offsetUnset(mixed $offset): void
        {
            //request arguments cannot be unset directly
            throw(new RouterException());
        }
    }