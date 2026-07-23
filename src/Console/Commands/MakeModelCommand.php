<?php

    namespace Wixnit\Console\Commands;

    use Wixnit\Console\Argument;
    use Wixnit\Console\AsCommand;
    use Wixnit\Console\Command;
    use Wixnit\Console\Option;

    /**
     * Scaffolds a new Model subclass from a stub - see GeneratesFiles for the shared
     * write-it-out-and-don't-clobber-anything logic. "Blog\Post" (or "Blog/Post")
     * generates ./app/Models/Blog/Post.php, namespaced App\Models\Blog.
     */
    #[AsCommand("make:model", description: "Scaffold a new Model class")]
    class MakeModelCommand extends Command
    {
        use GeneratesFiles;

        #[Argument(description: "Class name, optionally namespaced (e.g. Blog\\Post)")]
        public string $name;

        #[Option(description: "Base directory models are written under")]
        public string $path = "app/Models";

        #[Option(description: "Base namespace models are written under")]
        public string $namespace = "App\\Models";

        #[Option(shortcut: "f", description: "Overwrite the file if it already exists")]
        public bool $force = false;

        public function handle(): int
        {
            [$class, $namespace, $subPath] = $this->resolveClassName($this->name, $this->namespace);

            $directory = rtrim($this->path, "/").($subPath !== "" ? "/{$subPath}" : "");
            $target = "{$directory}/{$class}.php";

            $ok = $this->generateFromStub("model.stub", $target, [
                "namespace" => $namespace,
                "class" => $class,
            ], $this->force);

            return $ok ? self::SUCCESS : self::FAILURE;
        }
    }
