<?php

    namespace Wixnit\Data;

    use Exception;

    class Config
    {
        /**
         * @comment Load the wixnit config file for use in your project
         */
        public static function Load(string $path): void
        {
            if(file_exists($path))
            {
                require_once($path);

                if(function_exists("dbConfig"))
                {
                    $dbconfig = dbConfig();

                    if($dbconfig instanceof DBConfig)
                    {
                        $GLOBALS["WIXNIT_SQL_Connection_Credentials"] = $dbconfig;
                    }
                }
            }
            else
            {
                throw(new Exception("Unable to load config. The config file <b>".$path."</b> those not exist"));
            }
        }

        public static function setDBConfig(DBConfig $dbconfig)
        {
            $GLOBALS["WIXNIT_SQL_Connection_Credentials"] = $dbconfig;
        }
    }