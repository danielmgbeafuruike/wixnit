<?php

    namespace Wixnit\App;

    use Closure;
    use InvalidArgumentException;
    use Wixnit\Interfaces\ITranslator;

    class Views
    {
        private string $base_path = "";
        private array $namespaces = [];
        private array $routeSegments = [];
        private ?ITranslator $translator = null;
        private array $sharedData = [];
        private array $composers = [];

        function __construct(string $root_path, ITranslator | null $translator = null, array $routeArgs = [])
        {
            $this->base_path = rtrim($root_path, '/');

            if($translator != null)
            {
                $this->translator = $translator;
            }
            if(count($routeArgs) > 0)
            {
                $this->routeSegments = $routeArgs;
            }
        }

        /**
         * Register an additional named base path that can be targeted with the
         * "namespace::view" syntax in get()/exists(), e.g. addNamespace("admin", "/views/admin")
         * lets you request "admin::dashboard".
         * @param string $name
         * @param string $path
         * @return self
         */
        public function addNamespace(string $name, string $path): self
        {
            $this->namespaces[$name] = rtrim($path, '/');
            return $this;
        }

        /**
         * Data merged into every View this factory produces from here on, so common
         * values (current user, site name, flash messages...) don't need to be passed
         * to every individual render() call.
         * @param array $data
         * @return self
         */
        public function share(array $data): self
        {
            $this->sharedData = array_merge($this->sharedData, $data);
            return $this;
        }

        /**
         * Registers a closure that runs on a View right before get() returns it for a
         * given view name - useful for auto-injecting data every time a specific view
         * renders (e.g. always attach the logged-in user to "layout.header"), without
         * repeating ->with([...]) at every call site.
         *
         * Applies wherever that view name is resolved through this factory, including
         * as a layout (extend()) or component (component()) target from another view -
         * both resolve through this same factory when it's attached via withFactory().
         *
         * @param string $viewName same name format accepted by get() - dot or slash
         *   notation, optionally "namespace::..."
         * @param Closure $composer receives the resolved View instance; typically calls
         *   $view->with([...]) on it
         * @return self
         */
        public function composer(string $viewName, Closure $composer): self
        {
            $this->composers[self::normalizeName($viewName)][] = $composer;
            return $this;
        }

        public function get(string $viewName, ITranslator | null $translator = null, array $routeData = []): View
        {
            $viewName = self::normalizeName($viewName);

            $ret = new View($this->resolveBasePath($viewName) . "/" . $this->stripNamespace($viewName));
            $ret->withFactory($this);

            if($translator != null)
            {
                $ret->withTranslator($translator);
            }
            else if($this->translator != null)
            {
                $ret->withTranslator($this->translator);
            }

            if(count($routeData) > 0)
            {
                $ret->setDataRoutes($routeData);
            }
            else if(count($this->routeSegments) > 0)
            {
                $ret->setDataRoutes($this->routeSegments);
            }

            $ret->with($this->sharedData);

            foreach($this->composers[$viewName] ?? [] as $composer)
            {
                $composer($ret);
            }

            return $ret;
        }

        /**
         * Check whether a view name resolves to an actual file, without constructing
         * or rendering it - useful for theme/override fallback chains.
         * @param string $viewName
         * @return bool
         */
        public function exists(string $viewName): bool
        {
            $viewName = self::normalizeName($viewName);
            return View::resolves($this->resolveBasePath($viewName) . "/" . $this->stripNamespace($viewName));
        }

        /**
         * Normalizes a view name to slash notation: converts dots to slashes ("layouts.main"
         * -> "layouts/main") while leaving a "namespace::" prefix's own separator intact,
         * so "admin::dashboard.index" becomes "admin::dashboard/index". Shared with
         * View::resolveRelated() so extend()/component() names resolve the same way get() does.
         * @param string $viewName
         * @return string
         */
        public static function normalizeName(string $viewName): string
        {
            if(str_contains($viewName, "::"))
            {
                [$namespace, $rest] = explode("::", $viewName, 2);
                return $namespace . "::" . str_replace('.', '/', trim($rest, '/'));
            }

            return str_replace('.', '/', trim($viewName, '/'));
        }

        /**
         * Resolve which base path a "namespace::view" (or plain "view") name should be
         * looked up under. Falls back to the default base path for an unrecognised namespace.
         * @param string $viewName
         * @return string
         */
        private function resolveBasePath(string $viewName): string
        {
            if(str_contains($viewName, "::"))
            {
                $namespace = explode("::", $viewName, 2)[0];

                if(isset($this->namespaces[$namespace]))
                {
                    return $this->namespaces[$namespace];
                }
            }

            return $this->base_path;
        }

        /**
         * Strip a leading "namespace::" prefix, leaving the plain view path.
         *
         * The ".." check below is a defense-in-depth guard against a view name escaping
         * the base directory - in practice normalizeName() already neutralizes it, since
         * every "." gets converted to "/" before this ever runs, so a literal ".." can't
         * survive to reach here. It's kept in case that normalization ever changes.
         * @param string $viewName
         * @return string
         */
        private function stripNamespace(string $viewName): string
        {
            $name = str_contains($viewName, "::") ? explode("::", $viewName, 2)[1] : $viewName;
            $name = trim($name, '/');

            if(str_contains($name, ".."))
            {
                throw new InvalidArgumentException("Invalid view name \"$viewName\" - view names may not contain \"..\"");
            }

            return $name;
        }
    }
