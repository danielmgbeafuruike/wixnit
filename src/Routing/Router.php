<?php

namespace Wixnit\Routing;

use Closure;
use Wixnit\App\View;
use Wixnit\Enum\HTTPMethod;

class Router
{
    public string $page = "";
    public array $args = [];
    public array $routedArgs = [];
    public string $errorPagePath = "";
    public array $dataRoute = [];
    public array $dataRouteArgs = [];


    public string $requestURL = "";
    public array $routes = [];
    private array $redirects = [];
    private string $homePath = "";

    /**
     * @var Route[] registry of named routes, used by Router::Url() to generate links
     */
    private static array $namedRoutes = [];


    private Closure | null $requestInterceptor = null;
    private Closure | null $responseInterceptor = null;


    function __construct($path=null, $dataRoutes=null)
    {
        $this->dataRoute = (is_array($dataRoutes) ? $dataRoutes : []);
        $this->requestURL = trim(parse_url(($path != null) ? $path : (($_SERVER['ORIG_PATH_INFO'] ?? ($_SERVER['PATH_INFO'] ?? null)) ?? $_SERVER['REQUEST_URI']))['path'], "/");
        $this->requestURL = $this->stripDataPath($this->requestURL);
        $paths = explode("/", $this->requestURL);

        $this->page = $paths[0];

        for($i = 1; $i < count($paths); $i++)
        {
            $this->args[] = $paths[$i];
        }
    }

    /**
     * Internal helper that all HTTP-verb methods (get/post/put/...) delegate to.
     * Builds a RouteCollection containing one Route per path given, registers it
     * on the router, and returns it so it can be chained (->useGuard(), ->name(), etc.)
     * @param HTTPMethod $method
     * @param string|array $path
     * @param string|Closure|Path|View $handler
     * @param string|null $handlerMethod
     * @return RouteCollection
     */
    private function addRoute(HTTPMethod $method, string | array $path, string | Closure | Path | View $handler, ?string $handlerMethod=null): RouteCollection
    {
        $paths = is_array($path) ? $path : [$path];
        $rt = new RouteCollection();

        for($i = 0; $i < count($paths); $i++)
        {
            $rt->addRoute(new Route($paths[$i], $method, $handler, $handlerMethod));
        }
        $this->routes[] = $rt;
        return $rt;
    }

    /**
     * add routes that can process request with any method
     * @param string|array $path
     * @param string|Closure|View|Path $handler
     * @param string|null $handlerMethod
     * @return RouteCollection
     */
    public function any(string | array $path, string | Closure | Path | View $handler, ?string $handlerMethod=null): RouteCollection
    {
        return $this->addRoute(HTTPMethod::ANY, $path, $handler, $handlerMethod);
    }

    /**
     * add routes that process requests send with PUT method
     * @param string|array $path
     * @param string|\Closure|\Wixnit\Routing\Path|\Wixnit\App\View $handler
     * @param mixed $handlerMethod
     * @return RouteCollection
     */
    public function put(string | array $path, string | Closure | Path | View $handler, ?string $handlerMethod=null): RouteCollection
    {
        return $this->addRoute(HTTPMethod::PUT, $path, $handler, $handlerMethod);
    }

    /**
     * add routes that process requests send with POST method
     * @param string|array $path
     * @param string|\Closure|\Wixnit\Routing\Path|\Wixnit\App\View $handler
     * @param mixed $handlerMethod
     * @return RouteCollection
     */
    public function post(string | array $path, string | Closure | Path | View $handler, ?string $handlerMethod=null): RouteCollection
    {
        return $this->addRoute(HTTPMethod::POST, $path, $handler, $handlerMethod);
    }

    /**
     * add routes that process requests send with GET method
     * @param string|array $path
     * @param string|\Closure|\Wixnit\Routing\Path|\Wixnit\App\View $handler
     * @param mixed $handlerMethod
     * @return RouteCollection
     */
    public function get(string | array $path, string | Closure | Path | View $handler, ?string $handlerMethod=null): RouteCollection
    {
        return $this->addRoute(HTTPMethod::GET, $path, $handler, $handlerMethod);
    }

