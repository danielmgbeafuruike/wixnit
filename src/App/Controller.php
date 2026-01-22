<?php

    namespace Wixnit\App;

    use Closure;
    use Exception;
    use Wixnit\Data\FilterBuilder;
    use Wixnit\Data\Order;
    use Wixnit\Data\Pagination;
    use Wixnit\Data\SearchBuilder;
    use Wixnit\Routing\Request;
    use Wixnit\Routing\Response;

    /**
     * @comment initialize requests, call appropriate methods, suppose to contain business logic
     */
    abstract class Controller
    {
        protected ?Pagination $pagination = null;
        protected ?FilterBuilder $filters = null;
        protected ?SearchBuilder $searches = null;
        protected ?Order $order = null;


        protected Response $response;


        function __construct()
        {
            $this->response = new Response();
        }


        /**
         * Receive and process GET requests
         * @param \Wixnit\Routing\Request $req
         * @param array $args
         * @return  Response | null
         */
        public function get(Request $req, array $args=[]): Response | null {
            throw(new Exception("Method Not Implemented in controller"));
        }

        /**
         * Receive and process DELETE request
         * @param \Wixnit\Routing\Request $req
         * @param array $args
         * @return  Response | null
         */
        public function delete(Request $req, array $args=[]): Response | null {
            throw(new Exception("Method Not Implemented in controller"));
        }

        /**
         * Receive and process POST request
         * @param \Wixnit\Routing\Request $req
         * @param array $args
         * @return  Response | null
         */
        public function create(Request $req, array $args=[]): Response | null {
            throw(new Exception("Method Not Implemented in controller"));
        }

        /**
         * Receive and process PUT requests
         * @param \Wixnit\Routing\Request $req
         * @param array $args
         * @return  Response | null
         */
        public function update(Request $req, array $args=[]): Response | null {
            throw(new Exception("Method Not Implemented in controller"));
        }

        /**
         * Receive and process PATCH requests
         * @param \Wixnit\Routing\Request $req
         * @param array $args
         * @return  Response | null
         */
        public function patch(Request $req, array $args=[]): Response | null {
            throw(new Exception("Method Not Implemented in controller"));
        }

        /**
         * Receive and process HEAD requests
         * @param \Wixnit\Routing\Request $req
         * @param array $args
         * @return  Response | null
         */
        public function head(Request $req, array $args=[]): Response | null {
            throw(new Exception("Method Not Implemented in controller"));
        }

        /**
         * Receive and process OPTION requests
         * @param \Wixnit\Routing\Request $req
         * @param array $args
         * @return Response | null
         */
        public function option(Request $req, array $args=[]): Response | null {
            throw(new Exception("Method Not Implemented in controller"));
        }

        /**
         * Receive and process ANY requests
         * @param \Wixnit\Routing\Request $req
         * @param array $args
         * @return Response | null
         */
        public function handle(Request $req, array $args=[]): Response | null {
            throw(new Exception("Method Not Implemented in controller"));
        }


        /**
         * Close the connection and send all the data to the output and then continue processing data in the backgroundTask closure
         * @param \Closure|null $sendDataTask
         * @param \Closure|null $backgroundTask
         * @return void
         */
        protected function closeConnection(Closure | null $sendDataTask =null, Closure | null $backgroundTask = null): void
        {
            // Start output buffering
            ob_start();

            if($sendDataTask != null)
            {
                $sendDataTask();
            }
        
            // Get the size of the output
            $size = ob_get_length();
        
            // Set headers to close the connection
            header('Connection: close');
            header('Content-Length: ' . $size);  // Content-Length is important to notify the client how much data to expect
        
            // Flush the output buffer
            ob_end_flush();
            @ob_flush();
            flush();
        
            // Try to terminate the connection if FastCGI is being used
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        
            // Continue with the background process
            if($backgroundTask != null)
            {
                $backgroundTask();
            }
        }
    }