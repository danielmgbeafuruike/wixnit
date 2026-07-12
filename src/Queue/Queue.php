<?php

    namespace Wixnit\Queue;

    use Wixnit\Interfaces\IQueueDriver;
    use Wixnit\Queue\Drivers\FileDriver;

    /**
     * The main entry point for the queue system. Configure a driver once (optional - it
     * defaults to a `FileDriver` backed by a temp directory), then push jobs and process them:
     *
     *   Queue::UseDriver(new FileDriver(__DIR__."/../storage/queue")); // optional
     *
     *   Queue::Push(new SendWelcomeEmail($user->id));
     *   Queue::Later(300, new SendReminderEmail($user->id));            // runs in 5 minutes
     *   Queue::Push(new SendWelcomeEmail($user->id), "emails");         // named queue
     *
     *   Queue::Work("default");                                        // process what's available, then stop
     *   Queue::Work("default", ["stopWhenEmpty" => false, "sleep" => 2]); // run as a daemon
     *
     *   Queue::Failed();                                                // inspect failures
     *   Queue::Retry($failedJobId);
     */
    class Queue
    {
        private static ?IQueueDriver $driver = null;

        /**
         * configure the driver the queue uses. If never called, a FileDriver pointed at a
         * temp directory is used automatically the first time it's needed.
         * @param IQueueDriver $driver
         * @return void
         */
        public static function UseDriver(IQueueDriver $driver): void
        {
            Queue::$driver = $driver;
        }

        /**
         * get the currently configured driver (lazily defaulting to a FileDriver)
         * @return IQueueDriver
         */
        public static function Driver(): IQueueDriver
        {
            if(Queue::$driver === null)
            {
                Queue::$driver = new FileDriver();
            }
            return Queue::$driver;
        }

        /**
         * push a job onto the queue to run as soon as a worker picks it up
         * @param Job $job
         * @param string|null $queue defaults to the job's own queue() override, or "default" if neither is set
         * @return string the job's id (useful for logging/tracking)
         */
        public static function Push(Job $job, ?string $queue = null): string
        {
            $queueName = $job->queue() ?? $queue ?? "default";
            $queuedJob = QueuedJob::For($job, $queueName);

            Queue::Driver()->push($queuedJob);
            return $queuedJob->id;
        }

        /**
         * push a job onto the queue, but don't let it run until at least $delaySeconds from now
         * @param int $delaySeconds
         * @param Job $job
         * @param string|null $queue defaults to the job's own queue() override, or "default" if neither is set
         * @return string the job's id
         */
        public static function Later(int $delaySeconds, Job $job, ?string $queue = null): string
        {
            $queueName = $job->queue() ?? $queue ?? "default";
            $queuedJob = QueuedJob::For($job, $queueName, $delaySeconds);

            Queue::Driver()->push($queuedJob);
            return $queuedJob->id;
        }

        /**
         * pop the next available job off a queue yourself, without running a full Worker loop.
         * Remember to call the driver's delete()/release()/fail() on it once you're done -
         * in most cases, Queue::Work() is what you want instead.
         * @param string $queue
         * @return QueuedJob|null
         */
        public static function Pop(string $queue = "default"): ?QueuedJob
        {
            return Queue::Driver()->pop($queue);
        }

        /**
         * count how many jobs are waiting on a queue
         * @param string $queue
         * @return int
         */
        public static function Size(string $queue = "default"): int
        {
            return Queue::Driver()->size($queue);
        }

        /**
         * process jobs from a queue with a Worker. See Worker::work() for the available $options.
         * @param string $queue
         * @param array $options
         * @return int the number of jobs processed
         */
        public static function Work(string $queue = "default", array $options = []): int
        {
            $worker = new Worker(Queue::Driver());
            return $worker->work($queue, $options);
        }

        /**
         * get every job that has exhausted its retry attempts
         * @return FailedJob[]
         */
        public static function Failed(): array
        {
            return Queue::Driver()->getFailed();
        }

        /**
         * move a failed job back onto its original queue for another attempt
         * @param string $id
         * @return bool
         */
        public static function Retry(string $id): bool
        {
            return Queue::Driver()->retryFailed($id);
        }

        /**
         * permanently discard a failed job
         * @param string $id
         * @return bool
         */
        public static function ForgetFailed(string $id): bool
        {
            return Queue::Driver()->forgetFailed($id);
        }

        /**
         * discard every pending job on a queue
         * @param string $queue
         * @return int the number of jobs discarded
         */
        public static function Flush(string $queue = "default"): int
        {
            return Queue::Driver()->flush($queue);
        }
    }
