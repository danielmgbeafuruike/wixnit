<?php

    namespace wixnit\Routing;

    class Path
    {
        public string $Path = "";
        function __construct($path=null)
        {
            if($path != null)
            {
                $this->Path = $path;
            }
        }

        public function isValid(): bool
        {
            if(file_exists($this->Path))
            {
                return true;
            }
            return false;
        }

        /**
         * @throws \Exception
         */
        public function Require()
        {
            if($this->isValid())
            {
                require_once ($this->Path);
            }
            else
            {
                throw (new \Exception("the route <b>\"".$this->Path."\"</b> could not be loaded. The resource could not be found"));
            }
        }
    }