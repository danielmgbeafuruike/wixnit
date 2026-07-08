<?php

    namespace Wixnit\Queue\Drivers;

    use Throwable;
    use Wixnit\Queue\FailedJob;
    use Wixnit\Queue\IQueueDriver;
    use Wixnit\Queue\QueuedJob;
    use Wixnit\Utilities\DateTime;

    /**
     * Runs jobs immediately, in-process, the moment they're pushed - there's no real
     * queueing, waiting, or persistence involved. There's nothing to `Worker::work()` here;
     * push() has already done the work by the time it returns.
     *
     * Good for local development or tests where you want `Job` classes to run for real,
     * but don't want a background worker in the loop. Jobs that fail are recorded in an
     * in-memory failed list (lost when the request ends) so `Queue::Failed()` still works
     * within a single request/script.
     */
    class SyncDriver implements IQueueDriver
    {
        /**
         * @var array<string, FailedJob>
         */
        private array $failed = [];

        public function push(QueuedJob $job): void
        {
            $this->run($job);
        }

        public function pop(string $queue): ?QueuedJob
        {
            //nothing ever waits - push() already ran the job synchronously
            return null;
        }

        public function release(QueuedJob $job, int $delaySeconds): void
        {
            //no concept of "later" here; just try again right away
            $this->run($job);
        }

        public function delete(QueuedJob $job): void
        {
            //nothing persisted, nothing to delete
        }

        public function size(string $queue): int
        {
            return 0;
        }

        public function fail(QueuedJob $job, Throwable $exception): void
        {
            $this->failed[$job->id] = FailedJob::From($job, $exception);
        }

        public function getFailed(): array
        {
            return array_values($this->failed);
        }

        public function retryFailed(string $id): bool
        {
            if(!isset($this->failed[$id]))
            {
                return false;
            }

            $failedJob = $this->failed[$id];
            unset($this->failed[$id]);

            $this->run($failedJob->toQueuedJob());
            return true;
        }

        public function forgetFailed(string $id): bool
        {
            if(!isset($this->failed[$id]))
            {
                return false;
            }
            unset($this->failed[$id]);
            return true;
        }

        public function flush(string $queue): int
        {
            //nothing pending to flush
            return 0;
        }

        /**
         * run a job's handle() immediately, recording it as failed (with no retry - there's
         * no queue to retry it on later) if it throws
         * @param QueuedJob $queuedJob
         * @return void
         */
        private function run(QueuedJob $queuedJob): void
        {
            $job = $queuedJob->getJob();

            try
            {
                $job->attempts = $queuedJob->attempts + 1;
                $job->handle();
            }
            catch(Throwable $exception)
            {
                $job->failed($exception);
                $this->fail($queuedJob, $exception);
            }
        }
    }
