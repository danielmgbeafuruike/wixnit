<?php

    namespace Wixnit\Console\Commands;

    use Wixnit\Console\Argument;
    use Wixnit\Console\AsCommand;
    use Wixnit\Console\Command;
    use Wixnit\Console\Option;
    use Wixnit\Utilities\Str;

    /**
     * Scaffolds a new Command class from a stub, generating both the class name and
     * the file from the command's intended invocation name - "reports:daily" becomes
     * ReportsDailyCommand.php, pre-filled with `#[AsCommand("reports:daily", ...)]`.
     * This is the fastest way to see the #[AsCommand]/#[Argument]/#[Option]/$this->io
     * shape in the flesh: run `make:command your:thing` and open what it wrote.
     */
    #[AsCommand("make:command", description: "Scaffold a new console Command class")]
    class MakeCommandCommand extends Command
    {
        use GeneratesFiles;

        #[Argument(description: "The command's invocation name (e.g. reports:daily)")]
        public string $name;

        #[Option(description: "Directory commands are written under")]
        public string $path = "app/Console/Commands";

        #[Option(description: "Namespace commands are written under")]
        public string $namespace = "App\\Console\\Commands";

        #[Option(shortcut: "f", description: "Overwrite the file if it already exists")]
        public bool $force = false;

        public function handle(): int
        {
            $class = $this->classNameFor($this->name);
            $target = rtrim($this->path, "/")."/{$class}.php";

            $ok = $this->generateFromStub("command.stub", $target, [
                "namespace" => $this->namespace,
                "class" => $class,
                "name" => $this->name,
            ], $this->force);

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        private function classNameFor(string $name): string
        {
            $parts = array_filter(preg_split('/[:\-]+/', $name));
            $class = implode("", array_map(fn($part) => Str::StudlyCase($part), $parts));

            return str_ends_with($class, "Command") ? $class : ($class."Command");
        }
    }
