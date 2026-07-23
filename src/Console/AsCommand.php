<?php

    namespace Wixnit\Console;

    use Attribute;

    /**
     * Declares a Command subclass's name and description - the CLI equivalent of a
     * Route's path. Named "AsCommand" rather than "Command" specifically so it doesn't
     * collide with the abstract `Command` base class every command already has to
     * `extend` - a class can't share a name with something else in the same
     * namespace, and a command file needs both symbols in scope at once:
     *
     *   #[AsCommand('migrate:run', description: 'Run pending migrations')]
     *   class MigrateCommand extends Command
     *   {
     *       public function handle(): int { ... }
     *   }
     *
     * `name` is what's typed on the command line (`php wixnit migrate:run`) and is
     * also how `list` groups commands - everything before the first `:` becomes the
     * group heading, so `make:model`/`make:controller` both land under `make`.
     * `description` shows up next to the command in `list` and as the summary line in
     * `help {command}`.
     */
    #[Attribute(Attribute::TARGET_CLASS)]
    class AsCommand
    {
        function __construct(
            public string $name,
            public string $description = "",
        ) {}
    }
