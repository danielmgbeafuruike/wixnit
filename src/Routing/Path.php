<?php

    namespace Wixnit\Routing;

    class Path
    {
        public string $path = "";
        function __construct($path=null)
        {
            if($path != null)
            {
                $this->path = $path;
            }
        }

        /**
         * Check if the path is valid and the file or directory exists
         * @return bool
         */
        public function exist(): bool
        {
            if(file_exists($this->path))
            {
                return true;
            }
            return false;
        }

        
        /**
         * Require the file at the path if it is valid
         * @return void
         * @throws \Exception
         */
        public function require(): void
        {
            if($this->exist())
            {
                require_once ($this->path);
            }
            else
            {
                throw (new \Exception("the route <b>\"".$this->path."\"</b> could not be loaded. The resource could not be found"));
            }
        }

        /**
         * resolve the path of a resource relative to the current route
         * @param mixed $path
         * @param mixed $pathInfo
         * @return string
         */
        public static function ResolvePath($path, $pathInfo=null): string
        {
            $ret = $path;
            $prepend = "";
            $extra = false;

            if($pathInfo === null)
            {
                if((isset($_SERVER['PATH_INFO'])) ||(isset($_SERVER['ORIG_PATH_INFO'])))
                {
                    $tmp = isset($_REQUEST['ORIG_PATH_INFO']) ? explode("/", trim($_SERVER['ORIG_PATH_INFO'])) : explode("/", trim($_SERVER['PATH_INFO']));

                    if($tmp[(count($tmp) - 1)] == "")
                    {
                        $extra = true;
                    }

                    $ds = isset($_SERVER['ORIG_PATH_INFO']) ? explode("/", trim($_SERVER['ORIG_PATH_INFO'], "/")) : explode("/", trim($_SERVER['PATH_INFO'], "/"));
                    for($i = 0; $i < (count($ds) - 1); $i++)
                    {
                        $prepend .= "../";
                    }
                }
            }
            else
            {
                if((isset($pathInfo)) && ($pathInfo != ""))
                {
                    $tmp = explode("/", $pathInfo);

                    if($tmp[(count($tmp) - 1)] == "")
                    {
                        $extra = true;
                    }

                    $ds = explode("/", trim($pathInfo, "/"));
                    for($i = 0; $i < (count($ds) - 1); $i++)
                    {
                        $prepend .= "../";
                    }
                }
            }

            if($extra)
            {
                $ret = "../".$ret;
            }

            return $prepend.$ret;
        }
    }