    /**
     * add routes that process requests send with DELETE method
     * @param string|array $path
     * @param string|\Closure|\Wixnit\Routing\Path|\Wixnit\App\View $handler
     * @param mixed $handlerMethod
     * @return RouteCollection
     */
    public function delete(string | array $path, string | Closure | Path | View $handler, ?string $handlerMethod=null): RouteCollection
    {
        return $this->addRoute(HTTPMethod::DELETE, $path, $handler, $handlerMethod);
    }

    /**
     * add routes that process requests send with PATCH method
     * @param string|array $path
     * @param string|\Closure|\Wixnit\Routing\Path|\Wixnit\App\View $handler
     * @param mixed $handlerMethod
     * @return RouteCollection
     */
    public function patch(string | array $path, string | Closure | Path | View $handler, ?string $handlerMethod=null): RouteCollection
    {
        return $this->addRoute(HTTPMethod::PATCH, $path, $handler, $handlerMethod);
    }

    /**
     * add routes that process requests send with HEAD method
     * @param string|array $path
     * @param string|\Closure|\Wixnit\Routing\Path|\Wixnit\App\View $handler
     * @param mixed $handlerMethod
     * @return RouteCollection
     */
    public function head(string | array $path, string | Closure | Path | View $handler, ?string $handlerMethod=null): RouteCollection
    {
        return $this->addRoute(HTTPMethod::HEAD, $path, $handler, $handlerMethod);
    }

    /**
     * resolve requests to their routes
     * @return void
     */
    public function mapRoutes()
    {
        //get the request method
        $method = Router::GetMethod();

        //honor any explicit redirect() rules before attempting to match routes
        foreach($this->redirects as $redirect)
        {
            if(trim($redirect["from"], "/") === $this->requestURL)
            {
                (new Response())->redirect($redirect["to"])->send();
                return;
            }
        }

        //if this is a request for the root and a home route was configured, route to it instead
        $requestURL = $this->requestURL;
        if(($requestURL == "") && ($this->homePath != ""))
        {
            $requestURL = trim($this->homePath, "/");
        }

        //get all routes from their collections
        $routeList = [];

        for($i = 0; $i < count($this->routes); $i++)
        {
            if($this->routes[$i] instanceof Route)
            {
                $routeList[] = $this->routes[$i];
            }
            else if($this->routes[$i] instanceof RouteCollection)
            {
                $rl = $this->routes[$i]->getRoutes();

                for($j = 0; $j < count($rl); $j++)
                {
                    $routeList[] = $rl[$j];
                }
            }
        }

        //loop through the routes and check if they match the request URL
        $pathMatchedWrongMethod = false;

        for($i = 0; $i < count($routeList); $i++)
        {
            if($routeList[$i]->matches($requestURL, $method))
            {
                //get the routed args
                $this->routedArgs = $routeList[$i]->getRoutedArgs();

                //prepre the request object for the request
                $req = $this->buildRequest();

                //run request interceptor
                if($this->requestInterceptor != null)
                {
                    $req = ($this->requestInterceptor)($req, $routeList[$i]->getPath(), $routeList[$i]->getTag()) ?? $req;
                }

                //execute the route
                $response = $routeList[$i]->execute($req);

                //run post application interceptor
                if($this->responseInterceptor != null)
                {
                    $response = ($this->responseInterceptor)($response, $routeList[$i]->getPath(), $routeList[$i]->getTag()) ?? $response;
                }

                //if response is a view, render it or send the response
                if($response instanceof Response)
                {
                    $response->send();
                }
                if($response instanceof View)
                {
                    $response->render();
                }
                return;
            }
            else if($routeList[$i]->matchesPath($requestURL))
            {
                //the path exists but not for this HTTP method - remember it so we can send a 405 instead of a 404
                $pathMatchedWrongMethod = true;
            }
        }

        if($pathMatchedWrongMethod)
        {
            $this->showMethodNotAllowed();
            return;
        }

        if($this->errorPagePath != "")
        {
            require_once ($this->errorPagePath);
        }
        else
        {
            $this->showResourceNotFound();
        }
    }

