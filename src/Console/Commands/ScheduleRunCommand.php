<?php

    namespace Wixnit\Console\Commands;

    use Wixnit\Console\AsCommand;
    use Wixnit\Console\Command;
    use Wixnit\Schedule\Schedule;

    /**
     * Runs every scheduled task that's due right now, via Schedule::RunDue() - the
     * thing a single "* * * * *" cron entry should invoke every minute:
     *
     *   * * * * * php /app/bin/wixnit schedule:run >> /dev/null 2>&1
     *
     * A scheduled task that should itself run one of this application's own console
     * commands doesn't need a separate shell-out - schedule it as a closure that calls
     * back into the same kernel that's running this command:
     *
     *   Schedule::Call(fn() => $kernel->call('reports:daily'))->dailyAt('06:00');
     *
     * Failures are isolated per task (one bad task never stops the rest from running)
     * and reported as a table at the end - see Schedule::RunDue() for exactly what
     * "isolated" means here.
     */
    #[AsCommand("schedule:run", description: "Run every scheduled task that is currently due")]
    class ScheduleRunCommand extends Command
    {
        public function handle(): int
        {
            $results = Schedule::RunDue();

            if(count($results) === 0)
            {
                $this->io->line("Nothing is due.");
                return self::SUCCESS;
            }

            $failures = 0;

            foreach($results as $result)
            {
                $name = $result["task"]->getName();

                if($result["skipped"])
                {
                    $this->io->line("- {$name} (skipped)");
                    continue;
                }

                if($result["succeeded"])
                {
                    $this->io->success($name);
                    continue;
                }

                $failures++;
                $message = ($result["exception"] !== null) ? $result["exception"]->getMessage() : "failed";
                $this->io->error("{$name}: {$message}");
            }

            $this->io->newLine();
            $this->io->line(count($results)." task(s) due, {$failures} failed.");

            return ($failures === 0) ? self::SUCCESS : self::FAILURE;
        }
    }
