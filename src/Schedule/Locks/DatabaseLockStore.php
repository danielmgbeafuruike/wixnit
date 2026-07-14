<?php

    namespace Wixnit\Schedule\Locks;

    use PDO;
    use PDOException;
    use Wixnit\Interfaces\ILockStore;

    /**
     * Shared, multi-server-safe lock store backed by a database table - the correct choice
     * for ScheduledTask::onOneServer() in a real deployment, since FileLockStore only
     * guards against overlap on a single machine.
     *
     * Uses a portable "claim a row" pattern rather than an engine-specific advisory-lock
     * function (e.g. MySQL's GET_LOCK(), Postgres's pg_advisory_lock()) so it works
     * unmodified across MySQL/Postgres/SQLite: acquiring is an INSERT guarded by the
     * primary key, so a second concurrent acquire() naturally fails with an integrity
     * constraint violation; an expired lock is stolen via a conditional UPDATE instead.
     *
     *   $pdo = new PDO(...);
     *   $store = new DatabaseLockStore($pdo);
     *   $store->createTable(); // once, or manage the table via your own migrations
     *   Schedule::UseLockStore($store);
     */
    class DatabaseLockStore implements ILockStore
    {
        private PDO $pdo;
        private string $table;

        public function __construct(PDO $pdo, string $table = "schedule_locks")
        {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo = $pdo;
            $this->table = $table;
        }

        /**
         * Convenience table creation - safe to call repeatedly. You can just as easily
         * create this table yourself (schedule_locks: lock_key varchar primary key,
         * owner varchar, expires_at bigint) via your own migrations instead.
         * @return void
         */
        public function createTable(): void
        {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->table} (
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

            try {
                $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (lock_key, owner, expires_at) VALUES (:key, :owner, :expires)");
                $stmt->execute(["key" => $key, "owner" => $owner, "expires" => $expiresAt]);
                return true;
            } catch (PDOException $exception) {
                // someone already holds this key - steal it only if their lock has expired
                $stmt = $this->pdo->prepare("UPDATE {$this->table} SET owner = :owner, expires_at = :expires WHERE lock_key = :key AND expires_at < :now");
                $stmt->execute(["owner" => $owner, "expires" => $expiresAt, "key" => $key, "now" => $now]);

                return $stmt->rowCount() === 1;
            }
        }

        public function release(string $key): void
        {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE lock_key = :key");
            $stmt->execute(["key" => $key]);
        }

        public function isLocked(string $key): bool
        {
            $stmt = $this->pdo->prepare("SELECT expires_at FROM {$this->table} WHERE lock_key = :key");
            $stmt->execute(["key" => $key]);
            $expiresAt = $stmt->fetchColumn();

            return ($expiresAt !== false) && ((int) $expiresAt >= time());
        }

        private static function ownerToken(): string
        {
            return (gethostname() ?: 'unknown-host') . ':' . getmypid();
        }
    }
