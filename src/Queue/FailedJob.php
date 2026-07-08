<?php

    namespace Wixnit\Queue;

    use Wixnit\Exception\QueueException;
    use Wixnit\Utilities\Convert;
    use Wixnit\Utilities\DateTime;

    /**
     * Represents a job that exhausted all of its retry attempts and was moved to a driver's
     * failed job storage. Returned by `IQueueDriver::getFailed()`.
     */
    class FailedJob
    {
        public string $id;
        public string $queue;
        public string $payload;
        public string $error;
        public DateTime $failedAt;

        public function __construct(string $id, string $queue, string $payload, string $error, DateTime $failedAt)
        {
            $this->id = $id;
            $this->queue = $queue;
            $this->payload = $payload;
            $this->error = $error;
            $this->failedAt = $failedAt;
        }

        /**
         * build a FailedJob from a QueuedJob and the exception that finally sank it
         * @param QueuedJob $job
         * @param \Throwable $exception
         * @return FailedJob
         */
        public static function From(QueuedJob $job, \Throwable $exception): FailedJob
        {
            return new FailedJob($job->id, $job->queue, $job->payload, $exception->getMessage(), new DateTime(time()));
        }

        /**
         * reconstruct the original Job instance from its stored payload
         * @return Job
         * @throws QueueException if the payload can't be unserialized back into a Job
         */
        public function getJob(): Job
        {
            $job = @unserialize($this->payload, ["allowed_classes" => true]);

            if(!($job instanceof Job))
            {
                throw QueueException::UnserializationFailed($this->id);
            }
            return $job;
        }

        /**
         * rebuild this failed job as a fresh, retryable QueuedJob (attempts reset to 0, available now)
         * @return QueuedJob
         */
        public function toQueuedJob(): QueuedJob
        {
            return new QueuedJob($this->id, $this->queue, $this->payload, 0, new DateTime(time()), new DateTime(time()));
        }

        /**
         * convert to a plain array suitable for storing as JSON (used by file-based drivers)
         * @return array
         */
        public function toArray(): array
        {
            return [
                "id" => $this->id,
                "queue" => $this->queue,
                "payload" => Convert::ToBase64($this->payload),
                "error" => $this->error,
                "failedAt" => $this->failedAt->toEpochSeconds(),
            ];
        }

        /**
         * rebuild a FailedJob from the array produced by toArray()
         * @param array $data
         * @return FailedJob
         */
        public static function FromArray(array $data): FailedJob
        {
            return new FailedJob(
                $data["id"],
                $data["queue"],
                Convert::FromBase64($data["payload"]),
                $data["error"],
                new DateTime($data["failedAt"])
            );
        }
    }
