<?php

    namespace Wixnit\Routing;

    use Wixnit\Enum\HTTPMethod;
    use Wixnit\Interfaces\IRouteGuard;
    use Wixnit\Interfaces\ITranslator;

    /**
     * Class RouteCollection
     * @package Wixnit\Routing
     */
    class RouteCollection
    {
        private array $routes = [];
        private string $rootRoute = "";

        /**
         * List of guards to use
         * @var IRouteGuard[]
         */
        private array $guards = [];
        private ITranslator $translator;


        /**
         * Add a route to the collection
         * @param Route $route
         * @return RouteCollection
         */
        public function addRoute(Route | RouteCollection $route): RouteCollection
        {
            $this->routes[] = $route;
            return $this;
        }

        /**
         * Set the root route for the collection
         * @param string $root
         * @return RouteCollection
         */
        public function setRoot(string $root): RouteCollection
        {
            $this->rootRoute = trim($root, "/");
            return $this;
        }

        /**
         * Get all routes in the collection
         * @return Route[]
         */
        public function getRoutes(): array
        {
            $ret = [];

            if($this->rootRoute == "")
            {
                for($i = 0; $i < count($this->routes); $i++)
                {
                    if($this->routes[$i] instanceof Route)
                    {
                        if(count($this->guards) > 0)
                        {
                            for($g = 0; $g < count($this->guards); $g++)
                            {
                                $this->routes[$i]->useGuard($this->guards[$g]);
                            }
                        }
                        if(isset($this->translator))
                        {
                            $this->routes[$i]->useTranslator($this->translator);
                        }
                        $ret[]= $this->routes[$i];
                    }
                    if($this->routes[$i] instanceof RouteCollection)
                    {
                        $rs= $this->routes[$i]->getRoutes();

                        for($j = 0; $j < count($rs); $j++)
                        {
                            if(count($this->guards) > 0)
                            {
                                for($g = 0; $g < count($this->guards); $g++)
                                {
                                    $rs[$j]->useGuard($this->guards[$g]);
                                }
                            }
                            if(isset($this->translator))
                            {
                                $rs[$j]->useTranslator($this->translator);
                            }
                            $ret[] = $rs[$j];
                        }
                    }
                }
            }
            else
            {
                for($i = 0; $i < count($this->routes); $i++)
                {
                    if($this->routes[$i] instanceof Route)
                    {
                        $r = clone $this->routes[$i];
                        if(count($this->guards) > 0)
                        {
                            for($g = 0; $g < count($this->guards); $g++)
                            {
                                $r->useGuard($this->guards[$g]);
                            }
                        }
                        if(isset($this->translator))
                        {
                            $r->useTranslator($this->translator);
                        }
                        $r->setPath($this->rootRoute."/".trim($r->getPath(), "/"));
                        $ret[]= $r;
                    }
                    if($this->routes[$i] instanceof RouteCollection)
                    {
                        $rs= $this->routes[$i]->getRoutes();

                        for($j = 0; $j < count($rs); $j++)
                        {
                            $r = clone $rs[$j];
                            if(count($this->guards) > 0)
                            {
                                for($g = 0; $g < count($this->guards); $g++)
                                {
                                    $r->useGuard($this->guards[$g]);
                                }
                            }
                            if(isset($this->translator))
                            {
                                $r->useTranslator($this->translator);
                            }
                            $r->setPath($this->rootRoute."/".trim($r->getPath(), "/"));
                            $ret[]= $r;
                        }
                    }
                }
            }
            return $ret;
        }

        /**
         * Clear all routes in the collection
         * @return void
         */
        public function clearRoutes(): void
        {
            $this->routes = [];
        }

        /**
         * Check if the collection is empty
         * @return bool
         */
        public function isEmpty(): bool
        {
            return empty($this->routes);
        }

        /**
         * Remove a route by its path and method
         * @param string $path
         * @param HTTPMethod $method
         * @return bool
         */
        public function removeRoute(string $path, HTTPMethod $method): bool
        {
            foreach ($this->routes as $key => $route) 
            {
                if ($route['path'] === $path && $route['method'] === $method) 
                {
                    unset($this->routes[$key]);
                    return true;
                }
            }
            return false;
        }

        /**
         * Set the routes for the collection
         * @param array $routes
         * @return void
         */
        public function setRoutes(array $routes): void
        {
            $this->routes = [];
            foreach ($routes as $route) 
            {
                if (($route instanceof Route) || ($route instanceof RouteCollection))
                {
                    $this->routes[] = $route;
                }
            }
        }
    
        /**
         * Apply a guard to all routes in the collection
         * @param IRouteGuard $guard
         * @return RouteCollection
         */
        public function withGuard(IRouteGuard $guard): RouteCollection
        {
            $this->guards[] = $guard;
            return $this;
        }


        /**
         * Get the guard applied to the collection
         * @return IRouteGuard|null
         */
        public function getGuards(): ?IRouteGuard
        {
            return $this->guards ?? null;
        }

        /**
         * Set the translator to all the routes in the collection
         * @param \Wixnit\Interfaces\ITranslator $translator
         * @return RouteCollection
         */
        public function withTranslator(ITranslator $translator): RouteCollection
        {
            $this->translator = $translator;
            return $this;
        }

        /**
         * Get the translator thats used on all the routes
         * @return ITranslator|null
         */
        public function getTranslator(): ?ITranslator
        {
            return $this->translator ?? null;
        }


        #region static methods

        /**
         * Create a new RouteCollection with a root route
         * @param string $route
         * @param array $routes
         * @return RouteCollection
         */
        public static function Group(string $route, array $routes)
        {
            $ret = new RouteCollection();
            $ret->setRoot($route);

            for($i = 0; $i < count($routes); $i++)
            {
                if(($routes[$i] instanceof Route) || ($routes[$i] instanceof RouteCollection))
                {
                    $ret->addRoute($routes[$i]);
                }
            }
            return $ret;
        }
        #endregion
    }