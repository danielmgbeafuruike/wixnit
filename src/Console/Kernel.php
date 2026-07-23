<?php

    namespace Wixnit\Console;

    use Throwable;
    use Wixnit\Console\Commands\HelpCommand;
    use Wixnit\Console\Commands\ListCommand;
    use Wixnit\Exception\ConsoleException;

    /**
     * The console equivalent of Router - register() a command class, then run() the
     * process's $argv against everything that's registered. `list` and `help` are
     * always available, even with zero register() calls, the same way a fresh Router
     * still responds to something.
     *
     *   $kernel = new Kernel();
     *   $kernel->register(MigrateCommand::class);
     *   $kernel->register(MakeModelCommand::class);
     *   exit($kernel->run($argv));
     *
     * Every command's #[Argument]/#[Option] shape is validated the moment it's
     * registered (see CommandMap), so a typo'd attribute fails loudly at boot, not the
     * first time someone happens to invoke that particular command.
     */
    class Kernel
    {
        /** @var array<string, string> command name => fully-qualified Command class */
        private array $commands = [];

        private ?ConsoleIO $lastIO = null;

        function __construct()
        {
            $this->commands["list"] = ListCommand::class;
            $this->commands["help"] = HelpCommand::class;
        }

        /**
         * @param string $commandClass a Wixnit\Console\Command subclass, decorated with #[AsCommand(...)]
         * @return void
         * @throws ConsoleException if the class's declared shape is invalid, its name
         *   collides with an already-registered command, or its name is reserved
         *   ("list"/"help")
         */
        public function register(string $commandClass): void
        {
            $signature = CommandMap::forClass($commandClass);

            if(($signature->name === "list") || ($signature->name === "help"))
            {
                throw ConsoleException::ReservedCommandName($signature->name);
            }

            if(isset($this->commands[$signature->name]))
            {
                throw ConsoleException::DuplicateCommandName($signature->name, $this->commands[$signature->name], $commandClass);
            }

            $this->commands[$signature->name] = $commandClass;
        }

        /**
         * register several command classes at once
         * @param string[] $commandClasses
         * @return void
         */
        public function registerMany(array $commandClasses): void
        {
            foreach($commandClasses as $commandClass)
            {
                $this->register($commandClass);
            }
        }

        /**
         * @param string $name
         * @return bool
         */
        public function has(string $name): bool
        {
            return isset($this->commands[$name]);
        }

        /**
         * every registered command, keyed by name
         * @return array<string, string>
         */
        public function commands(): array
        {
            return $this->commands;
        }

        /**
         * @param string $name
         * @return CommandSignature|null
         */
        public function signatureFor(string $name): ?CommandSignature
        {
            return isset($this->commands[$name]) ? CommandMap::forClass($this->commands[$name]) : null;
        }

        /**
         * Parse and dispatch a real process invocation - $argv exactly as PHP hands it
         * to you, script name included.
         * @param string[] $argv
         * @return int an exit code - hand this straight to exit()
         */
        public function run(array $argv): int
        {
            array_shift($argv); // the script name itself, e.g. "bin/wixnit"

            [$globals, $remaining] = GlobalOptions::extract($argv);

            $io = new ConsoleIO(
                verbosity: $globals->verbosity,
                noInteraction: $globals->noInteraction,
            );

            if($globals->help)
            {
                $target = array_shift($remaining);
                return $this->dispatch("help", ($target !== null) ? [$target] : [], $io);
            }

            if(count($remaining) === 0)
            {
                return $this->dispatch("list", [], $io);
            }

            $name = array_shift($remaining);
            return $this->dispatch($name, $remaining, $io);
        }

        /**
         * Run a command programmatically - from another command (pass $io to keep it
         * writing to the same place), from a scheduled task, or from a test. Values are
         * given the same way they'd be typed: as CLI-style tokens, e.g.
         * ['--fresh' => true, 'model' => 'User'] resolves the same as
         * "--fresh User" would on the command line - dash-prefixed keys become
         * options, everything else is matched positionally in the order given.
         *
         *   $exitCode = $kernel->call('migrate:run', ['--fresh' => true]);
         *   $output = $kernel->lastIO()->output();   // inspect what it printed
         *
         * @param string $name
         * @param array<int|string, mixed> $arguments
         * @param ConsoleIO|null $io reuses an existing ConsoleIO (so output interleaves
         *   correctly with whatever called this) if given; otherwise builds a fresh
         *   ConsoleIO::ForTesting() instance, retrievable afterwards via lastIO()
         * @return int
         */
        public function call(string $name, array $arguments = [], ?ConsoleIO $io = null): int
        {
            $io = $io ?? ConsoleIO::ForTesting();
            $this->lastIO = $io;
            return $this->dispatch($name, self::toTokens($arguments), $io);
        }

        /**
         * The ConsoleIO instance used by the most recent call() that didn't have one
         * explicitly passed to it - the usual way to retrieve a command's captured
         * output in a test without having to build a ConsoleIO yourself first.
         * @return ConsoleIO|null
         */
        public function lastIO(): ?ConsoleIO
        {
            return $this->lastIO;
        }

        /**
         * @param string $name
         * @param string[] $tokens
         * @param ConsoleIO $io
         * @return int
         */
        private function dispatch(string $name, array $tokens, ConsoleIO $io): int
        {
            if(!isset($this->commands[$name]))
            {
                $io->error("Command \"{$name}\" is not defined.");

                $suggestion = $this->suggest($name);
                if($suggestion !== null)
                {
                    $io->line("Did you mean \"{$suggestion}\"?");
                }
                else
                {
                    $io->line("Run \"list\" to see every registered command.");
                }
                return Command::INVALID;
            }

            $class = $this->commands[$name];
            $signature = CommandMap::forClass($class);

            try
            {
                $parsed = ArgvParser::parse($signature, $tokens);
            }
            catch(ConsoleException $exception)
            {
                $io->error($exception->getMessage());
                $io->line("Usage: {$signature->usage()}");
                return Command::INVALID;
            }

            /** @var Command $command */
            $command = new $class();

            foreach($parsed["arguments"] as $property => $value)
            {
                $command->{$property} = $value;
            }
            foreach($parsed["options"] as $property => $value)
            {
                $command->{$property} = $value;
            }

            $command->bootstrap($io, $this);

            try
            {
                return $command->handle();
            }
            catch(ConsoleException $exception)
            {
                $io->error($exception->getMessage());
                return Command::FAILURE;
            }
            catch(Throwable $exception)
            {
                $io->error($exception->getMessage());
                if($io->verbosity()->shows(Verbosity::VERBOSE))
                {
                    $io->line(get_class($exception).":\n".$exception->getTraceAsString());
                }
                return Command::FAILURE;
            }
        }

        /**
         * Turn a call()-style arguments array into argv-style tokens, so programmatic
         * invocation goes through exactly the same ArgvParser::parse() every real
         * invocation does. Integer keys are positional, in the order given; a
         * string key is treated as an option - prefixed with "--" automatically if it
         * wasn't already given one - with `true`/`false` collapsing to a bare flag
         * (present/absent) and anything else passed through as "--key=value".
         * @param array<int|string, mixed> $arguments
         * @return string[]
         */
        private static function toTokens(array $arguments): array
        {
            $tokens = [];

            foreach($arguments as $key => $value)
            {
                if(is_int($key))
                {
                    $tokens[] = (string) $value;
                    continue;
                }

                $flag = (str_starts_with($key, "-")) ? $key : "--{$key}";

                if($value === true)
                {
                    $tokens[] = $flag;
                }
                else if($value === false)
                {
                    continue;
                }
                else if(is_array($value))
                {
                    foreach($value as $item)
                    {
                        $tokens[] = "{$flag}={$item}";
                    }
                }
                else
                {
                    $tokens[] = "{$flag}={$value}";
                }
            }
            return $tokens;
        }

        /**
         * a close-match suggestion for a mistyped command name, or null if nothing
         * registered is close enough to be worth suggesting
         * @param string $name
         * @return string|null
         */
        private function suggest(string $name): ?string
        {
            $best = null;
            $bestDistance = null;

            foreach(array_keys($this->commands) as $candidate)
            {
                $distance = levenshtein($name, $candidate);
                if(($bestDistance === null) || ($distance < $bestDistance))
                {
                    $bestDistance = $distance;
                    $best = $candidate;
                }
            }

            return (($bestDistance !== null) && ($bestDistance <= 3)) ? $best : null;
        }
    }
