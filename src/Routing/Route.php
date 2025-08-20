<?php

    namespace Wixnit\Routing;

    use Closure;
    use ReflectionClass;
    use Wixnit\App\Controller;
    use Wixnit\App\View;
    use Wixnit\Enum\HTTPMethod;
    use Wixnit\Enum\HTTPResponseCode;
    use Wixnit\Interfaces\IRouteGuard;
    use Wixnit\Interfaces\ITranslator;

    class Route
    {
        private string $path;
        private HTTPMethod $method;
        private Closure $handler;

        /**
         * List of guards to protect the route
         * @var IRouteGuard[]
         */
        private array $guards = [];
        private array $dataRoutes = [];
        private ITranslator $translator;
        private array $routedArgs = [];


        public function __construct(string $path, HTTPMethod $method, string | Closure | View | Path $handler, ?string $handlerMethod=null)
        {
            $this->path = trim($path, "/");
            $this->method = $method;
            
            if($handler instanceof Closure) 
            {
                $this->handler = $handler;
            }
            else if($handler instanceof View)
            {
                $this->handler = function(...$args) use ($handler) 
                {
                    if(isset($this->translator))
                    {
                        $handler->withTranslator($this->translator);
                    }
                    $handler->withDataRoutes($this->dataRoutes);
                    $handler->render(...$args);
                };
            }
            else if($handler instanceof Path)
            {
                $this->handler = function(...$args) use ($handler) {
                    if(file_exists($handler->path)) 
                    {
                        require_once ($handler->path);
                    }
                    else 
                    {
                        (new Response())
                            ->setStatusCode(HTTPResponseCode::NOT_FOUND)
                            ->setContent("The resource at path {$handler->path} does not exist.")
                            ->send();
                    }
                };
            }
            else 
            {
                $ref = new \ReflectionClass($handler);
                if(!$ref->isInstantiable() && !($handler instanceof Controller))
                {
                    throw new \InvalidArgumentException("The provided class is not instantiable.");
                }
                
                $instance = ($handler instanceof Controller) ? $handler : $ref->newInstance();

                if($instance instanceof Controller && $handlerMethod === null)
                {
                    // Default to the controller's method based on the HTTP method
                    switch (Router::GetMethod()) {
                        case HTTPMethod::GET:
                            $handlerMethod = 'get';
                            break;
                        case HTTPMethod::POST:
                            $handlerMethod = 'create';
                            break;
                        case HTTPMethod::PUT:
                            $handlerMethod = 'update';
                            break;
                        case HTTPMethod::DELETE:
                            $handlerMethod = 'delete';
                            break;
                        case HTTPMethod::PATCH:
                            $handlerMethod = 'patch';
                            break;
                        case HTTPMethod::HEAD:
                            $handlerMethod = 'head';
                            break;
                        case HTTPMethod::OPTION:
                            $handlerMethod = 'option';
                            break;
                        default:
                            $handlerMethod = 'handle'; // Fallback to a generic handler
                    }
                }

                if(($handlerMethod != null) && method_exists($instance, $handlerMethod))
                {
                    $this->handler = function(...$args) use ($instance, $handlerMethod) {
                        $instance->$handlerMethod(...$args);
                    };
                }
                else if(method_exists($instance, "handle"))
                {
                    $this->handler = function(...$args) use ($instance) {
                        $instance->handle(...$args);
                    };
                }
                else
                {
                    throw new \InvalidArgumentException("The specified handler method \"".$handlerMethod."\" does not exist in the provided class \"".(new ReflectionClass($instance))->getShortName()."\"");
                }
            }
        }

        /**
         * Getters for the route properties
         */
        public function getPath(): string
        {
            return $this->path;
        }

        public function setPath(string $path): Route
        {
            $this->path = trim($path, "/");
            return $this;
        }

        /**
         * Get the HTTP method for the route
         * @return HTTPMethod
         */
        public function getMethod(): HTTPMethod
        {
            return $this->method;
        }

        /**
         * Get the closure to be executed for the route
         * @return Closure
         */
        public function getHandler(): Closure
        {
            return $this->handler;
        }

        /**
         * Set a guard for the route to control access
         * @param \Wixnit\Interfaces\IRouteGuard $guard
         * @return Route
         */
        public function useGuard(IRouteGuard $guard): Route
        {
            $this->guards[] = $guard;
            return $this;
        }

        /**
         * get the guard for the route
         * @return IRouteGuard[]
         */
        public function getGuards(): array
        {
            return $this->guards;
        }

        /**
         * Set a translator for the route to handle translations
         * @param \Wixnit\Interfaces\ITranslator $translator
         * @return Route
         */
        public function useTranslator(ITranslator $translator): Route
        {
            $this->translator = $translator;
            return $this;
        }

        /**
         * Get the translator for the route
         * @return ITranslator|null
         */
        public function getTranslator(): ?ITranslator
        {
            return $this->translator;
        }

        /**
         * Check if the route has a translator
         * @return bool
         */
        public function hasTranslator(): bool
        {
            return isset($this->translator);
        }

        /**
         * Sets data routes for the route
         * @return Route
         */
        public function useDataRoutes(): Route
        {
            $data = func_get_args();

            for($i = 0; $i < count($data); $i++)
            {
                if(is_array($data[$i]))
                {
                    for($j = 0; $j < count($data[$i][$j]); $j++)
                    {
                        if(is_string($data[$i][$j]))
                        {
                            $this->dataRoutes[] = $data[$i][$j];
                        }
                    }
                }
                else if(is_string($data[$i]))
                {
                    $this->dataRoutes[] = $data[$i];
                }
            }
            return $this;
        }

        /**
         * Get the data routes for the route
         * @return array
         */
        public function getDataRoutes(): array
        {
            return $this->dataRoutes;
        }

        /**
         * Check if the route has a data route arguments
         * @return bool
         */
        public function hasDataRoutes(): bool
        {
            return (count($this->dataRoutes) > 0);
        }

        /**
         * Check if the route matches a given path and method
         * @param string $path
         * @param \Wixnit\Enum\HTTPMethod $method
         * @return bool
         */
        public function matches(string $path, HTTPMethod $method): bool
        {
            if(($method == $this->method) || ($this->method == HTTPMethod::ANY))
            {
                $r = explode("/", $this->path);
                $c = explode("/", $path);

                $this->routedArgs = [];

                for($i = 0; $i < count($r); $i++)
                {
                    if(count($c) > $i)
                    {
                        if(trim($r[$i]) == "{*}")
                        {
                            return true;
                        }
                        else if(preg_match("/{.*?}/", $r[$i]))
                        {
                            $this->routedArgs[trim($r[$i], "{}")] = $c[$i];
                        }
                        else if($r[$i] != $c[$i])
                        {
                            return false;
                        }
                    }
                    else
                    {
                        return false;
                    }
                }

                if(count($r) == count($c))
                {
                    return true;
                }
            }
            return false;
        }

        /**
         * gets the routed arguments from the matched path. should be called after a successful match
         * @return array
         */
        public function getRoutedArgs(): array
        {
            return $this->routedArgs;
        }

        /**
         * Check if the route has a guard
         * @return bool
         */
        public function isGuarded(): bool
        {
            return count($this->guards) > 0;
        }

        /**
         * Execute the route handler with the given request
         * @param Request $req
         * @return void
         */
        public function execute(Request $req): void
        {
            $payLoads = [];

            if($this->isGuarded())
            {
                $guards = array_reverse($this->guards);

                for($i = 0; $i < count($guards); $i++)
                {
                    if($guards[$i]->checkAccess($req))
                    {
                        if($guards[$i] instanceof PayloadedGuard)
                        {
                            $payLoads[] = $guards[$i]->getPaylaod();
                        }
                    }
                    else
                    {
                        $error = $guards[$i]->onFail();

                        if($error instanceof Response)
                        {
                            $error->send();
                        }
                        else if($error instanceof View)
                        {
                            if(isset($this->translator))
                            {
                                $error->withTranslator($this->translator);
                            }
                            $error->withDataRoutes($this->dataRoutes);
                            $error->render($req, $payLoads);
                        }
                        return;
                    }
                }               
            }

            //add routed args

            // Execute the handler
            call_user_func($this->handler, $req, $payLoads);
        }
    }