    /**
     * Create a new RouteCollection with a root route
     * @param string $path
     * @param array $routes
     * @return RouteCollection
     */
    public function group(string $path, array $routes): RouteCollection
    {
        $rt = new RouteCollection();

        for($i = 0; $i < count($routes); $i++)
        {
            if(($routes[$i] instanceof Route) || ($routes[$i] instanceof RouteCollection))
            {
                $rt->addRoute($routes[$i]);
            }
        }
        $rt->setRoot($path);

        //add it to the route stack
        $this->routes[] = $rt;
        return $rt;
    }

    /**
     * Set the error page path to be used when a route is not found
     * @param string $path
     * @return void
     */
    public function setErrorPagePath(string $path): void
    {
        $this->errorPagePath = $path;
    }

    /**
     * Set the home route path
     * @param string $arg
     * @param string|null $method
     * @return void
     */
    public function setHomeRoute($arg, $method=null)
    {
        $this->homePath = $arg;
    }

    /**
     * set redirection routes
     * @param mixed $from
     * @param mixed $to
     * @return void
     */
    public function redirect($from, $to)
    {
        $this->redirects[] = ["from"=>$from, "to"=>$to];
    }

    /**
     * Save data routes to the global scope. This will enable the data routes to be used anywhere in the application
     * @return void
     */
    public function globalizeDataRoutes()
    {
        $keys = array_keys($this->dataRouteArgs);
        $dataRoute = "";

        for($i = 0; $i < count($keys); $i++)
        {
            $dataRoute .= "/".$this->dataRouteArgs[$keys[$i]];
        }
        $dataRoute = trim($dataRoute, '/');

        $GLOBALS['data-routes'] = $this->dataRoute;
        $GLOBALS['route-args']= $this->dataRouteArgs;
        $GLOBALS['data-route-string'] = $dataRoute;
        $GLOBALS['current-path'] = $this->requestURL;
    }

    /**
     * Set the data route for the router
     * @param array $routes
     * @return void
     */
    public function setDataRoute(array $routes=[])
    {
        $this->dataRoute = $routes;
    }

    /**
     * The method will be ran before the request is sent to the remaining of the application
     * @param Closure $closure
     * @return void
     */
    public function interceptRequest(Closure $closure)
    {
        $this->requestInterceptor = $closure;
    }

    /**
     * The method will be ran after the application is ran and can be used to further process responses
     * @param Closure $closure
     * @return void
     */
    public function interceptResponse(Closure $closure)
    {
        $this->responseInterceptor = $closure;
    }

    


    #region static method

    /**
     * Adjust URL to stay poniting at the default set URL relative to the current URL
     * @param mixed $path
     * @param mixed $pathInfo
     * @return string
     */
    public static function ResolveURL($path, $pathInfo=null): string
    {
        $ret = $path;
        $prepend = "";
        $extra = false;

        if($pathInfo === null)
        {
            if((isset($_SERVER['PATH_INFO'])) ||(isset($_SERVER['ORIG_PATH_INFO'])))
            {
                $tmp = isset($_REQUEST['ORIG_PATH_INFO']) ? explode("/", trim($_SERVER['ORIG_PATH_INFO'])) : explode("/", trim($_SERVER['PATH_INFO']));

                if($tmp[(count($tmp) - 1)] == "")
                {
                    $extra = true;
                }

                $ds = isset($_SERVER['ORIG_PATH_INFO']) ? explode("/", trim($_SERVER['ORIG_PATH_INFO'], "/")) : explode("/", trim($_SERVER['PATH_INFO'], "/"));
                for($i = 0; $i < (count($ds) - 1); $i++)
                {
                    $prepend .= "../";
                }
            }
        }
        else
        {
            if((isset($pathInfo)) && ($pathInfo != ""))
            {
                $tmp = explode("/", $pathInfo);

                if($tmp[(count($tmp) - 1)] == "")
                {
                    $extra = true;
                }

                $ds = explode("/", trim($pathInfo, "/"));
                for($i = 0; $i < (count($ds) - 1); $i++)
                {
                    $prepend .= "../";
                }
            }
        }


        if($extra)
        {
            $ret = "../".$ret;
        }

        $dPath = Router::GetDataRouteString();
        return $prepend.(($dPath != "") ? Router::GetDataRouteString()."/" : "").$ret;
    }

