<?php

    namespace Wixnit\App;

    use Exception;
    use Wixnit\Routing\Request;

    class View
    {
        private $filePath = "";

        function __construct($file_path=null)
        {
            $this->filePath = $file_path;
        }

        /**
         * @throws Exception
         */
        public function render(Request $req): void
        {
            $full = ($this->filePath.(file_exists($this->filePath) ? "" : (file_exists($this->filePath.".php") ? ".php" : ".phtml")));

            if(file_exists($full))
            {
                require_once($full);
            }
            else
            {
                throw(new Exception("the view \"".array_reverse(explode("/", $this->filePath))[0]."\" was not found"));
            }
        }
    }