<?php

    namespace wixnit\Data;

    use mysqli;

    class DBConfig
    {
        protected string $dbServer = "localhost";
        protected string $dbName = "alphacheq_collections";
        protected string $dbPassword = "";
        protected string  $dbUsername = "root";

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
        }

        public static function Init($hostname, $username, $password, $database): DBConfig
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