<?php

    namespace Wixnit\Queue\Drivers;

    use Throwable;
    use Wixnit\Queue\FailedJob;
    use Wixnit\Queue\IQueueDriver;
    use Wixnit\Queue\QueuedJob;
    use Wixnit\Utilities\DateTime;

    /**
     * Keeps queued and failed jobs in plain PHP arrays, entirely in-process. Nothing is
     * persisted - state is gone as soon as the request/script ends. Useful for unit tests,
     * or anywhere you want the real push/pop/retry/worker mechanics without touching disk.
     */
    class MemoryDriver implements IQueueDriver
    {
        /**
         * @var array<string, QueuedJob[]>
         */
        private array $queues = [];

        /**
         * @var array<string, FailedJob>
         */
        private array $failed = [];

        public function push(QueuedJob $job): void
        {
            if(!isset($this->queues[$job->queue]))
            {
                $this->queues[$job->queue] = [];
            }
            $this->queues[$job->queue][] = $job;
        }

        public function pop(string $queue): ?QueuedJob
        {
            if(empty($this->queues[$queue]))
            {
                return null;
            }

            $jobs = $this->queues[$queue];

            //pick the earliest-available, earliest-created job (first-in-first-out among what's eligible)
            $bestIndex = null;
            for($i = 0; $i < count($jobs); $i++)
            {
                if(!$jobs[$i]->isAvailable())
                {
                    continue;
                }

                if(($bestIndex === null) || ($jobs[$i]->createdAt->toEpochSeconds() < $jobs[$bestIndex]->createdAt->toEpochSeconds()))
                {
                    $bestIndex = $i;
                }
            }

            if($bestIndex === null)
            {
                return null;
            }

            $job = $jobs[$bestIndex];
            array_splice($this->queues[$queue], $bestIndex, 1);

            return $job;
        }

        public function release(QueuedJob $job, int $delaySeconds): void
        {
            $job->availableAt = new DateTime(time() + max($delaySeconds, 0));
            $this->push($job);
        }

        public function delete(QueuedJob $job): void
        {
            //already removed from the in-memory queue when it was popped - nothing further to do
        }

        public function size(string $queue): int
        {
            return count($this->queues[$queue] ?? []);
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

            $this->push($failedJob->toQueuedJob());
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
            $count = count($this->queues[$queue] ?? []);
            $this->queues[$queue] = [];
            return $count;
        }
    }
