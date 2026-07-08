<?php

    namespace Wixnit\Queue;

    use Throwable;

    /**
     * Base class for queueable jobs. Extend this, implement handle(), and push instances
     * of it onto a queue:
     *
     *   class SendWelcomeEmail extends Job
     *   {
     *       public function __construct(public string $userId) {}
     *
     *       public function handle(): void
     *       {
     *           // ... send the email ...
     *       }
     *
     *       public function maxAttempts(): int { return 5; }
     *   }
     *
     *   Queue::Push(new SendWelcomeEmail($user->id));
     *
     * Jobs are serialized with PHP's native serialize()/unserialize() to be stored between
     * requests, so keep constructor arguments to simple, serializable data (IDs, strings,
     * arrays) rather than things like open file handles or database connections.
     */
    abstract class Job implements IJob
    {
        /**
         * how many times this job has been attempted so far. Populated by the Worker
         * before handle() is called - you can inspect this inside handle() if your logic
         * needs to behave differently on a retry.
         * @var int
         */
        public int $attempts = 0;

        /**
         * do the actual work. Throw an exception to signal failure.
         * @return void
         */
        abstract public function handle(): void;

        /**
         * which queue this job should go on, if one isn't explicitly given to Queue::Push()/Later().
         * Override to pin a job to a specific queue (e.g. "emails"); return null to just use
         * whatever queue name the caller specifies (defaulting to "default").
         * @return string|null
         */
        public function queue(): ?string
        {
            return null;
        }

        /**
         * the maximum number of times this job will be attempted before it's considered failed
         * @return int
         */
        public function maxAttempts(): int
        {
            return 3;
        }

        /**
         * how long to wait before retrying, given the attempt number that just failed (1-indexed).
         * Defaults to a capped exponential backoff: 10s, 20s, 40s, 80s ... capped at 300s (5 minutes).
         * @param int $attempt
         * @return int seconds to wait before the next attempt
         */
        public function backoffSeconds(int $attempt): int
        {
            return min(10 * (2 ** ($attempt - 1)), 300);
        }

        /**
         * called once this job has exhausted all of its attempts. Override to notify someone,
         * clean up partial work, etc - does nothing by default.
         * @param Throwable $exception the exception from the final failed attempt
         * @return void
         */
        public function failed(Throwable $exception): void
        {
            //no-op by default; override if you need to react to permanent failure
        }
    }
