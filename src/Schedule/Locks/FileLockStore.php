<?php

    namespace Wixnit\Schedule\Locks;

    use Wixnit\Interfaces\ILockStore;

    /**
     * Default lock store: one lock file per key, guarded with flock(). Works out of the
     * box with no external service, and is safe for concurrent processes on a single
     * machine - the same guarantee (and the same limitation) as Queue\Drivers\FileDriver:
     * this is NOT safe as a shared lock across multiple machines/a network filesystem
     * with weak rename/lock guarantees. For that (e.g. ScheduledTask::onOneServer() in a
     * real multi-server deployment), use DatabaseLockStore instead.
     */
    class FileLockStore implements ILockStore
    {
        private string $directory;

        /**
         * @var array<string, resource>
         */
        private array $handles = [];

        public function __construct(?string $directory = null)
        {
            $this->directory = rtrim($directory ?? (sys_get_temp_dir() . "/wixnit-schedule-locks"), "/");
        }

        public function acquire(string $key, int $ttlSeconds): bool
        {
            $this->ensureDirectory();
            $path = $this->pathFor($key);

            $handle = @fopen($path, 'c');
            if ($handle === false) {
                return false;
            }

            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);
                return $this->stealIfStale($key, $path, $ttlSeconds);
            }

            ftruncate($handle, 0);
            fwrite($handle, (string) getmypid());
            fflush($handle);
            touch($path);

            $this->handles[$key] = $handle;
            return true;
        }

        public function release(string $key): void
        {
            if (isset($this->handles[$key])) {
                flock($this->handles[$key], LOCK_UN);
                fclose($this->handles[$key]);
                unset($this->handles[$key]);
            }
            @unlink($this->pathFor($key));
        }

        public function isLocked(string $key): bool
        {
            $path = $this->pathFor($key);

            if (!is_file($path)) {
                return false;
            }

            $handle = @fopen($path, 'c');
            if ($handle === false) {
                return true; // can't even check - assume locked/inaccessible
            }

            $couldLock = flock($handle, LOCK_EX | LOCK_NB);
            if ($couldLock) {
                flock($handle, LOCK_UN);
            }
            fclose($handle);

            return !$couldLock;
        }

        /**
         * A lock file older than $ttlSeconds is assumed abandoned by a process that died
         * without releasing it, and is safe to steal - a single retry after removing it,
         * not a loop, to avoid fighting another process doing the same steal at the same instant.
         */
        private function stealIfStale(string $key, string $path, int $ttlSeconds): bool
        {
            $mtime = @filemtime($path);

            if (($mtime === false) || ((time() - $mtime) <= $ttlSeconds)) {
                return false;
            }

            @unlink($path);

            $handle = @fopen($path, 'c');
            if (($handle === false) || !flock($handle, LOCK_EX | LOCK_NB)) {
                if ($handle !== false) {
                    fclose($handle);
                }
                return false;
            }

            ftruncate($handle, 0);
            fwrite($handle, (string) getmypid());
            fflush($handle);
            touch($path);

            $this->handles[$key] = $handle;
            return true;
        }

        private function pathFor(string $key): string
        {
            return $this->directory . '/' . preg_replace('/[^A-Za-z0-9_\-.]/', '_', $key) . '.lock';
        }

        private function ensureDirectory(): void
        {
            if (!is_dir($this->directory)) {
                @mkdir($this->directory, 0777, true);
            }
        }
    }
