<?php

    namespace Wixnit\Data;

    use Exception;
    use mysqli;
    use mysqli_result;
    use Throwable;
    use Wixnit\Exception\DatabaseException;
    use Wixnit\Utilities\Convert;

    class DBConfig
    {
        protected string $dbServer = "";
        protected string $dbName = "";
        protected string $dbPassword = "";
        protected string  $dbUsername = "";

        private ?mysqli $conn = null;

        /**
         * Execute a query and return it's result
         * @param mixed $query
         * @return bool|mysqli_result
         */
        public function query($query): bool|mysqli_result
        {
            if($this->conn != null)
            {
                $result = $this->conn->query($query);
                $this->conn->close();
                return $result;
            }
            else
            {
                $db = new mysqli($this->dbServer, $this->dbUsername, $this->dbPassword, $this->dbName);
                $result = $db->query($query);
                $db->close();
                return $result;
            }
        }

        /**
         * Get the DB username
         * @return string
         */
        public function getDBUserName()
        {
            return $this->dbUsername;
        }

        /**
         * Get the DB password
         * @return string
         */
        public function getDBPassword(): string
        {
            return $this->dbPassword;
        }

        /**
         * Get the BD server
         * @return string
         */
        public function getServer(): string
        {
            return $this->dbServer;
        }

        /**
         * Get the database name
         * @return string
         */
        public function getDBName(): string
        {
            return $this->dbName;
        }

        /**
         * Get a mysql connection either locally initited, or from the globaly saved credentials
         * @throws \Exception
         * @return mysqli|null
         */
        public function getConnection(): mysqli
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
                        throw (new Exception("Could not connect to the database"));
                    }
                    else
                    {
                        return $this->conn;
                    }
                }
                else if(isset($GLOBALS["WIXNIT_MYSQL_Connection_Credentials"]))
                {
                    try{
                        $cred = $GLOBALS["WIXNIT_MYSQL_Connection_Credentials"];

                        if(is_array($cred))
                        {
                            if(isset($cred['server']) && isset($cred['username']) && isset($cred['password']) && isset($cred['database']))
                            {
                                $this->conn = new mysqli($cred['server'], $cred['username'], $cred['password'], $cred['database']);
                                return $this->conn;
                            }
                            else
                            {
                                throw(DatabaseException::InvalidDatabaseConnectionParameters($cred));
                            }
                        }
                        else
                        {
                            throw(DatabaseException::InvalidDatabaseConnectionParameters($cred));
                        }
                    }
                    catch(Exception $e)
                    {
                        throw($e);
                    }
                }
                throw(new Exception("No SQL connection credentials. Unable to intialize mysql Connection"));
            }
        }

        #region static methods

        /**
         * Initialize a new database collection using the supplied credentials
         * @param string $hostname
         * @param string $username
         * @param string $password
         * @param string $database
         * @return DBConfig
         */
        public static function Init(string $hostname, string $username, string $password, string $database): DBConfig
        {
            $config = new DBConfig();
            $config->dbServer = $hostname;
            $config->dbUsername = $username;
            $config->dbPassword = $password;
            $config->dbName = $database;
            return $config;
        }

        /**
         * Create a DBConfig from an existing mysql connection
         * @param \mysqli $mysqli
         * @return DBConfig
         */
        public static function Use(mysqli $mysqli): DBConfig
        {
            $ret = new DBConfig();
            $ret->conn = $mysqli;
            return $ret;
        }
        #end region
    }