<?php

    namespace Wixnit\Queue\Drivers;

    use PDO;
    use Throwable;
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
     *   $pdo = new PDO("mysql:host=localhost;dbname=app", $user, $pass);
     *   $driver = new DatabaseDriver($pdo);
     *   $driver->createTables(); // once, or manage the tables via your own migrations
     *   Queue::UseDriver($driver);
     *
     * pop() claims a row with a conditional UPDATE ("claim this row only if nobody else
     * already has") rather than an engine-specific row-locking clause like MySQL's
     * `SELECT ... FOR UPDATE` (which SQLite doesn't support the same way) - this is the
     * same atomic-claim idea as FileDriver's rename() trick, just expressed as SQL that
     * works unmodified across MySQL/Postgres/SQLite.
     *
     * Payloads are stored base64-encoded (via QueuedJob::toArray()/FailedJob::toArray(),
     * the same conversion FileDriver already relies on) since serialize() output isn't
     * guaranteed to be safe for every column charset/encoding.
     */
    class DatabaseDriver implements IQueueDriver
    {
        private PDO $pdo;
        private string $jobsTable;
        private string $failedTable;

        /**
         * How many candidate rows pop() will attempt to claim before giving up and
         * returning null - only relevant under heavy contention, where another worker
         * can win the race for a candidate between this driver reading and claiming it.
         */
        private int $maxClaimAttempts;

        public function __construct(PDO $pdo, string $jobsTable = "queue_jobs", string $failedTable = "queue_failed_jobs", int $maxClaimAttempts = 5)
        {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo = $pdo;
            $this->jobsTable = $jobsTable;
            $this->failedTable = $failedTable;
            $this->maxClaimAttempts = max($maxClaimAttempts, 1);
        }

        /**
         * Convenience table creation - safe to call repeatedly. You can just as easily
         * create these tables yourself via your own migrations instead; the exact column
         * types here are intentionally modest (no engine-specific extensions) so this
         * works unmodified across MySQL/Postgres/SQLite.
         * @return void
         */
        public function createTables(): void
        {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->jobsTable} (
                id VARCHAR(64) PRIMARY KEY,
                queue VARCHAR(191) NOT NULL,
                payload TEXT NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                created_at BIGINT NOT NULL,
                available_at BIGINT NOT NULL,
                reserved_at BIGINT NULL
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->failedTable} (
                id VARCHAR(64) PRIMARY KEY,
                queue VARCHAR(191) NOT NULL,
                payload TEXT NOT NULL,
                error TEXT NOT NULL,
                failed_at BIGINT NOT NULL
            )");

            // CREATE INDEX has no portable "IF NOT EXISTS" on every engine (MySQL lacks it
            // entirely) - swallowing a duplicate-index error keeps this method idempotent
            // without needing per-engine branches.
            $this->tryCreateIndex("idx_{$this->jobsTable}_poll", $this->jobsTable, ["queue", "available_at", "reserved_at"]);
        }

        public function push(QueuedJob $job): void
        {
            $data = $job->toArray();

            $stmt = $this->pdo->prepare("INSERT INTO {$this->jobsTable} (id, queue, payload, attempts, created_at, available_at, reserved_at)
                VALUES (:id, :queue, :payload, :attempts, :created_at, :available_at, NULL)");

            $stmt->execute([
                "id" => $data["id"],
                "queue" => $data["queue"],
                "payload" => $data["payload"],
                "attempts" => $data["attempts"],
                "created_at" => $data["createdAt"],
                "available_at" => $data["availableAt"],
            ]);
        }

        public function pop(string $queue): ?QueuedJob
        {
            $now = time();

            for ($attempt = 0; $attempt < $this->maxClaimAttempts; $attempt++) {
                $stmt = $this->pdo->prepare("SELECT id FROM {$this->jobsTable}
                    WHERE queue = :queue AND available_at <= :now AND reserved_at IS NULL
                    ORDER BY created_at ASC LIMIT 1");
                $stmt->execute(["queue" => $queue, "now" => $now]);
                $id = $stmt->fetchColumn();

                if ($id === false) {
                    return null; // nothing eligible waiting at all
                }

                // conditional claim: only succeeds if this row is still unreserved right now -
                // if another worker won the race since the SELECT above, rowCount() is 0 and
                // we just move on to the next candidate rather than stealing their job
                $claim = $this->pdo->prepare("UPDATE {$this->jobsTable} SET reserved_at = :now WHERE id = :id AND reserved_at IS NULL");
                $claim->execute(["now" => $now, "id" => $id]);

                if ($claim->rowCount() === 1) {
                    return $this->find($id);
                }
            }
            return null;
        }

        public function release(QueuedJob $job, int $delaySeconds): void
        {
            $job->availableAt = new DateTime(time() + max($delaySeconds, 0));

            $stmt = $this->pdo->prepare("UPDATE {$this->jobsTable}
                SET reserved_at = NULL, available_at = :available_at, attempts = :attempts
                WHERE id = :id");

            $stmt->execute([
                "available_at" => $job->availableAt->toEpochSeconds(),
                "attempts" => $job->attempts,
                "id" => $job->id,
            ]);
        }

        public function delete(QueuedJob $job): void
        {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->jobsTable} WHERE id = :id");
            $stmt->execute(["id" => $job->id]);
        }

        public function size(string $queue): int
        {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->jobsTable}
                WHERE queue = :queue AND available_at <= :now AND reserved_at IS NULL");
            $stmt->execute(["queue" => $queue, "now" => time()]);

            return (int) $stmt->fetchColumn();
        }

        public function fail(QueuedJob $job, Throwable $exception): void
        {
            $failedJob = FailedJob::From($job, $exception);
            $data = $failedJob->toArray();

            $this->pdo->beginTransaction();

            try {
                $insert = $this->pdo->prepare("INSERT INTO {$this->failedTable} (id, queue, payload, error, failed_at)
                    VALUES (:id, :queue, :payload, :error, :failed_at)");
                $insert->execute([
                    "id" => $data["id"],
                    "queue" => $data["queue"],
                    "payload" => $data["payload"],
                    "error" => $data["error"],
                    "failed_at" => $data["failedAt"],
                ]);

                $delete = $this->pdo->prepare("DELETE FROM {$this->jobsTable} WHERE id = :id");
                $delete->execute(["id" => $job->id]);

                $this->pdo->commit();
            } catch (Throwable $caught) {
                $this->pdo->rollBack();
                throw $caught;
            }
        }

        public function getFailed(): array
        {
            $stmt = $this->pdo->query("SELECT * FROM {$this->failedTable}");
            $ret = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ret[] = FailedJob::FromArray($this->fromFailedRow($row));
            }
            return $ret;
        }

        public function retryFailed(string $id): bool
        {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->failedTable} WHERE id = :id");
            $stmt->execute(["id" => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                return false;
            }

            $this->pdo->beginTransaction();

            try {
                $failedJob = FailedJob::FromArray($this->fromFailedRow($row));
                $this->push($failedJob->toQueuedJob());

                $delete = $this->pdo->prepare("DELETE FROM {$this->failedTable} WHERE id = :id");
                $delete->execute(["id" => $id]);

                $this->pdo->commit();
                return true;
            } catch (Throwable $caught) {
                $this->pdo->rollBack();
                throw $caught;
            }
        }

        public function forgetFailed(string $id): bool
        {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->failedTable} WHERE id = :id");
            $stmt->execute(["id" => $id]);

            return $stmt->rowCount() > 0;
        }

        public function flush(string $queue): int
        {
            // mirrors FileDriver's semantics: only pending (unreserved) jobs are discarded,
            // a job currently held by a worker is left alone
            $stmt = $this->pdo->prepare("DELETE FROM {$this->jobsTable} WHERE queue = :queue AND reserved_at IS NULL");
            $stmt->execute(["queue" => $queue]);

            return $stmt->rowCount();
        }


        #region private helpers

        private function find(string $id): ?QueuedJob
        {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->jobsTable} WHERE id = :id");
            $stmt->execute(["id" => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
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
            try {
                $columnList = implode(", ", $columns);
                $this->pdo->exec("CREATE INDEX {$indexName} ON {$table} ({$columnList})");
            } catch (Throwable) {
                // already exists - fine, this method is meant to be safely re-runnable
            }
        }

        #endregion
    }
