<?php

    namespace Wixnit\App;

    use Closure;
    use Wixnit\Data\FilterBuilder;
    use Wixnit\Data\Order;
    use Wixnit\Data\Pagination;
    use Wixnit\Data\SearchBuilder;
    use Wixnit\Routing\Request;

    /**
     * @comment initialize requests, call appropriate methods, suppose to contain business logic
     */
    abstract class Controller
    {
        protected ?Pagination $pagination = null;
        protected ?FilterBuilder $filters = null;
        protected ?SearchBuilder $searches = null;
        protected ?Order $order = null;

        public function get(Request $req): void {}

        public function delete(Request $req): void {}

        public function create(Request $req): void {}

        public function update(Request $req): void {}

        public function patch(Request $req): void {}

        public function head(Request $req): void {}

        public function option(Request $req): void {}


        /**
         * Close the connection and send all the data to the output and then continue processing data in the backgroundTask closure
         * @param \Closure|null $sendDataTask
         * @param \Closure|null $backgroundTask
         * @return void
         */
        protected function closeConnection(Closure $sendDataTask =null, Closure $backgroundTask = null): void
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