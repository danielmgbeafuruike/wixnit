<?php

    namespace Wixnit\Console\Commands;

    use Wixnit\Console\Argument;
    use Wixnit\Console\AsCommand;
    use Wixnit\Console\Command;
    use Wixnit\Console\Option;

    /**
     * Scaffolds a new Controller subclass from a stub - see GeneratesFiles for the
     * shared write-it-out-and-don't-clobber-anything logic. "Blog\Posts" (or
     * "Blog/Posts") generates ./app/Controllers/Blog/Posts.php, namespaced
     * App\Controllers\Blog.
     */
    #[AsCommand("make:controller", description: "Scaffold a new Controller class")]
    class MakeControllerCommand extends Command
    {
        use GeneratesFiles;

        #[Argument(description: "Class name, optionally namespaced (e.g. Blog\\Posts)")]
        public string $name;

        #[Option(description: "Base directory controllers are written under")]
        public string $path = "app/Controllers";

        #[Option(description: "Base namespace controllers are written under")]
        public string $namespace = "App\\Controllers";

        #[Option(shortcut: "f", description: "Overwrite the file if it already exists")]
        public bool $force = false;

        public function handle(): int
        {
            [$class, $namespace, $subPath] = $this->resolveClassName($this->name, $this->namespace);

            $directory = rtrim($this->path, "/").($subPath !== "" ? "/{$subPath}" : "");
            $target = "{$directory}/{$class}.php";

            $ok = $this->generateFromStub("controller.stub", $target, [
                "namespace" => $namespace,
                "class" => $class,
            ], $this->force);

            return $ok ? self::SUCCESS : self::FAILURE;
        }
    }
