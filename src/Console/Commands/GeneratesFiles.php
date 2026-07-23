<?php

    namespace Wixnit\Console\Commands;

    use Wixnit\Utilities\File;

    /**
     * Shared plumbing for the make:model/make:controller/make:command generators -
     * read a stub file, swap in {{placeholders}}, and write the result out, refusing
     * to clobber an existing file unless --force is given. Not part of the public
     * Console API; an implementation detail of the three built-in generators.
     */
    trait GeneratesFiles
    {
        /**
         * @param string $stubFile filename under Console/Commands/stubs/, e.g. "model.stub"
         * @param string $targetPath where to write the generated file
         * @param array<string, string> $replacements {{key}} => value
         * @param bool $force overwrite $targetPath if it already exists
         * @return bool true on success
         */
        protected function generateFromStub(string $stubFile, string $targetPath, array $replacements, bool $force): bool
        {
            if(File::Exists($targetPath) && !$force)
            {
                $this->io->error("{$targetPath} already exists. Pass --force to overwrite it.");
                return false;
            }

            $stubPath = __DIR__."/stubs/{$stubFile}";
            $stub = @file_get_contents($stubPath);

            if($stub === false)
            {
                $this->io->error("Missing stub file: {$stubPath}");
                return false;
            }

            $contents = strtr($stub, $this->wrapPlaceholders($replacements));

            if(!File::Write($targetPath, $contents))
            {
                $this->io->error("Failed writing {$targetPath}.");
                return false;
            }

            $this->io->success("Created {$targetPath}");
            return true;
        }

        /**
         * @param array<string, string> $replacements
         * @return array<string, string> the same map, keyed as "{{key}}" for strtr()
         */
        private function wrapPlaceholders(array $replacements): array
        {
            $wrapped = [];
            foreach($replacements as $key => $value)
            {
                $wrapped["{{{$key}}}"] = $value;
            }
            return $wrapped;
        }

        /**
         * Splits a possibly-namespaced name ("Blog\Post" or just "Post") into its
         * class name, full namespace, and the extra directory segments (if any)
         * that namespace implies relative to the base path - StudlyCase-ing each
         * segment along the way.
         * @param string $name
         * @param string $rootNamespace
         * @return array{0: string, 1: string, 2: string} [class name, full namespace, sub-path]
         */
        private function resolveClassName(string $name, string $rootNamespace): array
        {
            $segments = array_values(array_filter(explode("\\", str_replace("/", "\\", $name))));
            $segments = array_map(fn($segment) => \Wixnit\Utilities\Str::StudlyCase($segment), $segments);

            $class = array_pop($segments);
            $namespace = (count($segments) > 0) ? ($rootNamespace."\\".implode("\\", $segments)) : $rootNamespace;
            $subPath = (count($segments) > 0) ? implode("/", $segments) : "";

            return [$class, $namespace, $subPath];
        }
    }