    /**
     * Get the global data route string
     * @return string
     */
    public static function GetDataRouteString(): string
    {
        if(isset($GLOBALS['data-route-string']))
        {
            return $GLOBALS['data-route-string'];
        }
        return "";
    }

    /**
     * Get router data routes
     * @return array
     */
    public static function GetDataRoutes(): array
    {
        if(isset($GLOBALS['data-routes']))
        {
            return $GLOBALS['data-routes'];
        }
        return [];
    }

    /**
     * Get router data route args
     * @return array
     */
    public static function GetDataRouteArgs(): array
    {
        if(isset($GLOBALS['route-args']))
        {
            return $GLOBALS['route-args'];
        }
        return [];
    }

    /**
     * Full current path
     * @return string
     */
    public static function CurrentPath(): string
    {
        if(isset($GLOBALS['current-path']))
        {
            return $GLOBALS['current-path'];
        }
        return "";
    }

    /**
     * Register a route under a name so it can later be resolved to a URL with Router::Url().
     * Called automatically by Route::name(), you shouldn't need to call this directly.
     * @param string $name
     * @param Route $route
     * @return void
     */
    public static function RegisterNamedRoute(string $name, Route $route): void
    {
        Router::$namedRoutes[$name] = $route;
    }

    /**
     * Build a URL for a named route, filling in its {param} / {param:type} placeholders.
     * Any extra values passed in $params that aren't used as path placeholders are appended as a query string.
     *
     * Example:
     *  $router->get("user/{id}", UserController::class, "show")->name("user.show");
     *  Router::Url("user.show", ["id" => 5]);              // "user/5"
     *  Router::Url("user.show", ["id" => 5, "tab" => "x"]); // "user/5?tab=x"
     *
     * @param string $name
     * @param array $params
     * @return string
     * @throws RouterException if no route was registered under the given name
     */
    public static function Url(string $name, array $params = []): string
    {
        if(!isset(Router::$namedRoutes[$name]))
        {
            throw new \Wixnit\Exception\RouterException("No route named \"".$name."\" has been registered.");
        }

        $path = Router::$namedRoutes[$name]->getPath();
        $segments = explode("/", $path);
        $used = [];

        for($i = 0; $i < count($segments); $i++)
        {
            if(preg_match("/^{(.*?)}$/", $segments[$i], $m))
            {
                $inner = $m[1];
                $paramName = str_contains($inner, ":") ? explode(":", $inner, 2)[0] : $inner;

                if(array_key_exists($paramName, $params))
                {
                    $segments[$i] = rawurlencode((string) $params[$paramName]);
                    $used[] = $paramName;
                }
            }
        }

        $url = implode("/", $segments);

        $query = array_diff_key($params, array_flip($used));
        if(count($query) > 0)
        {
            $url .= "?".http_build_query($query);
        }

        return $url;
    }
    #endregion

    

    #region private method

    private function showResourceNotFound()
    {
        http_response_code(404);
        echo "
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Error : : Not Found</title>
                </head>
                <body style='text-align: center;'>
                    <h1 style='color: dimgray; font-family: Arial; font-size: 3em;'>404</h1>
                    <h3 style='color: lightgray; font-family: Segoe UI; font-weight: normal;'>
                        The requested page was not found
                    </h3>
                </body>
            </html>";
    }

