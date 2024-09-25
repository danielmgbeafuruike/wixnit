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
        protected ?Pagination $Pagination = null;
        protected ?FilterBuilder $Filters = null;
        protected ?SearchBuilder $Searches = null;
        protected ?Order $Order = null;

        public function Get(Request $req): void {}

        public function Delete(Request $req): void {}

        public function Create(Request $req): void {}

        public function Update(Request $req): void {}

        public function Patch(Request $req): void {}

        public function Head(Request $req): void {}

        public function Option(Request $req): void {}


        /*
        // Utility classes to be used within the controller to controll things 
        */
        protected function CloseConnection(Closure $sendDataTask =null, Closure $backgroundTask = null): void
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