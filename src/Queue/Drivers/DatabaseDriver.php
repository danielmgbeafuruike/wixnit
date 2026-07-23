<?php

    namespace Wixnit\Queue\Drivers;

    use mysqli;
    use mysqli_sql_exception;
    use mysqli_stmt;
    use Throwable;
    use Wixnit\App\Container;
    use Wixnit\Data\DBConfig;
    use Wixnit\Exception\DatabaseException;
    use Wixnit\Interfaces\IQueueDriver;
    use Wixnit\Queue\FailedJob;
    use Wixnit\Queue\QueuedJob;
    use Wixnit\Utilities\DateTime;

    /**
     * Persists jobs to a database table rather than the filesystem (FileDriver) or memory
     * (MemoryDriver) - the right choice once you have multiple worker processes/machines
     * that need to share one queue, or want queued work to survive independently of any
     * one server's disk.
     *
     * Talks to the database the same way the rest of the framework does - a plain
     * `mysqli` connection, resolved from the same place `DBMigrator`/`Model` already get
     * theirs (`Container::get('db')`, populated by `DBConfig::Init()`) - rather than
     * opening a second connection through a different extension (PDO) with its own DSN,
     * credentials, and error-handling conventions to keep in sync. That mismatch was the
     * whole problem with the driver this replaces: a queue is infrastructure the rest of
     * the app depends on, and infrastructure that speaks a different database client than
     * everything else it runs alongside is exactly the kind of thing that quietly stops
     * working the day someone reconfigures the "real" connection and forgets this one
     * exists.
     *
     *   Queue::UseDriver(new DatabaseDriver());   // resolves the connection lazily via Container::get('db')
     *   Queue::UseDriver(new DatabaseDriver($mysqli));   // or hand it one directly - tests, a second connection, etc.
     *
     * Nothing above needs `createTables()` called explicitly first if the tables already
     * exist elsewhere (your own migrations, or a prior `createTables()` call) - it's
     * offered purely as a convenience, safe to call repeatedly, the same idempotent
     * `CREATE TABLE IF NOT EXISTS` idiom `DBMigrator::mapPivotTables()` already uses for
     * pivot tables elsewhere in the framework.
     *
     * pop() claims a row with a conditional UPDATE ("claim this row only if nobody else
     * already has") rather than an engine-specific row-locking clause like MySQL's
     * `SELECT ... FOR UPDATE` - the same atomic-claim idea as FileDriver's rename() trick,
     * just expressed as SQL.
     *
     * Payloads are stored base64-encoded (via QueuedJob::toArray()/FailedJob::toArray(),
     * the same conversion FileDriver already relies on) since serialize() output isn't
     * guaranteed to be safe for every column charset/encoding, in a LONGTEXT column - a
     * plain TEXT column tops out at 64KB, which a serialized job carrying anything more
     * than a handful of small properties can exceed without any warning until the row
     * silently gets truncated.
     */
    class DatabaseDriver implements IQueueDriver
    {
        private ?mysqli $connection;
        private string $jobsTable;
        private string $failedTable;

        /**
         * How many candidate rows pop() will attempt to claim before giving up and
         * returning null - only relevant under heavy contention, where another worker
         * can win the race for a candidate between this driver reading and claiming it.
         */
        private int $maxClaimAttempts;

        /**
         * @param mysqli|null $connection an existing connection to use, or null to
         *   resolve one lazily (on first actual query, not here in the constructor) via
         *   Container::get('db') - the same connection DBConfig::Init() already set up
         *   for everything else. Passing null is almost always what you want; the
         *   explicit form is mainly for tests, or a queue deliberately backed by a
         *   different database than the rest of the app.
         * @param string $jobsTable
         * @param string $failedTable
         * @param int $maxClaimAttempts
         */
        function __construct(?mysqli $connection = null, string $jobsTable = "queue_jobs", string $failedTable = "queue_failed_jobs", int $maxClaimAttempts = 5)
        {
            $this->connection = $connection;
            $this->jobsTable = $jobsTable;
            $this->failedTable = $failedTable;
            $this->maxClaimAttempts = max($maxClaimAttempts, 1);
        }

        /**
         * Convenience table creation - safe to call repeatedly. You can just as easily
         * create these tables yourself via your own migrations instead; the column types
         * here are intentionally modest (no engine-specific extensions).
         * @return void
         */
        public function createTables(): void
        {
            $this->connection()->query("CREATE TABLE IF NOT EXISTS {$this->jobsTable} (
                id VARCHAR(64) PRIMARY KEY,
                queue VARCHAR(191) NOT NULL,
                payload LONGTEXT NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                created_at BIGINT NOT NULL,
                available_at BIGINT NOT NULL,
                reserved_at BIGINT NULL
            )");

            $this->connection()->query("CREATE TABLE IF NOT EXISTS {$this->failedTable} (
                id VARCHAR(64) PRIMARY KEY,
                queue VARCHAR(191) NOT NULL,
                payload LONGTEXT NOT NULL,
                error TEXT NOT NULL,
                failed_at BIGINT NOT NULL
            )");

            // CREATE INDEX has no portable "IF NOT EXISTS" on MySQL - swallowing a
            // duplicate-index error keeps this method idempotent without needing to
            // first query information_schema to check whether it's already there.
            $this->tryCreateIndex("idx_{$this->jobsTable}_poll", $this->jobsTable, ["queue", "available_at", "reserved_at"]);
        }

        public function push(QueuedJob $job): void
        {
            $data = $job->toArray();

            $this->run(
                __METHOD__,
                "INSERT INTO {$this->jobsTable} (id, queue, payload, attempts, created_at, available_at, reserved_at) VALUES (?, ?, ?, ?, ?, ?, NULL)",
                "sssiii",
                [$data["id"], $data["queue"], $data["payload"], $data["attempts"], $data["createdAt"], $data["availableAt"]],
            );
        }

        public function pop(string $queue): ?QueuedJob
        {
            $now = time();

            for($attempt = 0; $attempt < $this->maxClaimAttempts; $attempt++)
            {
                $stmt = $this->run(
                    __METHOD__,
                    "SELECT id FROM {$this->jobsTable} WHERE queue = ? AND available_at <= ? AND reserved_at IS NULL ORDER BY created_at ASC LIMIT 1",
                    "si",
                    [$queue, $now],
                );

                $row = $stmt->get_result()->fetch_assoc();

                if($row === null)
                {
                    return null; // nothing eligible waiting at all
                }
                $id = $row["id"];

                // conditional claim: only succeeds if this row is still unreserved right now -
                // if another worker won the race since the SELECT above, affected_rows is 0
                // and we just move on to the next candidate rather than stealing their job
                $claim = $this->run(
                    __METHOD__,
                    "UPDATE {$this->jobsTable} SET reserved_at = ? WHERE id = ? AND reserved_at IS NULL",
                    "is",
                    [$now, $id],
                );

                if($claim->affected_rows === 1)
                {
                    return $this->find($id);
                }
            }
            return null;
        }

        public function release(QueuedJob $job, int $delaySeconds): void
        {
            $job->availableAt = new DateTime(time() + max($delaySeconds, 0));

            $this->run(
                __METHOD__,
                "UPDATE {$this->jobsTable} SET reserved_at = NULL, available_at = ?, attempts = ? WHERE id = ?",
                "iis",
                [$job->availableAt->toEpochSeconds(), $job->attempts, $job->id],
            );
        }

        public function delete(QueuedJob $job): void
        {
            $this->run(__METHOD__, "DELETE FROM {$this->jobsTable} WHERE id = ?", "s", [$job->id]);
        }

        public function size(string $queue): int
        {
            $stmt = $this->run(
                __METHOD__,
                "SELECT COUNT(*) AS total FROM {$this->jobsTable} WHERE queue = ? AND available_at <= ? AND reserved_at IS NULL",
                "si",
                [$queue, time()],
            );
            return (int) $stmt->get_result()->fetch_assoc()["total"];
        }

        public function fail(QueuedJob $job, Throwable $exception): void
        {
            $failedJob = FailedJob::From($job, $exception);
            $data = $failedJob->toArray();
            $operation = __METHOD__;

            $this->transaction(function() use ($operation, $data, $job) {
                $this->run(
                    $operation,
                    "INSERT INTO {$this->failedTable} (id, queue, payload, error, failed_at) VALUES (?, ?, ?, ?, ?)",
                    "ssssi",
                    [$data["id"], $data["queue"], $data["payload"], $data["error"], $data["failedAt"]],
                );
                $this->run($operation, "DELETE FROM {$this->jobsTable} WHERE id = ?", "s", [$job->id]);
            });
        }

        public function getFailed(): array
        {
            $stmt = $this->run(__METHOD__, "SELECT * FROM {$this->failedTable} ORDER BY failed_at ASC", "", []);
            $ret = [];

            foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row)
            {
                $ret[] = FailedJob::FromArray($this->fromFailedRow($row));
            }
            return $ret;
        }

        public function retryFailed(string $id): bool
        {
            $stmt = $this->run(__METHOD__, "SELECT * FROM {$this->failedTable} WHERE id = ?", "s", [$id]);
            $row = $stmt->get_result()->fetch_assoc();

            if($row === null)
            {
                return false;
            }

            $operation = __METHOD__;

            $this->transaction(function() use ($operation, $row, $id) {
                $failedJob = FailedJob::FromArray($this->fromFailedRow($row));
                $this->push($failedJob->toQueuedJob());
                $this->run($operation, "DELETE FROM {$this->failedTable} WHERE id = ?", "s", [$id]);
            });
            return true;
        }

        public function forgetFailed(string $id): bool
        {
            $stmt = $this->run(__METHOD__, "DELETE FROM {$this->failedTable} WHERE id = ?", "s", [$id]);
            return $stmt->affected_rows > 0;
        }

        public function flush(string $queue): int
        {
            // mirrors FileDriver's semantics: only pending (unreserved) jobs are discarded,
            // a job currently held by a worker is left alone
            $stmt = $this->run(__METHOD__, "DELETE FROM {$this->jobsTable} WHERE queue = ? AND reserved_at IS NULL", "s", [$queue]);
            return $stmt->affected_rows;
        }

        #region private helpers

        private function find(string $id): ?QueuedJob
        {
            $stmt = $this->run(__METHOD__, "SELECT * FROM {$this->jobsTable} WHERE id = ?", "s", [$id]);
            $row = $stmt->get_result()->fetch_assoc();

            if($row === null)
            {
                return null;
            }

            return QueuedJob::FromArray([
                "id" => $row["id"],
                "queue" => $row["queue"],
                "payload" => $row["payload"],
                "attempts" => (int) $row["attempts"],
                "createdAt" => (int) $row["created_at"],
                "availableAt" => (int) $row["available_at"],
            ]);
        }

        /**
         * @param array $row
         * @return array shaped the way FailedJob::FromArray() expects
         */
        private function fromFailedRow(array $row): array
        {
            return [
                "id" => $row["id"],
                "queue" => $row["queue"],
                "payload" => $row["payload"],
                "error" => $row["error"],
                "failedAt" => (int) $row["failed_at"],
            ];
        }

        private function tryCreateIndex(string $indexName, string $table, array $columns): void
        {
            try
            {
                $columnList = implode(", ", $columns);
                $this->connection()->query("CREATE INDEX {$indexName} ON {$table} ({$columnList})");
            }
            catch(Throwable)
            {
                // already exists - fine, this method is meant to be safely re-runnable
            }
        }

        /**
         * Runs the body of $work inside a transaction, committing on success and rolling
         * back (then re-throwing) on any failure - the same all-or-nothing shape the
         * original driver used PDO's beginTransaction()/commit()/rollBack() for, done
         * through mysqli's equivalents instead.
         * @param callable $work
         * @return void
         */
        private function transaction(callable $work): void
        {
            $db = $this->connection();
            $db->begin_transaction();

            try
            {
                $work();
                $db->commit();
            }
            catch(Throwable $exception)
            {
                $db->rollback();
                throw $exception;
            }
        }

        /**
         * Prepare, bind, and execute a query, wrapping any failure (a failed prepare(), a
         * failed execute(), or an exception mysqli itself throws under its default error
         * report mode) into the same richly-detailed DatabaseException::QueryFailed()
         * every other database operation in the framework already raises - identifying
         * where it happened by $operation, the same way every call site elsewhere in the
         * framework passes its own __METHOD__.
         * @param string $operation typically __METHOD__ from the call site
         * @param string $sql "?"-placeholder SQL
         * @param string $types mysqli bind_param type string ("s"/"i"/"d"/"b" per placeholder) - "" for a query with no placeholders
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

        #endregion
    }
