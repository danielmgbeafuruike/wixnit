<?php

    namespace Wixnit\Console\Commands;

    use ReflectionClass;
    use Throwable;
    use Wixnit\App\Container;
    use Wixnit\App\PointerSavable;
    use Wixnit\App\Savable;
    use Wixnit\Console\Argument;
    use Wixnit\Console\AsCommand;
    use Wixnit\Console\Command;
    use Wixnit\Console\Option;
    use Wixnit\Data\DBConfig;
    use Wixnit\Data\DBMigrator;
    use mysqli;

    /**
     * Runs DBMigrator against every model the application has registered - or, given a
     * single class name, just that one. Reads the database connection the same way any
     * HTTP request already would (Container::get('db'), populated by whatever the
     * app's bootstrap already passes to DBConfig::Init()), so there's no separate
     * console-only database configuration to keep in sync.
     *
     * The application registers the models it wants `migrate:run` (with no argument)
     * to sweep, once, at boot:
     *
     *   Container::set('console.migrate.models', [User::class, Post::class, Comment::class]);
     *
     * `--fresh` drops each table before migrating it, rather than diffing it against
     * its previous shape - genuinely destructive, so it's worth thinking twice before
     * scripting it against anything that isn't a local/throwaway database.
     */
    #[AsCommand("migrate:run", description: "Create or update database tables for the application's models")]
    class MigrateRunCommand extends Command
    {
        #[Argument(description: "Only migrate this one model class (fully-qualified)", default: null)]
        public ?string $model = null;

        #[Option(shortcut: "f", description: "Drop each table before migrating it, instead of diffing it against its current shape")]
        public bool $fresh = false;

        public function handle(): int
        {
            $classes = ($this->model !== null) ? [$this->model] : $this->registeredModels();

            if($classes === null)
            {
                return self::FAILURE;
            }

            if(count($classes) === 0)
            {
                $this->io->warning("No models to migrate.");
                return self::SUCCESS;
            }

            $connection = Container::get("db", DBConfig::class)->getConnection();
            $migrator = new DBMigrator($connection);

            $bar = $this->io->progressBar(count($classes));

            foreach($classes as $class)
            {
                if(!class_exists($class))
                {
                    $this->io->error("Class \"{$class}\" doesn't exist.");
                    return self::FAILURE;
                }

                $this->io->verbose("Migrating {$class}...");

                if($this->fresh)
                {
                    $this->dropTable($connection, $class);
                }

                try
                {
                    $migrator->mapClass($class);
                }
                catch(Throwable $exception)
                {
                    $this->io->error("Failed migrating {$class}: ".$exception->getMessage());
                    return self::FAILURE;
                }

                $bar->advance();
            }

            $this->io->success(count($classes)." model(s) migrated.");
            return self::SUCCESS;
        }

        /**
         * @return string[]|null the registered model list, or null (having already
         *   reported the problem to $this->io) if none was found and no --model was given
         */
        private function registeredModels(): ?array
        {
            if(!Container::has("console.migrate.models"))
            {
                $this->io->error("No models are registered for migration, and no model argument was given.");
                $this->io->line('Either pass a class name ("php wixnit migrate:run App\\Models\\User"),');
                $this->io->line("or register your model list once at boot:");
                $this->io->line('  Container::set(\'console.migrate.models\', [User::class, Post::class]);');
                return null;
            }
            return Container::get("console.migrate.models", "array");
        }

        /**
         * Drops the model's table, ahead of a fresh migration - mirrors DBMigrator's
         * own reflection-based instantiation so the table name comes from the exact
         * same place DBMigrator itself would read it from.
         * @param mysqli $connection
         * @param string $class
         * @return void
         */
        private function dropTable(mysqli $connection, string $class): void
        {
            $reflection = new ReflectionClass($class);

            $instance = $reflection->isSubclassOf(PointerSavable::class)
                ? $reflection->newInstance(new mysqli(), false)
                : ($reflection->isSubclassOf(Savable::class)
                    ? $reflection->newInstance(false)
                    : $reflection->newInstance(new mysqli()));

            $table = $instance->getDBImage()->name;

            $this->io->verbose("Dropping table \"{$table}\"...");
            $connection->query("DROP TABLE IF EXISTS `{$table}`");
        }
    }
