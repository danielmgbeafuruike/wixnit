<?php

    namespace Wixnit\Schedule;

    use Closure;
    use DateTimeInterface;
    use Throwable;
    use Wixnit\Interfaces\ILockStore;
    use Wixnit\Queue\Job;
    use Wixnit\Schedule\Locks\FileLockStore;
    use Wixnit\Utilities\Logger;

    /**
     * The main entry point for the scheduling system. Register tasks once at boot, then
     * run whatever's due from a single cron entry firing every minute:
     *
     *   Schedule::Call(fn() => Report::generateDaily())->dailyAt('06:00');
     *   Schedule::Job(new CleanupOldSessions())->hourly();
     *   Schedule::Event(new DailyDigestDue())->dailyAt('08:00');
     *   Schedule::Command('php /app/bin/rotate-logs.php')->daily();
     *
     *   // in a script invoked by: * * * * * php /app/bin/scheduler.php
     *   Schedule::RunDue();
     *
     * Job()/Event() tasks are dispatched through your existing Wixnit\Queue\Queue::Push()
     * and Wixnit\Events\Event::Dispatch() respectively - a scheduled Job is just pushed
     * onto the queue at the right time (fast, non-blocking; the actual work happens
     * wherever your Worker is running), and a scheduled Event runs through the normal
     * listener pipeline, including any queued listeners.
     */
    class Schedule
    {
        /**
         * @var ScheduledTask[]
         */
        private static array $tasks = [];

        private static ?ILockStore $lockStore = null;
        private static ?string $timezone = null;

        /**
         * Schedule a closure to run inline at the scheduled time.
         * @param Closure $callback
         * @return ScheduledTask
         */
        public static function Call(Closure $callback): ScheduledTask
        {
            return self::register(ScheduledTask::forCallback($callback));
        }

        /**
         * Schedule a Job to be pushed onto the queue at the scheduled time (via Queue::Push() -
         * runs through your normal Worker/retry/backoff machinery, not inline in the scheduler process).
         * @param Job $job
         * @param string|null $queue defaults to the job's own queue() override, or "default"
         * @return ScheduledTask
         */
        public static function Job(Job $job, ?string $queue = null): ScheduledTask
        {
            return self::register(ScheduledTask::forJob($job, $queue));
        }

        /**
         * Schedule an event to be dispatched (via Event::Dispatch()) at the scheduled time.
         * @param object $event
         * @return ScheduledTask
         */
        public static function Event(object $event): ScheduledTask
        {
            return self::register(ScheduledTask::forEvent($event));
        }

        /**
         * Schedule a shell command to run at the scheduled time.
         * @param string $command
         * @return ScheduledTask
         */
        public static function Command(string $command): ScheduledTask
        {
            return self::register(ScheduledTask::forCommand($command));
        }

        /**
         * Every currently registered task, in registration order.
         * @return ScheduledTask[]
         */
        public static function Tasks(): array
        {
            return self::$tasks;
        }

        /**
         * Remove every registered task. Mainly useful between tests.
         * @return void
         */
        public static function Flush(): void
        {
            self::$tasks = [];
        }

        /**
         * Set the default timezone task schedules are evaluated in (individual tasks can
         * still override this with ->timezone()). If never called, falls back to PHP's
         * own default timezone (date_default_timezone_get()).
         * @param string $timezone
         * @return void
         */
        public static function UseTimezone(string $timezone): void
        {
            self::$timezone = $timezone;
        }

        public static function Timezone(): string
        {
            return self::$timezone ?? date_default_timezone_get();
        }

        /**
         * Configure the lock store used by withoutOverlapping()/onOneServer(). If never
         * called, a FileLockStore pointed at a temp directory is used automatically -
         * fine for single-machine deployments, but NOT sufficient for onOneServer() across
         * multiple servers (see ScheduledTask::onOneServer()) - use
         * Wixnit\Schedule\Locks\DatabaseLockStore for that.
         * @param ILockStore $store
         * @return void
         */
        public static function UseLockStore(ILockStore $store): void
        {
            self::$lockStore = $store;
        }

        public static function LockStore(): ILockStore
        {
            if (self::$lockStore === null) {
                self::$lockStore = new FileLockStore();
            }
            return self::$lockStore;
        }

        /**
         * Run every registered task that's due at $now (defaults to the current moment),
         * in registration order. A single cron entry firing every minute is the intended
         * way to invoke this:
         *
         *   * * * * * php /app/bin/scheduler.php  ->  Schedule::RunDue();
         *
         * One task throwing never prevents the rest from running or being evaluated - each
         * task's own errors are already isolated inside ScheduledTask::run(), and
         * $catchExceptions adds a second layer of isolation around run() itself, so even a
         * bug in the scheduling machinery for one task can't take down the whole tick.
         *
         * @param DateTimeInterface|null $now
         * @param bool $catchExceptions
         * @return array<int, array{task: ScheduledTask, ran: bool, skipped: bool, succeeded: bool|null, exception: Throwable|null}>
         *   one entry per task that was due (skipped tasks - due but blocked by a when()/
         *   skip()/lock - are included with skipped=true; tasks that weren't due at all
         *   aren't included)
         */
        public static function RunDue(?DateTimeInterface $now = null, bool $catchExceptions = true): array
        {
            $results = [];

            foreach (self::$tasks as $task) {
                if (!$task->isDue($now)) {
                    continue;
                }

                if ($catchExceptions) {
                    try {
                        $result = $task->run();
                    } catch (Throwable $exception) {
                        Logger::Exception($exception, ["task" => $task->getName()]);
                        $result = ['ran' => true, 'skipped' => false, 'succeeded' => false, 'exception' => $exception];
                    }
                } else {
                    $result = $task->run();
                }

                if (($result['exception'] ?? null) !== null) {
                    Logger::Exception($result['exception'], ["task" => $task->getName()]);
                }

                $result['task'] = $task;
                $results[] = $result;
            }

            return $results;
        }

        /**
         * A lightweight summary of every registered task - name, cron expression, and next
         * run time - handy for a "php scheduler.php --list" style introspection command.
         * Building this calls getNextRunDate() for each task, which brute-force searches
         * forward through the cron expression, so it's not something to call on every tick.
         * @return array<int, array{name: string, expression: string, description: ?string, nextRun: \DateTimeImmutable}>
         */
        public static function ListTasks(): array
        {
            $ret = [];

            foreach (self::$tasks as $task) {
                $ret[] = [
                    "name" => $task->getName(),
                    "expression" => $task->getExpression(),
                    "description" => $task->getDescription(),
                    "nextRun" => $task->getNextRunDate(),
                ];
            }
            return $ret;
        }

        private static function register(ScheduledTask $task): ScheduledTask
        {
            self::$tasks[] = $task;
            return $task;
        }
    }
