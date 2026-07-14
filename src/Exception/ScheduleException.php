<?php

    namespace Wixnit\Exception;

    use Exception;
    use Throwable;

    class ScheduleException extends Exception
    {
        public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }

        public static function InvalidCronExpression(string $expression): self
        {
            return new self("Invalid cron expression: \"$expression\"");
        }

        public static function NoUpcomingRunFound(string $expression): self
        {
            return new self("Could not find an upcoming run date for cron expression \"$expression\" within the search horizon - it may be impossible to satisfy (e.g. \"February 30th\")");
        }

        public static function MissingTaskName(): self
        {
            return new self("withoutOverlapping()/onOneServer() on a Call() task requires an explicit ->name(\"...\") - a Closure has no stable identity across separate scheduler runs to derive a lock key from");
        }

        public static function UnknownTaskType(string $type): self
        {
            return new self("Unknown scheduled task type \"$type\"");
        }

        public static function CommandFailed(string $command): self
        {
            return new self("Failed to start process for scheduled command: \"$command\"");
        }
    }
