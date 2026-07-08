<?php

    namespace Wixnit\Queue;

    use Wixnit\Exception\QueueException;
    use Wixnit\Utilities\Convert;
    use Wixnit\Utilities\DateTime;
    use Wixnit\Utilities\Random;

    /**
     * The envelope a driver actually stores/moves around: a job's serialized payload plus
     * the metadata needed to schedule and retry it (id, queue name, attempt count, timing).
     * You generally won't construct this yourself - use `QueuedJob::For()`, or get one back
     * from a driver's pop().
     */
    class QueuedJob
    {
        public string $id;
        public string $queue;
        public string $payload;
        public int $attempts;
        public DateTime $createdAt;
        public DateTime $availableAt;

        public function __construct(string $id, string $queue, string $payload, int $attempts, DateTime $createdAt, DateTime $availableAt)
        {
            $this->id = $id;
            $this->queue = $queue;
            $this->payload = $payload;
            $this->attempts = $attempts;
            $this->createdAt = $createdAt;
            $this->availableAt = $availableAt;
        }

        /**
         * build a new QueuedJob envelope around a job instance
         * @param Job $job
         * @param string $queue
         * @param int $delaySeconds how many seconds from now until the job becomes eligible to run
         * @return QueuedJob
         */
        public static function For(Job $job, string $queue, int $delaySeconds = 0): QueuedJob
        {
            $now = time();

            return new QueuedJob(
                Random::UUID(),
                $queue,
                serialize($job),
                0,
                new DateTime($now),
                new DateTime($now + max($delaySeconds, 0))
            );
        }

        /**
         * is this job currently eligible to run (i.e. its availableAt time has passed)?
         * @return bool
         */
        public function isAvailable(): bool
        {
            return $this->availableAt->toEpochSeconds() <= time();
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
         * convert to a plain array suitable for storing as JSON (used by file-based drivers).
         * The payload is base64-encoded since serialize() output isn't guaranteed to be valid JSON text.
         * @return array
         */
        public function toArray(): array
        {
            return [
                "id" => $this->id,
                "queue" => $this->queue,
                "payload" => Convert::ToBase64($this->payload),
                "attempts" => $this->attempts,
                "createdAt" => $this->createdAt->toEpochSeconds(),
                "availableAt" => $this->availableAt->toEpochSeconds(),
            ];
        }

        /**
         * rebuild a QueuedJob from the array produced by toArray()
         * @param array $data
         * @return QueuedJob
         */
        public static function FromArray(array $data): QueuedJob
        {
            return new QueuedJob(
                $data["id"],
                $data["queue"],
                Convert::FromBase64($data["payload"]),
                $data["attempts"],
                new DateTime($data["createdAt"]),
                new DateTime($data["availableAt"])
            );
        }
    }
