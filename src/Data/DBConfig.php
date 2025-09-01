<?php

namespace Wixnit\Data;

use Exception;
use mysqli;
use mysqli_result;
use Wixnit\Exception\DatabaseException;
use Wixnit\App\Container; // <- our service container

class DBConfig
{
    protected string $dbServer   = "";
    protected string $dbName     = "";
    protected string $dbPassword = "";
    protected string $dbUsername = "";

    private ?mysqli $conn = null;


    function __construct()
    {
        // Ensure connection is closed when script shuts down
        register_shutdown_function(function () {
            //$this->close();
        });
    }


    /**
     * Destructor: auto-close connection if still open
     */
    public function __destruct()
    {
        //$this->close();
    }

    /**
     * Execute a query and return its result
     * @param string $query
     * @return bool|mysqli_result
     * @throws Exception
     */
    public function query(string $query): bool|mysqli_result
    {
        $conn = $this->getConnection();
        $result = $conn->query($query);

        if ($result === false) {
            throw new DatabaseException("Query failed: " . $conn->error);
        }

        return $result;
    }

    /**
     * Get or create a mysqli connection.
     * Uses the container as a fallback if no credentials are set.
     *
     * @return mysqli
     * @throws Exception
     */
    public function getConnection(): mysqli
    {
        // Reuse existing connection
        if ($this->conn instanceof mysqli) {
            return $this->conn;
        }

        // If credentials are set, create a new connection
        if ($this->dbName !== "" && $this->dbUsername !== "" && $this->dbServer !== "") {
            $this->conn = $this->createConnection(
                $this->dbServer,
                $this->dbUsername,
                $this->dbPassword,
                $this->dbName
            );
            return $this->conn;
        }

        // Otherwise, check the container
        if (Container::has('db')) {
            $config = Container::get('db');
            if ($config instanceof DBConfig) {
                $this->conn = $config->getConnection();
                return $this->conn;
            }
        }

        // Finally, try global fallback
        if (isset($GLOBALS["WIXNIT_MYSQL_Connection_Credentials"])) {
            $cred = $GLOBALS["WIXNIT_MYSQL_Connection_Credentials"];
            if (
                is_array($cred)
                && isset($cred['server'], $cred['username'], $cred['password'], $cred['database'])
            ) {
                $this->conn = $this->createConnection($cred['server'], $cred['username'], $cred['password'], $cred['database']);
                Container::set('db', $this);
                return $this->conn;
            }
            throw DatabaseException::InvalidDatabaseConnectionParameters($cred);
        }

        throw new Exception("No SQL connection credentials available.");
    }

    /**
     * Internal helper for creating mysqli connections
     */
    private function createConnection(string $server, string $user, string $pass, string $db): mysqli
    {
        $conn = @new mysqli($server, $user, $pass, $db);

        if ($conn->connect_errno) {
            throw new DatabaseException(
                "Could not connect to database ($server/$db): " . $conn->connect_error
            );
        }
        return $conn;
    }

    /**
     * Close the current connection (optional, call at shutdown)
     */
    public function close(): void
    {
        if ($this->conn instanceof mysqli) {
            $this->conn->close();
            $this->conn = null;
        }
    }

    #region static methods

    /**
     * Initialize a new database configuration
     */
    public static function Init(string $hostname, string $username, string $password, string $database): DBConfig
    {
        $config = new DBConfig();
        $config->dbServer   = $hostname;
        $config->dbUsername = $username;
        $config->dbPassword = $password;
        $config->dbName     = $database;

        // Store in container for reuse
        Container::set('db', $config);

        return $config;
    }

    /**
     * Wrap an existing mysqli connection
     */
    public static function Use(mysqli $mysqli): DBConfig
    {
        $ret = new DBConfig();
        $ret->conn = $mysqli;

        // Store in container for reuse
        Container::set('db', $ret);

        return $ret;
    }

    #endregion
}
