<?php

    namespace Wixnit\Console\Commands;

    use Wixnit\Console\AsCommand;
    use Wixnit\Console\Argument;
    use Wixnit\Console\Command;
    use Wixnit\Console\CommandSignature;

    /**
     * Built into every Kernel - shows a single command's full usage: its synopsis,
     * every #[Argument] and #[Option] it declares, their descriptions, and their
     * defaults. Also what "-h"/"--help" routes to, whether or not a command name was
     * given alongside it.
     */
    #[AsCommand("help", description: "Show a command's full usage, or list every command")]
    class HelpCommand extends Command
    {
        #[Argument(description: "The command to show help for", default: null)]
        public ?string $command = null;

        public function handle(): int
        {
            if($this->command === null)
            {
                return $this->kernel->call("list", [], $this->io);
            }

            $signature = $this->kernel->signatureFor($this->command);

            if($signature === null)
            {
                $this->io->error("Command \"{$this->command}\" is not defined.");
                $this->io->line('Run "list" to see every registered command.');
                return self::INVALID;
            }

            $this->render($signature);
            return self::SUCCESS;
        }

        private function render(CommandSignature $signature): void
        {
            $this->io->info($signature->name);
            if($signature->description !== "")
            {
                $this->io->line($signature->description);
            }
            $this->io->newLine();

            $this->io->line("Usage:");
            $this->io->line("  ".$signature->usage());
            $this->io->newLine();

            if(count($signature->arguments) > 0)
            {
                $this->io->line("Arguments:");
                foreach($signature->arguments as $argument)
                {
                    $suffix = $argument->required ? "" : " [default: ".$this->describe($argument->default)."]";
                    $this->io->line("  ".str_pad($argument->name, 20).$argument->description.$suffix);
                }
                $this->io->newLine();
            }

            if(count($signature->options) > 0)
            {
                $this->io->line("Options:");
                foreach($signature->options as $option)
                {
                    $flags = ($option->shortcut !== null) ? ("-{$option->shortcut}, --{$option->name}") : ("    --{$option->name}");
                    $suffix = $option->isFlag ? "" : " [default: ".$this->describe($option->default)."]";
                    $this->io->line("  ".str_pad($flags, 22).$option->description.$suffix);
                }
            }
        }

        private function describe(mixed $value): string
        {
            if($value === null)
            {
                return "none";
            }
            if(is_bool($value))
            {
                return $value ? "true" : "false";
            }
            if(is_array($value))
            {
                return (count($value) === 0) ? "[]" : implode(", ", $value);
            }
            return (string) $value;
        }
    }
