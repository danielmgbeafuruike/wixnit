<?php

    namespace Wixnit\Console;

    /**
     * Base class every console command extends. Declare its name via #[AsCommand(...)]
     * on the class, its inputs via #[Argument]/#[Option] on public properties, and do
     * the actual work in handle():
     *
     *   #[AsCommand('migrate:run', description: 'Run pending migrations')]
     *   class MigrateCommand extends Command
     *   {
     *       #[Option(shortcut: 'f', description: 'Drop all tables and re-migrate from scratch')]
     *       public bool $fresh = false;
     *
     *       #[Argument(description: 'Only migrate this model class', default: null)]
     *       public ?string $model = null;
     *
     *       public function handle(): int
     *       {
     *           $this->io->info("Running migrations...");
     *           return self::SUCCESS;
     *       }
     *   }
     *
     * `$io` and `$kernel` are populated by Kernel right before handle() is called - not
     * in the constructor, since a command declared with #[Argument]/#[Option]
     * properties needs to exist (so Kernel can reflect and mass-assign onto it) before
     * either is available.
     */
    abstract class Command
    {
        /** the command ran successfully */
        public const SUCCESS = 0;

        /** the command ran, but failed - an uncaught exception is also reported this way */
        public const FAILURE = 1;

        /** the command was never run at all - unknown command name, or arguments/options
         * that don't satisfy its declared #[Argument]/#[Option] signature */
        public const INVALID = 2;

        protected ConsoleIO $io;
        protected ?Kernel $kernel = null;

        /**
         * do the actual work. Return one of the exit-code constants above (or any other
         * small integer - anything non-zero is treated as failure by the calling shell).
         * @return int
         */
        abstract public function handle(): int;

        /**
         * Wires up $io/$kernel after the command has been constructed and its
         * #[Argument]/#[Option] properties have been mass-assigned, immediately before
         * handle() is called.
         * @internal called by Kernel - not meant to be called directly
         * @param ConsoleIO $io
         * @param Kernel|null $kernel
         * @return void
         */
        public function bootstrap(ConsoleIO $io, ?Kernel $kernel = null): void
        {
            $this->io = $io;
            $this->kernel = $kernel;
        }
    }
