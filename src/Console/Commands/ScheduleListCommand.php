<?php

    namespace Wixnit\Console\Commands;

    use Wixnit\Console\AsCommand;
    use Wixnit\Console\Command;
    use Wixnit\Schedule\Schedule;

    /**
     * A read-only view of every task Schedule::Call()/Job()/Event()/Command() has
     * registered - name, cron expression, description, and when it'll next run.
     * Registering tasks is entirely up to the application's own boot code (typically
     * wherever it also calls DBConfig::Init() etc); this just surfaces what's already
     * there via Schedule::ListTasks().
     */
    #[AsCommand("schedule:list", description: "List every scheduled task and when it will next run")]
    class ScheduleListCommand extends Command
    {
        public function handle(): int
        {
            $tasks = Schedule::ListTasks();

            if(count($tasks) === 0)
            {
                $this->io->warning("No tasks are scheduled.");
                return self::SUCCESS;
            }

            $rows = [];
            foreach($tasks as $task)
            {
                $rows[] = [
                    $task["name"],
                    $task["expression"],
                    $task["description"] ?? "",
                    $task["nextRun"]->format("Y-m-d H:i:s"),
                ];
            }

            $this->io->table(["Name", "Expression", "Description", "Next Run"], $rows);
            $this->io->line(count($tasks)." task(s), timezone ".Schedule::Timezone().".");
            return self::SUCCESS;
        }
    }
