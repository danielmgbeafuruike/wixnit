<?php

    namespace Wixnit\Schedule\Locks;

    use mysqli;
    use mysqli_sql_exception;
    use mysqli_stmt;
    use Wixnit\App\Container;
    use Wixnit\Data\DBConfig;
    use Wixnit\Exception\DatabaseException;
    use Wixnit\Interfaces\ILockStore;

    /**
     * Shared, multi-server-safe lock store backed by a database table - the correct choice
     * for ScheduledTask::onOneServer() in a real deployment, since FileLockStore only
     * guards against overlap on a single machine.
     *
     * Talks to the database the same way the rest of the framework does - a plain
     * `mysqli` connection, resolved from the same place `DBMigrator`/`Model`/
     * `Queue\Drivers\DatabaseDriver` already get theirs (`Container::get('db')`, populated
     * by `DBConfig::Init()`) - rather than a second connection through PDO with its own
     * DSN, credentials, and error-handling conventions to keep in sync with whatever the
     * app's real database config is doing. Same fix, same reasoning, as the queue's own
     * database driver.
     *
     *   Schedule::UseLockStore(new DatabaseLockStore());
     *   (new DatabaseLockStore())->createTable(); // once, or manage the table via your own migrations
     *
     * Uses a portable "claim a row" pattern rather than an engine-specific advisory-lock
     * function (e.g. MySQL's GET_LOCK(), Postgres's pg_advisory_lock()): acquiring is an
     * INSERT guarded by the primary key, so a second concurrent acquire() naturally fails
     * with a duplicate-key error rather than blocking; an expired lock is stolen via a
     * conditional UPDATE instead.
     */
    class DatabaseLockStore implements ILockStore
    {
        /** MySQL's "duplicate entry for key" errno - what a concurrent acquire() collides with */
        private const ER_DUP_ENTRY = 1062;

        private ?mysqli $connection;
        private string $table;

        /**
         * @param mysqli|null $connection an existing connection to use, or null to
         *   resolve one lazily (on first actual query, not here in the constructor) via
         *   Container::get('db') - the same connection DBConfig::Init() already set up
         *   for everything else. Passing null is almost always what you want.
         * @param string $table
         */
        function __construct(?mysqli $connection = null, string $table = "schedule_locks")
        {
            $this->connection = $connection;
            $this->table = $table;
        }

        /**
         * Convenience table creation - safe to call repeatedly. You can just as easily
         * create this table yourself via your own migrations instead.
         * @return void
         */
        public function createTable(): void
        {
            $this->connection()->query("CREATE TABLE IF NOT EXISTS {$this->table} (
                lock_key VARCHAR(191) PRIMARY KEY,
                owner VARCHAR(128) NOT NULL,
                expires_at BIGINT NOT NULL
            )");
        }

        public function acquire(string $key, int $ttlSeconds): bool
        {
            $now = time();
            $owner = self::ownerToken();
            $expiresAt = $now + max($ttlSeconds, 1);

            if($this->tryInsert($key, $owner, $expiresAt))
            {
                return true;
            }

            // someone already holds this key - steal it only if their lock has expired
            $stmt = $this->run(
                __METHOD__,
                "UPDATE {$this->table} SET owner = ?, expires_at = ? WHERE lock_key = ? AND expires_at < ?",
                "sisi",
                [$owner, $expiresAt, $key, $now],
            );
            return $stmt->affected_rows === 1;
        }

        public function release(string $key): void
        {
            $this->run(__METHOD__, "DELETE FROM {$this->table} WHERE lock_key = ?", "s", [$key]);
        }

        public function isLocked(string $key): bool
        {
            $stmt = $this->run(__METHOD__, "SELECT expires_at FROM {$this->table} WHERE lock_key = ?", "s", [$key]);
            $row = $stmt->get_result()->fetch_assoc();

            return ($row !== null) && ((int) $row["expires_at"] >= time());
        }

        /**
         * @param string $key
         * @param string $owner
         * @param int $expiresAt
         * @return bool true if the row was inserted (lock acquired outright); false if it
         *   already existed (someone else holds - or held - this key)
         * @throws DatabaseException on any failure that isn't just "the key already exists"
         */
        private function tryInsert(string $key, string $owner, int $expiresAt): bool
        {
            $sql = "INSERT INTO {$this->table} (lock_key, owner, expires_at) VALUES (?, ?, ?)";

            try
            {
                $stmt = $this->connection()->prepare($sql);

                if($stmt === false)
                {
                    throw DatabaseException::QueryFailed(__METHOD__, $sql, [$key, $owner, $expiresAt], $this->connection()->error, $this->connection()->errno);
                }

                $stmt->bind_param("ssi", $key, $owner, $expiresAt);

                if($stmt->execute() === false)
                {
                    if($stmt->errno === self::ER_DUP_ENTRY)
                    {
                        return false;
                    }
                    throw DatabaseException::QueryFailed(__METHOD__, $sql, [$key, $owner, $expiresAt], $stmt->error, $stmt->errno);
                }
                return true;
            }
            catch(mysqli_sql_exception $exception)
            {
                if($exception->getCode() === self::ER_DUP_ENTRY)
                {
                    return false;
                }
                throw DatabaseException::QueryFailed(__METHOD__, $sql, [$key, $owner, $expiresAt], $exception->getMessage(), $exception->getCode());
            }
        }

        private static function ownerToken(): string
        {
            return (gethostname() ?: "unknown-host").":".getmypid();
        }

        /**
         * Prepare, bind, and execute a query, wrapping any failure into the same
         * richly-detailed DatabaseException::QueryFailed() every other database operation
         * in the framework already raises - see Queue\Drivers\DatabaseDriver::run() for
         * the identical helper this one mirrors.
         * @param string $operation typically __METHOD__ from the call site
         * @param string $sql "?"-placeholder SQL
         * @param string $types mysqli bind_param type string ("s"/"i"/"d"/"b" per placeholder)
         * @param array $params values in placeholder order
         * @return mysqli_stmt
         * @throws DatabaseException
         */
        private function run(string $operation, string $sql, string $types, array $params): mysqli_stmt
        {
            try
            {
                $stmt = $this->connection()->prepare($sql);

                if($stmt === false)
                {
                    throw DatabaseException::QueryFailed($operation, $sql, $params, $this->connection()->error, $this->connection()->errno);
                }
                if($types !== "")
                {
                    $stmt->bind_param($types, ...$params);
                }
                if($stmt->execute() === false)
                {
                    throw DatabaseException::QueryFailed($operation, $sql, $params, $stmt->error, $stmt->errno);
                }
                return $stmt;
            }
            catch(mysqli_sql_exception $exception)
            {
                throw DatabaseException::QueryFailed($operation, $sql, $params, $exception->getMessage(), $exception->getCode());
            }
        }

        /**
         * The connection to use - whatever was passed to the constructor, or, resolved
         * (and cached) the first time it's actually needed, whatever DBConfig::Init()
         * already set up for the rest of the application.
         * @return mysqli
         */
        private function connection(): mysqli
        {
            if($this->connection === null)
            {
                $this->connection = Container::get("db", DBConfig::class)->getConnection();
            }
            return $this->connection;
        }
    }
