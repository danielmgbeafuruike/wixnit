<?php

    namespace Wixnit\Schedule;

    use Closure;
    use DateTimeImmutable;
    use DateTimeInterface;
    use DateTimeZone;
    use Throwable;
    use Wixnit\Events\Event;
    use Wixnit\Exception\ScheduleException;
    use Wixnit\Queue\Job;
    use Wixnit\Queue\Queue;

    /**
     * One registered scheduled task: what to run, how often, and under what constraints.
     * Built via Schedule::Call()/Job()/Event()/Command() - not constructed directly.
     *
     *   Schedule::Call(fn() => Report::generateDaily())
     *       ->dailyAt('06:00')
     *       ->name('daily-report')
     *       ->withoutOverlapping()
     *       ->onFailure(fn($e) => Logger::Exception($e));
     *
     *   Schedule::Job(new CleanupOldSessions())->hourly();
     *
     *   Schedule::Event(new DailyDigestDue())->dailyAt('08:00');
     *
     *   Schedule::Command('php /app/bin/rotate-logs.php')->daily()->runInBackground();
     */
    class ScheduledTask
    {
        private string $type;
        private mixed $runner;
        private ?string $queueName = null;

        private CronExpression $cron;
        private ?string $timezone = null;

        /** @var Closure[] */
        private array $constraints = [];
        /** @var Closure[] */
        private array $rejections = [];

        private ?Closure $beforeHook = null;
        private ?Closure $afterHook = null;
        private ?Closure $onSuccessHook = null;
        private ?Closure $onFailureHook = null;

        private bool $withoutOverlapping = false;
        private int $overlapExpiryMinutes = 1440;
        private bool $onOneServer = false;

        private ?string $name = null;
        private ?string $description = null;

        private ?string $outputPath = null;
        private bool $appendOutput = false;
        private bool $runInBackground = false;

        private function __construct(string $type, mixed $runner)
        {
            $this->type = $type;
            $this->runner = $runner;
            $this->cron = new CronExpression('* * * * *');
        }

        /**
         * @internal use Schedule::Call() instead
         */
        public static function forCallback(Closure $callback): self
        {
            return new self('call', $callback);
        }

        /**
         * @internal use Schedule::Job() instead
         */
        public static function forJob(Job $job, ?string $queue = null): self
        {
            $task = new self('job', $job);
            $task->queueName = $queue;
            return $task;
        }

        /**
         * @internal use Schedule::Event() instead
         */
        public static function forEvent(object $event): self
        {
            return new self('event', $event);
        }

        /**
         * @internal use Schedule::Command() instead
         */
        public static function forCommand(string $command): self
        {
            return new self('command', $command);
        }



        #region frequency

        /**
         * Set a raw cron expression directly - every named frequency helper below is sugar
         * for this. Accepts the standard @hourly/@daily/@weekly/@monthly/@yearly shorthands too.
         * @param string $expression
         * @return self
         */
        public function cron(string $expression): self
        {
            $this->cron = new CronExpression($expression);
            return $this;
        }

        public function everyMinute(): self
        {
            return $this->cron('* * * * *');
        }

        public function everyTwoMinutes(): self
        {
            return $this->cron('*/2 * * * *');
        }

        public function everyFiveMinutes(): self
        {
            return $this->cron('*/5 * * * *');
        }

        public function everyTenMinutes(): self
        {
            return $this->cron('*/10 * * * *');
        }

        public function everyFifteenMinutes(): self
        {
            return $this->cron('*/15 * * * *');
        }

        public function everyThirtyMinutes(): self
        {
            return $this->cron('0,30 * * * *');
        }

        public function hourly(): self
        {
            return $this->cron('0 * * * *');
        }

        public function hourlyAt(int $minute): self
        {
            return $this->cron("$minute * * * *");
        }

        public function daily(): self
        {
            return $this->cron('0 0 * * *');
        }

        /**
         * @param string $time "HH:MM", 24-hour
         * @return self
         */
        public function dailyAt(string $time): self
        {
            [$hour, $minute] = self::parseTime($time);
            return $this->cron("$minute $hour * * *");
        }

        /**
         * @param string $first "HH:MM"
         * @param string $second "HH:MM"
         * @return self
         */
        public function twiceDaily(string $first = '01:00', string $second = '13:00'): self
        {
            [$hour1, $minute1] = self::parseTime($first);
            [$hour2, $minute2] = self::parseTime($second);

            if ($minute1 !== $minute2) {
                throw ScheduleException::InvalidCronExpression("twiceDaily() requires both times to share the same minute ($first, $second)");
            }
            return $this->cron("$minute1 $hour1,$hour2 * * *");
        }

        public function weekly(): self
        {
            return $this->cron('0 0 * * 0');
        }

        /**
         * @param int|int[] $daysOfWeek 0 (Sunday) through 6 (Saturday)
         * @param string $time "HH:MM"
         * @return self
         */
        public function weeklyOn(int | array $daysOfWeek, string $time = '00:00'): self
        {
            [$hour, $minute] = self::parseTime($time);
            $days = is_array($daysOfWeek) ? implode(',', $daysOfWeek) : (string) $daysOfWeek;
            return $this->cron("$minute $hour * * $days");
        }

        public function monthly(): self
        {
            return $this->cron('0 0 1 * *');
        }

        /**
         * @param int $day 1-31
         * @param string $time "HH:MM"
         * @return self
         */
        public function monthlyOn(int $day = 1, string $time = '00:00'): self
        {
            [$hour, $minute] = self::parseTime($time);
            return $this->cron("$minute $hour $day * *");
        }

        /**
         * @param int $first day of month
         * @param int $second day of month
         * @param string $time "HH:MM"
         * @return self
         */
        public function twiceMonthly(int $first = 1, int $second = 16, string $time = '00:00'): self
        {
            [$hour, $minute] = self::parseTime($time);
            return $this->cron("$minute $hour $first,$second * *");
        }

        /**
         * Runs daily at the given time, but only actually fires on the calendar's last day
         * of that month - cron has no native "last day of month" field, so this runs a
         * daily cron under the hood and adds a when() constraint checking the date.
         * @param string $time "HH:MM"
         * @return self
         */
        public function lastDayOfMonth(string $time = '00:00'): self
        {
            [$hour, $minute] = self::parseTime($time);
            $this->cron("$minute $hour * * *");

            return $this->when(function () {
                $now = new DateTimeImmutable('now', new DateTimeZone($this->resolveTimezone()));
                return ((int) $now->format('j')) === ((int) $now->format('t'));
            });
        }

        public function quarterly(): self
        {
            return $this->cron('0 0 1 1,4,7,10 *');
        }

        public function yearly(): self
        {
            return $this->cron('0 0 1 1 *');
        }

        public function annually(): self
        {
            return $this->yearly();
        }

        /**
         * @param int $month 1-12
         * @param int $day 1-31
         * @param string $time "HH:MM"
         * @return self
         */
        public function yearlyOn(int $month = 1, int $day = 1, string $time = '00:00'): self
        {
            [$hour, $minute] = self::parseTime($time);
            return $this->cron("$minute $hour $day $month *");
        }

        /**
         * Restricts whatever frequency is already set to Monday-Friday only, without
         * disturbing the minute/hour/day/month fields already configured.
         * @return self
         */
        public function weekdays(): self
        {
            return $this->when(fn() => ((int) self::now($this->resolveTimezone())->format('N')) <= 5);
        }

        /**
         * Restricts whatever frequency is already set to Saturday/Sunday only.
         * @return self
         */
        public function weekends(): self
        {
            return $this->when(fn() => ((int) self::now($this->resolveTimezone())->format('N')) >= 6);
        }

        /**
         * Restricts whatever frequency is already set to only fire when the current time of
         * day falls within [$startTime, $endTime).
         * @param string $startTime "HH:MM"
         * @param string $endTime "HH:MM"
         * @return self
         */
        public function between(string $startTime, string $endTime): self
        {
            return $this->when(fn() => self::timeWithinRange(self::now($this->resolveTimezone()), $startTime, $endTime));
        }

        /**
         * The inverse of between() - skips any run whose time of day falls within
         * [$startTime, $endTime).
         * @param string $startTime "HH:MM"
         * @param string $endTime "HH:MM"
         * @return self
         */
        public function unlessBetween(string $startTime, string $endTime): self
        {
            return $this->skip(fn() => self::timeWithinRange(self::now($this->resolveTimezone()), $startTime, $endTime));
        }

        /**
         * Evaluate this task's cron expression (plus timezone) in a specific timezone,
         * independent of Schedule::Timezone()'s process-wide default.
         * @param string $timezone e.g. "America/New_York"
         * @return self
         */
        public function timezone(string $timezone): self
        {
            $this->timezone = $timezone;
            return $this;
        }

        #endregion



        #region constraints

        /**
         * Adds a condition that must return true for this task to run, in addition to being
         * cron-due. Can be called more than once - every when() must pass.
         * @param Closure $callback
         * @return self
         */
        public function when(Closure $callback): self
        {
            $this->constraints[] = $callback;
            return $this;
        }

        /**
         * Adds a condition that, if it returns true, skips this run. Can be called more than
         * once - any skip() being true prevents the task from running.
         * @param Closure $callback
         * @return self
         */
        public function skip(Closure $callback): self
        {
            $this->rejections[] = $callback;
            return $this;
        }

        #endregion



        #region hooks

        public function before(Closure $callback): self
        {
            $this->beforeHook = $callback;
            return $this;
        }

        public function after(Closure $callback): self
        {
            $this->afterHook = $callback;
            return $this;
        }

        public function onSuccess(Closure $callback): self
        {
            $this->onSuccessHook = $callback;
            return $this;
        }

        /**
         * @param Closure $callback receives the Throwable that made the task fail
         * @return self
         */
        public function onFailure(Closure $callback): self
        {
            $this->onFailureHook = $callback;
            return $this;
        }

        #endregion



        #region locking

        /**
         * Prevents this exact task from starting a new run while a previous run of it is
         * still in progress - guards against a slow task still executing when its next
         * scheduled trigger fires.
         *
         * Uses Schedule::LockStore() (a file-based lock by default), keyed by this task's
         * name() - Job()/Event()/Command() tasks default to a name derived from the job/event
         * class or command string, but a Call() (raw Closure) task has no stable identity
         * across separate scheduler process runs, so it MUST be given an explicit name()
         * for this to work.
         *
         * @param int $expiresAfterMinutes crash-safety net: if a previous run's lock is older
         *   than this, it's assumed the process died without releasing it and the lock is
         *   stolen rather than blocking forever
         * @return self
         */
        public function withoutOverlapping(int $expiresAfterMinutes = 1440): self
        {
            $this->withoutOverlapping = true;
            $this->overlapExpiryMinutes = $expiresAfterMinutes;
            return $this;
        }

        /**
         * Prevents more than one server from claiming the SAME scheduled tick for this task -
         * relevant when multiple machines each run their own cron pointed at the same app.
         *
         * IMPORTANT: this is only meaningful with a lock store that's actually shared across
         * those machines (e.g. Wixnit\Schedule\Locks\DatabaseLockStore pointed at a database
         * every server can reach). The default FileLockStore is per-machine - if
         * Schedule::LockStore() is never overridden, onOneServer() will not do anything useful
         * in a true multi-server deployment, since each machine has its own independent lock file.
         * @return self
         */
        public function onOneServer(): self
        {
            $this->onOneServer = true;
            return $this;
        }

        #endregion



        #region identity, output, process behavior

        /**
         * A stable identifier for this task - required if withoutOverlapping()/onOneServer()
         * is used on a Call() task (see those methods), and used to label this task in
         * Schedule::RunDue()'s results and in logged exceptions either way.
         * @param string $name
         * @return self
         */
        public function name(string $name): self
        {
            $this->name = $name;
            return $this;
        }

        public function description(string $description): self
        {
            $this->description = $description;
            return $this;
        }

        /**
         * For Command() tasks: write the command's combined stdout/stderr to a file.
         * @param string $path
         * @return self
         */
        public function sendOutputTo(string $path): self
        {
            $this->outputPath = $path;
            $this->appendOutput = false;
            return $this;
        }

        /**
         * Same as sendOutputTo(), but appends rather than overwriting.
         * @param string $path
         * @return self
         */
        public function appendOutputTo(string $path): self
        {
            $this->outputPath = $path;
            $this->appendOutput = true;
            return $this;
        }

        /**
         * For Command() tasks only: start the process and return immediately rather than
         * waiting for it to finish. POSIX/Unix-like OSes only (backgrounds the process via
         * a trailing shell "&") - not supported on Windows.
         * @param bool $background
         * @return self
         */
        public function runInBackground(bool $background = true): self
        {
            $this->runInBackground = $background;
            return $this;
        }

        #endregion



        /**
         * Is this task due to run at the given moment (cron-due only - doesn't evaluate
         * when()/skip() constraints; see run(), which checks both)?
         * @param DateTimeInterface|null $now defaults to the current time
         * @return bool
         */
        public function isDue(?DateTimeInterface $now = null): bool
        {
            $timezone = new DateTimeZone($this->resolveTimezone());
            $moment = ($now !== null)
                ? DateTimeImmutable::createFromInterface($now)->setTimezone($timezone)
                : self::now($this->resolveTimezone());

            return $this->cron->isDue($moment);
        }

        /**
         * The next time (at or after now) this task's cron expression will match - does not
         * account for when()/skip() constraints, which can only be evaluated at the moment
         * they'd actually run.
         * @return DateTimeImmutable
         */
        public function getNextRunDate(): DateTimeImmutable
        {
            return $this->cron->nextRunDate(null, new DateTimeZone($this->resolveTimezone()));
        }

        public function getExpression(): string
        {
            return $this->cron->getExpression();
        }

        public function getDescription(): ?string
        {
            return $this->description;
        }

        /**
         * This task's resolved name - explicit name() if set, otherwise a type-appropriate
         * default (job/event class name, or a hash of the command string). Never throws:
         * falls back to a generic placeholder for an unnamed Call() task, since this is
         * used for display/logging where failing to produce a name shouldn't itself be fatal
         * (unlike resolveName(), which is strict and used internally by the locking methods).
         * @return string
         */
        public function getName(): string
        {
            try {
                return $this->resolveName();
            } catch (Throwable) {
                return $this->description ?? ('unnamed ' . $this->type . ' task');
            }
        }

        /**
         * Run this task right now, unconditionally (does not check isDue()) - checks
         * when()/skip() constraints and locking first, then executes and runs hooks.
         * @return array{ran: bool, skipped: bool, succeeded: bool|null, exception: Throwable|null}
         */
        public function run(): array
        {
            if (!$this->passesConstraints()) {
                return ['ran' => false, 'skipped' => true, 'succeeded' => null, 'exception' => null];
            }

            $overlapLockKey = null;
            $serverLockKey = null;

            if ($this->withoutOverlapping) {
                $overlapLockKey = 'schedule:overlap:' . $this->resolveName();

                if (!Schedule::LockStore()->acquire($overlapLockKey, $this->overlapExpiryMinutes * 60)) {
                    return ['ran' => false, 'skipped' => true, 'succeeded' => null, 'exception' => null];
                }
            }

            if ($this->onOneServer) {
                $serverLockKey = 'schedule:server:' . $this->resolveName() . ':' . intdiv(time(), 60);

                if (!Schedule::LockStore()->acquire($serverLockKey, 90)) {
                    if ($overlapLockKey !== null) {
                        Schedule::LockStore()->release($overlapLockKey);
                    }
                    return ['ran' => false, 'skipped' => true, 'succeeded' => null, 'exception' => null];
                }
            }

            $exception = null;
            $succeeded = false;

            try {
                if ($this->beforeHook !== null) {
                    ($this->beforeHook)();
                }

                $output = $this->execute();

                if (($this->outputPath !== null) && ($output !== null) && ($output !== '')) {
                    $this->writeOutput($output);
                }

                $succeeded = true;

                if ($this->onSuccessHook !== null) {
                    ($this->onSuccessHook)();
                }
            } catch (Throwable $caught) {
                $exception = $caught;

                if ($this->onFailureHook !== null) {
                    ($this->onFailureHook)($caught);
                }
            } finally {
                if ($this->afterHook !== null) {
                    ($this->afterHook)();
                }
                if ($overlapLockKey !== null) {
                    Schedule::LockStore()->release($overlapLockKey);
                }
                if ($serverLockKey !== null) {
                    Schedule::LockStore()->release($serverLockKey);
                }
            }

            return ['ran' => true, 'skipped' => false, 'succeeded' => $succeeded, 'exception' => $exception];
        }


        #region internals

        private function passesConstraints(): bool
        {
            foreach ($this->constraints as $constraint) {
                if (!$constraint()) {
                    return false;
                }
            }
            foreach ($this->rejections as $rejection) {
                if ($rejection()) {
                    return false;
                }
            }
            return true;
        }

        private function execute(): ?string
        {
            switch ($this->type) {
                case 'call':
                    ($this->runner)();
                    return null;

                case 'job':
                    Queue::Push($this->runner, $this->queueName);
                    return null;

                case 'event':
                    Event::Dispatch($this->runner);
                    return null;

                case 'command':
                    return $this->runCommand();
            }
            throw ScheduleException::UnknownTaskType($this->type);
        }

        private function runCommand(): string
        {
            $command = $this->runner;

            if ($this->runInBackground) {
                $redirect = ($this->outputPath !== null)
                    ? ' ' . ($this->appendOutput ? '>>' : '>') . ' ' . escapeshellarg($this->outputPath) . ' 2>&1'
                    : ' > /dev/null 2>&1';

                shell_exec($command . $redirect . ' &');
                return '';
            }

            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

            if (!is_resource($process)) {
                throw ScheduleException::CommandFailed($command);
            }

            $stdout = stream_get_contents($pipes[1]) ?: '';
            $stderr = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $stdout . $stderr;
        }

        private function writeOutput(string $output): void
        {
            $flags = $this->appendOutput ? (FILE_APPEND | LOCK_EX) : LOCK_EX;
            @file_put_contents($this->outputPath, $output, $flags);
        }

        /**
         * @throws ScheduleException if this is an unnamed Call() task - see withoutOverlapping()
         */
        private function resolveName(): string
        {
            if ($this->name !== null) {
                return $this->name;
            }

            return match ($this->type) {
                'job', 'event' => get_class($this->runner),
                'command' => 'command:' . md5($this->runner),
                'call' => throw ScheduleException::MissingTaskName(),
            };
        }

        private function resolveTimezone(): string
        {
            return $this->timezone ?? Schedule::Timezone();
        }

        private static function now(string $timezone): DateTimeImmutable
        {
            return new DateTimeImmutable('now', new DateTimeZone($timezone));
        }

        /**
         * @return array{0: int, 1: int} [hour, minute]
         */
        private static function parseTime(string $time): array
        {
            $parts = explode(':', $time, 2);

            if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
                throw ScheduleException::InvalidCronExpression("Invalid time \"$time\", expected \"HH:MM\"");
            }
            return [(int) $parts[0], (int) $parts[1]];
        }

        private static function timeWithinRange(DateTimeImmutable $now, string $startTime, string $endTime): bool
        {
            [$startHour, $startMinute] = self::parseTime($startTime);
            [$endHour, $endMinute] = self::parseTime($endTime);

            $nowMinutes = ((int) $now->format('H')) * 60 + ((int) $now->format('i'));
            $startMinutes = ($startHour * 60) + $startMinute;
            $endMinutes = ($endHour * 60) + $endMinute;

            if ($startMinutes <= $endMinutes) {
                return ($nowMinutes >= $startMinutes) && ($nowMinutes < $endMinutes);
            }
            // range wraps past midnight, e.g. between('22:00', '06:00')
            return ($nowMinutes >= $startMinutes) || ($nowMinutes < $endMinutes);
        }

        #endregion
    }
