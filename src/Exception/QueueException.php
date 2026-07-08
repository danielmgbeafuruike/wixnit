<?php

    namespace Wixnit\Exception;

    class QueueException extends WixnitException
    {
        public static function UnserializationFailed(string $jobId): self
        {
            return new self("Failed to reconstruct job '$jobId' from its stored payload - the job's class may no longer exist, or the payload is corrupt.", ["jobId" => $jobId]);
        }

        public static function DirectoryNotWritable(string $path): self
        {
            return new self("Queue storage directory is not writable: '$path'.", ["path" => $path]);
        }

        public static function InvalidJob(string $reason): self
        {
            return new self("Invalid job: $reason.", ["reason" => $reason]);
        }

        public static function PushFailed(string $reason): self
        {
            return new self("Failed to push job onto the queue: $reason.", ["reason" => $reason]);
        }
    }
