<?php

    namespace Wixnit\Queue;

    use Throwable;
    use Wixnit\Interfaces\IQueueDriver;

    /**
     * Pulls jobs off a queue (via a driver) and runs them, handling retries with backoff
     * and moving permanently-failed jobs into failed storage. Typically run from a CLI
     * script, e.g.:
     *
     *   $worker = new Worker(new FileDriver());
     *   $worker->work("default", ["maxSeconds" => 50, "sleep" => 2, "stopWhenEmpty" => false]);
     *
     * ...invoked once a minute by cron, or as a long-running daemon process - whichever
     * suits your hosting environment.
     */
    class Worker
    {
        private IQueueDriver $driver;

        public function __construct(IQueueDriver $driver)
        {
            $this->driver = $driver;
        }

        /**
         * process jobs from a queue until a stopping condition is met.
         *
         * @param string $queue
         * @param array $options
         *   - maxJobs (int|null): stop after processing this many jobs. Default: no limit.
         *   - maxSeconds (int|null): stop after running for this long. Default: no limit.
         *   - sleep (int): seconds to wait between polls when the queue is empty and $stopWhenEmpty is false. Default: 1.
         *   - stopWhenEmpty (bool): stop as soon as the queue has nothing available, rather than
         *     polling for new work. Default: true (suited to a cron-triggered "run once" worker).
         *   - onProcessed (callable|null): called as (QueuedJob $job, bool $succeeded, ?Throwable $exception) after each job.
         * @return int the number of jobs processed
         */
        public function work(string $queue = "default", array $options = []): int
        {
            $maxJobs = $options["maxJobs"] ?? null;
            $maxSeconds = $options["maxSeconds"] ?? null;
            $sleepSeconds = $options["sleep"] ?? 1;
            $stopWhenEmpty = $options["stopWhenEmpty"] ?? true;
            $onProcessed = $options["onProcessed"] ?? null;

            $start = time();
            $processed = 0;

            while(true)
            {
                $queuedJob = $this->driver->pop($queue);

                if($queuedJob === null)
                {
                    if($stopWhenEmpty)
                    {
                        break;
                    }
                    sleep($sleepSeconds);
                }
                else
                {
                    [$succeeded, $exception] = $this->process($queuedJob);
                    $processed++;

                    if($onProcessed !== null)
                    {
                        $onProcessed($queuedJob, $succeeded, $exception);
                    }
                }

                if(($maxJobs !== null) && ($processed >= $maxJobs))
                {
                    break;
                }
                if(($maxSeconds !== null) && ((time() - $start) >= $maxSeconds))
                {
                    break;
                }
            }
            return $processed;
        }

        /**
         * process a single job that's already been popped off the queue: run it, and on
         * failure either release it for another attempt (with backoff) or, if it's out of
         * attempts, move it to failed storage.
         * @param QueuedJob $queuedJob
         * @return array{0: bool, 1: Throwable|null} whether it succeeded, and the exception if it didn't
         */
        public function process(QueuedJob $queuedJob): array
        {
            try
            {
                $job = $queuedJob->getJob();
                $job->attempts = $queuedJob->attempts + 1;

                $job->handle();

                $this->driver->delete($queuedJob);
                return [true, null];
            }
            catch(Throwable $exception)
            {
                $queuedJob->attempts++;

                //if the payload itself couldn't be reconstructed, there's no job to ask for
                //maxAttempts()/backoffSeconds() - just fail it outright
                $job = null;
                try { $job = $queuedJob->getJob(); } catch(Throwable) { }

                $maxAttempts = $job?->maxAttempts() ?? 1;

                if($queuedJob->attempts >= $maxAttempts)
                {
                    $job?->failed($exception);
                    $this->driver->fail($queuedJob, $exception);
                }
                else
                {
                    $delay = $job?->backoffSeconds($queuedJob->attempts) ?? 60;
                    $this->driver->release($queuedJob, $delay);
                }
                return [false, $exception];
            }
        }
    }
