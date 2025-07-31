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
                    dbConfig();
                }
            }
            else
            {
                throw(new Exception("Unable to load config. The config file <b>".$path."</b> those not exist"));
            }
        }

        /**
         * set the DB credentials
         * @param string $userName
         * @param string $password
         * @param string $dataBase
         * @param string $server
         * @return void
         */
        public static function DBCredentials(string $userName, string $password, string $dataBase, string $server="localhost")
        {
            putenv("WIXNIT_MYSQL_Connection_Credentials=".json_encode(['server'=> $server, 'username'=> $userName, 'password'=> $password, 'database'=> $dataBase]));
        }
    }