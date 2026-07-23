<?php

    namespace Wixnit\Console\Commands;

    use Wixnit\Console\AsCommand;
    use Wixnit\Console\Command;
    use Wixnit\Console\CommandMap;

    /**
     * Built into every Kernel - lists every registered command, grouped by the part of
     * its name before the first ":" (so "make:model"/"make:controller" both land under
     * "make"). Running the kernel with no arguments at all does exactly this.
     */
    #[AsCommand("list", description: "List every registered command")]
    class ListCommand extends Command
    {
        public function handle(): int
        {
            $groups = [];

            foreach($this->kernel->commands() as $name => $class)
            {
                $signature = CommandMap::forClass($class);
                $groups[$signature->group()][] = $signature;
            }

            ksort($groups);

            $this->io->line("Wixnit CLI");
            $this->io->newLine();
            $this->io->line("Usage:");
            $this->io->line("  command [arguments] [options]");
            $this->io->newLine();

            foreach($groups as $groupName => $signatures)
            {
                usort($signatures, fn($a, $b) => $a->name <=> $b->name);

                $this->io->info(strtoupper($groupName));
                foreach($signatures as $signature)
                {
                    $this->io->line("  ".str_pad($signature->name, 24)."  ".$signature->description);
                }
                $this->io->newLine();
            }

            $this->io->line('Run "help <command>" for full usage on any of the above.');
            return self::SUCCESS;
        }
    }
