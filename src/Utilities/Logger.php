<?php

    namespace Wixnit\Utilities;

    use Throwable;
    use Wixnit\Enum\LogLevel;
    use Wixnit\Exception\WixnitException;

    /**
     * A simple leveled file logger. Log files rotate daily by filename (e.g.
     * "2026-07-08.log"), one line per entry:
     *
     *   [2026-07-08 14:03:11] [ERROR] Payment failed {"orderId":"abc123"}
     *
     * Usage:
     *   Logger::UseDirectory(__DIR__."/../storage/logs");
     *   Logger::Info("User logged in", ["userId" => $user->id]);
     *   Logger::Error("Payment failed", ["orderId" => $order->id]);
     */
    class Logger
    {
        private static ?string $directory = null;
        private static LogLevel $minimumLevel = LogLevel::DEBUG;

        /**
         * set the directory log files are written to. If never called, defaults to a
         * "wixnit-logs" folder inside the system's temp directory.
         * @param string $path
         * @return void
         */
        public static function UseDirectory(string $path): void
        {
            Logger::$directory = rtrim($path, "/");
        }

        /**
         * set the minimum level that will actually be written to the log file - anything
         * less severe than this is silently ignored. Defaults to LogLevel::DEBUG (everything logged).
         * @param LogLevel $level
         * @return void
         */
        public static function SetMinimumLevel(LogLevel $level): void
        {
            Logger::$minimumLevel = $level;
        }

        /**
         * log a debug message - fine-grained information useful only while actively developing/debugging
         * @param string $message
         * @param array $context
         * @return void
         */
        public static function Debug(string $message, array $context = []): void
        {
            Logger::Log(LogLevel::DEBUG, $message, $context);
        }

        /**
         * log an informational message - normal, expected events worth a record (a user signed up, a job finished)
         * @param string $message
         * @param array $context
         * @return void
         */
        public static function Info(string $message, array $context = []): void
        {
            Logger::Log(LogLevel::INFO, $message, $context);
        }

        /**
         * log a warning - something unexpected happened, but the application recovered on its own
         * @param string $message
         * @param array $context
         * @return void
         */
        public static function Warning(string $message, array $context = []): void
        {
            Logger::Log(LogLevel::WARNING, $message, $context);
        }

        /**
         * log an error - something failed and needs attention, but the application is still running
         * @param string $message
         * @param array $context
         * @return void
         */
        public static function Error(string $message, array $context = []): void
        {
            Logger::Log(LogLevel::ERROR, $message, $context);
        }

        /**
         * log a critical error - something failed badly enough that the application (or a core part of it) can't continue
         * @param string $message
         * @param array $context
         * @return void
         */
        public static function Critical(string $message, array $context = []): void
        {
            Logger::Log(LogLevel::CRITICAL, $message, $context);
        }

        /**
         * log an exception as an error (or critical, if you pass that level), including its
         * message, location, and cause chain. If it's one of this library's own WixnitException
         * types, its attached structured context is merged in automatically.
         * @param Throwable $exception
         * @param array $context extra context to merge in alongside anything the exception carries
         * @param LogLevel $level
         * @return void
         */
        public static function Exception(Throwable $exception, array $context = [], LogLevel $level = LogLevel::ERROR): void
        {
            $exceptionContext = ($exception instanceof WixnitException) ? $exception->getContext() : [];

            $fullContext = array_merge($exceptionContext, $context, [
                "exception" => get_class($exception),
                "file" => $exception->getFile(),
                "line" => $exception->getLine(),
            ]);

            Logger::Log($level, $exception->getMessage(), $fullContext);
        }

        /**
         * write a log entry at an arbitrary level. All of Debug()/Info()/Warning()/Error()/Critical() funnel through here.
         * @param LogLevel $level
         * @param string $message
         * @param array $context
         * @return void
         */
        public static function Log(LogLevel $level, string $message, array $context = []): void
        {
            if($level->priority() < Logger::$minimumLevel->priority())
            {
                return;
            }

            $directory = Logger::directory();
            if(!is_dir($directory))
            {
                mkdir($directory, 0777, true);
            }

            $line = "[".date("Y-m-d H:i:s")."] [".strtoupper($level->value)."] ".$message;

            if(count($context) > 0)
            {
                $line .= " ".json_encode($context);
            }
            $line .= "\n";

            file_put_contents(Logger::pathFor(), $line, FILE_APPEND | LOCK_EX);
        }

        /**
         * read the most recent lines from a day's log file (defaults to today), optionally
         * filtered to a single level
         * @param int $lines maximum number of matching lines to return, most recent first
         * @param LogLevel|null $level if given, only lines at this level are returned
         * @param string|null $date "Y-m-d" formatted date; defaults to today
         * @return array<string>
         */
        public static function Read(int $lines = 100, ?LogLevel $level = null, ?string $date = null): array
        {
            $path = Logger::pathFor($date);

            if(!is_file($path))
            {
                return [];
            }

            $all = file($path, FILE_IGNORE_NEW_LINES);
            if($all === false)
            {
                return [];
            }

            if($level !== null)
            {
                $needle = "[".strtoupper($level->value)."]";
                $all = array_values(array_filter($all, fn($line) => str_contains($line, $needle)));
            }

            return array_slice(array_reverse($all), 0, $lines);
        }

        /**
         * delete a day's log file (defaults to today)
         * @param string|null $date "Y-m-d" formatted date; defaults to today
         * @return bool
         */
        public static function Clear(?string $date = null): bool
        {
            $path = Logger::pathFor($date);

            if(!is_file($path))
            {
                return false;
            }
            return unlink($path);
        }

        /**
         * delete log files older than the given number of days
         * @param int $keepDays
         * @return int the number of files deleted
         */
        public static function Cleanup(int $keepDays = 30): int
        {
            $directory = Logger::directory();
            if(!is_dir($directory))
            {
                return 0;
            }

            $cutoff = time() - ($keepDays * 86400);
            $files = glob($directory."/*.log");
            $deleted = 0;

            for($i = 0; $i < count($files); $i++)
            {
                if(filemtime($files[$i]) < $cutoff)
                {
                    unlink($files[$i]);
                    $deleted++;
                }
            }
            return $deleted;
        }


        #region private helpers

        /**
         * get the configured (or default) log directory
         * @return string
         */
        private static function directory(): string
        {
            return Logger::$directory ?? (sys_get_temp_dir()."/wixnit-logs");
        }

        /**
         * get the on-disk path for a given day's log file (defaults to today)
         * @param string|null $date
         * @return string
         */
        private static function pathFor(?string $date = null): string
        {
            $date = $date ?? date("Y-m-d");
            return Logger::directory()."/".$date.".log";
        }
        #endregion
    }
