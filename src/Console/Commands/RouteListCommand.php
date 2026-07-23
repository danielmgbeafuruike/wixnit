<?php

    namespace Wixnit\Console\Commands;

    use ReflectionFunction;
    use ReflectionProperty;
    use Wixnit\App\Container;
    use Wixnit\App\View;
    use Wixnit\Console\AsCommand;
    use Wixnit\Console\Command;
    use Wixnit\Routing\Path;
    use Wixnit\Routing\Route;
    use Wixnit\Routing\Router;

    /**
     * Lists every route the application's Router knows about. A Route only ever
     * exposes its handler as an already-wrapped Closure (see Route::__construct()),
     * so there's no getControllerClass()/getView() to just call - this reads the
     * closure's own bound `use (...)` variables back out via reflection instead, to
     * recover what the handler actually points at for display.
     *
     * There's no single global registry of "every route in the app" anywhere in the
     * framework - a Router is normally built fresh per request, from whatever the
     * current request path happens to be. For `route:list` to have anything to show,
     * register a Router built from the app's own route definitions once, at boot:
     *
     *   Container::set('router', $router);
     */
    #[AsCommand("route:list", description: "List every registered route")]
    class RouteListCommand extends Command
    {
        public function handle(): int
        {
            if(!Container::has("router"))
            {
                $this->io->error('No router is registered - nothing to list.');
                $this->io->line("Register the application's Router once at boot:");
                $this->io->line("  Container::set('router', \$router);");
                return self::FAILURE;
            }

            $router = Container::get("router", Router::class);
            $routes = [];

            foreach($router->routes as $collection)
            {
                foreach($collection->getRoutes() as $route)
                {
                    $routes[] = $route;
                }
            }

            if(count($routes) === 0)
            {
                $this->io->warning("No routes are registered.");
                return self::SUCCESS;
            }

            usort($routes, fn(Route $a, Route $b) => $a->getPath() <=> $b->getPath());

            $rows = [];
            foreach($routes as $route)
            {
                $rows[] = [
                    $route->getMethod()->name,
                    "/".$route->getPath(),
                    $route->getName() ?? "",
                    $this->describeHandler($route),
                ];
            }

            $this->io->table(["Method", "Path", "Name", "Handler"], $rows);
            $this->io->line(count($routes)." route(s).");
            return self::SUCCESS;
        }

        /**
         * Recovers a readable label for what a Route's handler closure actually points
         * at, by reading back the variables it closed over.
         * @param Route $route
         * @return string
         */
        private function describeHandler(Route $route): string
        {
            $variables = (new ReflectionFunction($route->getHandler()))->getStaticVariables();

            if(isset($variables["instance"]) && is_object($variables["instance"]))
            {
                $method = $variables["handlerMethod"] ?? "handle";
                return get_class($variables["instance"])."::{$method}()";
            }

            if(isset($variables["handler"]) && ($variables["handler"] instanceof View))
            {
                return "View: ".$this->readViewPath($variables["handler"]);
            }

            if(isset($variables["handler"]) && ($variables["handler"] instanceof Path))
            {
                return "File: ".$variables["handler"]->path;
            }

            return "Closure";
        }

        private function readViewPath(View $view): string
        {
            try
            {
                $property = new ReflectionProperty(View::class, "filePath");
                $property->setAccessible(true);
                $path = $property->getValue($view);
                return ($path === "") ? "(unnamed)" : $path;
            }
            catch(\Throwable)
            {
                return "(unknown)";
            }
        }
    }
