<?php

    namespace Wixnit\Data;

    use Exception;
    use mysqli;

    class DBConfig
    {
        protected string $dbServer = "";
        protected string $dbName = "";
        protected string $dbPassword = "";
        protected string  $dbUsername = "";

        private ?mysqli $conn = null;

        public function Query($query)
        {
            if($this->conn != null)
            {
                $result = $this->conn->query($query);
                $this->conn->Close();
                return $result;
            }
            else
            {
                $db = new mysqli($this->dbServer, $this->dbUsername, $this->dbPassword, $this->dbName);
                $result = $db->query($query);
                $db->Close();
                return $result;
            }
        }

        public function GetConnection(): mysqli
        {
            if($this->conn != null)
            {
                return $this->conn;
            }
            else
            {
                if(($this->dbName != "") && ($this->dbUsername != "") && ($this->dbServer != ""))
                {
                    $this->conn = new mysqli($this->dbServer, $this->dbUsername, $this->dbPassword, $this->dbName);

                    if($this->conn->connect_error)
                    {
                        throw (new \Exception("Could not connect to the database"));
                    }
                    else
                    {
                        return $this->conn;
                    }
                }
                else if(isset($GLOBALS["WIXNIT_SQL_Connection_Credentials"]))
                {
                    

                    if($GLOBALS["WIXNIT_SQL_Connection_Credentials"] instanceof DBConfig)
                    {
                        $globalConfig = $GLOBALS["WIXNIT_SQL_Connection_Credentials"];

                        if(($globalConfig->dbName != "") && ($globalConfig->dbUsername != "") && ($globalConfig->dbServer != ""))
                        {
                            return $GLOBALS["WIXNIT_SQL_Connection_Credentials"]->GetConnection();
                        }
                        else
                        {
                            throw(new Exception("Invalid SQL connection credentials. Unable to intialize mysql Connection"));
                        }
                    }
                }
                throw(new Exception("No SQL connection credentials. Unable to intialize mysql Connection"));
            }
        }

        public static function Init(string $hostname, string $username, string $password, string $database): DBConfig
        {
            $config = new DBConfig();
            $config->dbServer = $hostname;
            $config->dbUsername = $username;
            $config->dbPassword = $password;
            $config->dbName = $database;
            return $config;
        }

        public static function Use(mysqli $mysqli): DBConfig
        {
            $ret = new DBConfig();
            $ret->conn = $mysqli;
            return $ret;
        }
    }