    /**
     * Send a 405 Method Not Allowed response - used when a route path matches
     * but not for the HTTP method the request came in as
     * @return void
     */
    private function showMethodNotAllowed()
    {
        http_response_code(405);
        echo "
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Error : : Method Not Allowed</title>
                </head>
                <body style='text-align: center;'>
                    <h1 style='color: dimgray; font-family: Arial; font-size: 3em;'>405</h1>
                    <h3 style='color: lightgray; font-family: Segoe UI; font-weight: normal;'>
                        This resource does not support the requested HTTP method
                    </h3>
                </body>
            </html>";
    }

    /**
     * strip the data path from the request URL
     * @param string $path
     * @return string
     */
    private function stripDataPath(string $path)
    {
        $keys = array_keys($this->dataRoute);

        for($i = 0; $i < count($keys); $i++)
        {
            for($x = 0; $x < count($this->dataRoute[$keys[$i]]); $x++)
            {
                $p = explode("/", $path);

                if(in_array($this->dataRoute[$keys[$i]][$x], $p))
                {
                    $this->dataRouteArgs[$keys[$i]] = $this->dataRoute[$keys[$i]][$x];
                    $path = str_replace($this->dataRoute[$keys[$i]][$x], "", $path);
                }
            }
        }
        //die($path);
        return trim($path, '/');
    }

    /**
     * build, prep & hydrate the request object to be injected into comtrollers, views, paths or closures
     * @return Request
     */
    private function buildRequest(): Request
    {
        $routedData = new FormData();
        $postedData = new FormData();
        $getData = new FormData();
        $jsonData = new FormData();

        $routedData->args = $this->routedArgs;


        //retriever POST data
        $keys = array_keys($_POST);
        $postArgs = [];
        for($i = 0; $i < count($keys); $i++)
        {
            $postArgs[$keys[$i]] = $_POST[$keys[$i]];
        }
        $postedData->args = $postArgs;


        //retrieve GET data
        $keys = array_keys($_GET);
        $getArgs = [];
        for($i = 0; $i < count($keys); $i++)
        {
            $getArgs[$keys[$i]] = $_GET[$keys[$i]];
        }
        $getData->args = $getArgs;


        // Read raw data from the php://input stream
        $inputData = file_get_contents('php://input');
        
        $parsedData = [];

        // Check for content type header to detect the type of data
        $contentType = $_SERVER['CONTENT_TYPE'] ?? 'text/plain';

        if (strpos($contentType, 'application/json') !== false) 
        {
            $parsedData = json_decode($inputData, true);
            if (json_last_error() !== JSON_ERROR_NONE) 
            {
                
            }
        } 
        else if (strpos($contentType, 'application/xml') !== false || strpos($contentType, 'text/xml') !== false) 
        {
            // Parse XML data
            $xml = simplexml_load_string($inputData, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) 
            {
                
            }
            // Convert SimpleXMLElement object to an associative array
            $parsedData = json_decode(json_encode($xml), true);
        } 
        else if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) 
        {
            // Parse URL-encoded data (like a standard HTML form)
            parse_str($inputData, $parsedData);
        }
        else 
        {
            // Treat as plain text if no specific content type is provided
            $parsedData['raw'] = $inputData;
        }
        $jsonData->args = $parsedData;

        return new Request(Router::GetMethod(), $routedData, $getData, $postedData, $jsonData);
    }

    /**
     * check if a string is a valid HTTP method
     * @param mixed $method
     * @return bool
     */
    public static function IsValidMethod(string $method): bool
    {
        $pp = strtolower(trim($method));
        return (($pp == "post") || ($pp == "get") || ($pp == "put") || ($pp == "patch") || ($pp == "delete") || ($pp == "option") || ($pp == "head"));
    }

    /**
     * get the request method of the current request
     * @return HTTPMethod
     */
    public static function GetMethod(): HTTPMethod
    {
        $method = strtoupper((isset($_REQUEST['_method']) && Router::isValidMethod($_REQUEST['_method'])) ? $_REQUEST['_method'] : $_SERVER['REQUEST_METHOD']);

        if(HTTPMethod::tryFrom($method))
        {
            return HTTPMethod::from($method);
        }
        return HTTPMethod::ANY;
    }
    #endregion
}