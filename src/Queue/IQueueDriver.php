<?php

    namespace Wixnit\Queue;

    use Throwable;

    /**
     * The contract a queue backend must implement. Wixnit ships three: `SyncDriver` (runs
     * jobs immediately, no persistence - good for local dev), `MemoryDriver` (in-process
     * only, good for tests), and `FileDriver` (persists to disk, works standalone without
     * any external service). Implement this yourself to back the queue with something else
     * (a database table, Redis, SQS, etc).
     */
    interface IQueueDriver
    {
        /**
         * add a job to the queue, ready to be picked up (immediately, or once its availableAt has passed)
         * @param QueuedJob $job
         * @return void
         */
        public function push(QueuedJob $job): void;

        /**
         * reserve and return the next available job on a queue (its availableAt has passed,
         * and no other worker currently holds it), or null if there's nothing to do right now
         * @param string $queue
         * @return QueuedJob|null
         */
        public function pop(string $queue): ?QueuedJob;

        /**
         * put a previously-popped job back onto its queue after a failed attempt, to be retried
         * after the given delay
         * @param QueuedJob $job
         * @param int $delaySeconds
         * @return void
         */
        public function release(QueuedJob $job, int $delaySeconds): void;

        /**
         * permanently remove a job after it completed successfully
         * @param QueuedJob $job
         * @return void
         */
        public function delete(QueuedJob $job): void;

        /**
         * count how many jobs are currently waiting on a queue
         * @param string $queue
         * @return int
         */
        public function size(string $queue): int;

        /**
         * move a job that exhausted its retry attempts into failed storage
         * @param QueuedJob $job
         * @param Throwable $exception
         * @return void
         */
        public function fail(QueuedJob $job, Throwable $exception): void;

        /**
         * get every job currently sitting in failed storage
         * @return FailedJob[]
         */
        public function getFailed(): array;

        /**
         * move a failed job back onto its original queue for another attempt
         * @param string $id
         * @return bool true if a matching failed job was found and requeued
         */
        public function retryFailed(string $id): bool;

        /**
         * permanently discard a failed job
         * @param string $id
         * @return bool true if a matching failed job was found and discarded
         */
        public function forgetFailed(string $id): bool;

        /**
         * discard every pending job on a queue (jobs already reserved by a worker are left alone)
         * @param string $queue
         * @return int the number of jobs discarded
         */
        public function flush(string $queue): int;
    }
