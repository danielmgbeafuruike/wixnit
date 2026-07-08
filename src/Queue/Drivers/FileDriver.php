<?php

    namespace Wixnit\Queue\Drivers;

    use Throwable;
    use Wixnit\Exception\QueueException;
    use Wixnit\Queue\FailedJob;
    use Wixnit\Queue\IQueueDriver;
    use Wixnit\Queue\QueuedJob;
    use Wixnit\Utilities\DateTime;

    /**
     * Persists jobs to disk as one JSON file per job, so queued work survives between
     * requests/script runs without needing an external service. Layout:
     *
     *   {directory}/{queue}/pending/{id}.job   - waiting to be picked up
     *   {directory}/{queue}/reserved/{id}.job  - currently held by a worker
     *   {directory}/failed/{id}.job            - exhausted all retry attempts
     *
     * pop() claims a job by atomically renaming it from pending/ into reserved/ - if two
     * workers race for the same file, only one rename can succeed. This makes single-machine,
     * multi-process use safe; it is NOT safe across multiple machines sharing a network
     * filesystem with weak rename guarantees (e.g. some NFS setups). For that, use a real
     * message broker instead and implement IQueueDriver against it.
     */
    class FileDriver implements IQueueDriver
    {
        private string $directory;

        public function __construct(?string $directory = null)
        {
            $this->directory = rtrim($directory ?? (sys_get_temp_dir()."/wixnit-queue"), "/");
        }

        public function push(QueuedJob $job): void
        {
            $this->writeJson($this->pendingPath($job->queue, $job->id), $job->toArray());
        }

        public function pop(string $queue): ?QueuedJob
        {
            $pendingDir = $this->pendingDir($queue);
            $this->ensureDirectory($pendingDir);

            $files = glob($pendingDir."/*.job") ?: [];
            $candidates = [];

            for($i = 0; $i < count($files); $i++)
            {
                $data = $this->readJson($files[$i]);
                if(($data !== null) && ($data["availableAt"] <= time()))
                {
                    $candidates[] = ["path" => $files[$i], "data" => $data];
                }
            }

            //first-in-first-out among everything currently eligible
            usort($candidates, fn($a, $b) => $a["data"]["createdAt"] <=> $b["data"]["createdAt"]);

            for($i = 0; $i < count($candidates); $i++)
            {
                $id = $candidates[$i]["data"]["id"];
                $reservedPath = $this->reservedPath($queue, $id);
                $this->ensureDirectory(dirname($reservedPath));

                //rename() is atomic on the same filesystem - if another worker already claimed
                //this file, it will simply no longer exist and the rename fails harmlessly
                if(@rename($candidates[$i]["path"], $reservedPath))
                {
                    return QueuedJob::FromArray($candidates[$i]["data"]);
                }
            }
            return null;
        }

        public function release(QueuedJob $job, int $delaySeconds): void
        {
            $job->availableAt = new DateTime(time() + max($delaySeconds, 0));

            $this->writeJson($this->pendingPath($job->queue, $job->id), $job->toArray());
            @unlink($this->reservedPath($job->queue, $job->id));
        }

        public function delete(QueuedJob $job): void
        {
            @unlink($this->reservedPath($job->queue, $job->id));
        }

        public function size(string $queue): int
        {
            $files = glob($this->pendingDir($queue)."/*.job") ?: [];
            return count($files);
        }

        public function fail(QueuedJob $job, Throwable $exception): void
        {
            $failedJob = FailedJob::From($job, $exception);

            $this->writeJson($this->failedPath($job->id), $failedJob->toArray());
            @unlink($this->reservedPath($job->queue, $job->id));
        }

        public function getFailed(): array
        {
            $failedDir = $this->failedDir();
            $this->ensureDirectory($failedDir);

            $files = glob($failedDir."/*.job") ?: [];
            $ret = [];

            for($i = 0; $i < count($files); $i++)
            {
                $data = $this->readJson($files[$i]);
                if($data !== null)
                {
                    $ret[] = FailedJob::FromArray($data);
                }
            }
            return $ret;
        }

        public function retryFailed(string $id): bool
        {
            $path = $this->failedPath($id);
            $data = $this->readJson($path);

            if($data === null)
            {
                return false;
            }

            $failedJob = FailedJob::FromArray($data);
            $this->push($failedJob->toQueuedJob());
            @unlink($path);

            return true;
        }

        public function forgetFailed(string $id): bool
        {
            $path = $this->failedPath($id);

            if(!is_file($path))
            {
                return false;
            }
            return @unlink($path);
        }

        public function flush(string $queue): int
        {
            $files = glob($this->pendingDir($queue)."/*.job") ?: [];

            for($i = 0; $i < count($files); $i++)
            {
                @unlink($files[$i]);
            }
            return count($files);
        }


        #region private helpers

        private function pendingDir(string $queue): string
        {
            return $this->directory."/".$queue."/pending";
        }

        private function reservedDir(string $queue): string
        {
            return $this->directory."/".$queue."/reserved";
        }

        private function failedDir(): string
        {
            return $this->directory."/failed";
        }

        private function pendingPath(string $queue, string $id): string
        {
            return $this->pendingDir($queue)."/".$id.".job";
        }

        private function reservedPath(string $queue, string $id): string
        {
            return $this->reservedDir($queue)."/".$id.".job";
        }

        private function failedPath(string $id): string
        {
            return $this->failedDir()."/".$id.".job";
        }

        private function ensureDirectory(string $path): void
        {
            if(!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path))
            {
                throw QueueException::DirectoryNotWritable($path);
            }
        }

        private function writeJson(string $path, array $data): void
        {
            $this->ensureDirectory(dirname($path));

            if(file_put_contents($path, json_encode($data), LOCK_EX) === false)
            {
                throw QueueException::DirectoryNotWritable(dirname($path));
            }
        }

        private function readJson(string $path): ?array
        {
            $raw = @file_get_contents($path);
            if($raw === false)
            {
                return null;
            }

            $data = json_decode($raw, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
        }
        #endregion
    }
