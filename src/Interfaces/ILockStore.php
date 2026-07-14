<?php

    namespace Wixnit\Interfaces;

    /**
     * A pluggable lock backend used by Wixnit\Schedule for withoutOverlapping()/onOneServer().
     * Implementations must guarantee acquire() is atomic - no two concurrent callers should
     * ever both receive true for the same key at the same time.
     */
    interface ILockStore
    {
        /**
         * Attempt to atomically acquire a named lock.
         * @param string $key
         * @param int $ttlSeconds how long the lock is honored before it's considered stale and
         *   safe for another caller to steal - a crash-safety net in case release() never runs
         * @return bool true if acquired, false if another holder currently has it
         */
        public function acquire(string $key, int $ttlSeconds): bool;

        /**
         * Release a lock. Safe to call even if the lock was never held or has already expired.
         * @param string $key
         * @return void
         */
        public function release(string $key): void;

        /**
         * Check whether a key is currently locked, without attempting to acquire it.
         * @param string $key
         * @return bool
         */
        public function isLocked(string $key): bool;
    }
