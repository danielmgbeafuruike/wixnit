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
     * add routes that can process request with any method
     * @param string|array $path
     * @param string|Closure|View|Path $arg
     * @param string|null $handlerMethod
     * @return RouteCollection
     */
    public function any(string | array $path, string | Closure | Path | View $handler, ?string $handlerMethod=null): RouteCollection
    {
        if(is_array($path))
        {
            $rt = new RouteCollection();
            for($i = 0; $i < count($path); $i++)
            {
                $rt->addRoute(new Route($path[$i], HTTPMethod::ANY, $handler, $handlerMethod));
            }
            $this->routes[] = $rt;
            return $rt;
        }
        else
        {
            $rt = new RouteCollection();
            $rt->addRoute(new Route($path, HTTPMethod::ANY, $handler, $handlerMethod));
            $this->routes[] = $rt;
            return $rt;
        }
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
        if(is_array($path))
        {
            $rt = new RouteCollection();
            for($i = 0; $i < count($path); $i++)
            {
                $rt->addRoute(new Route($path[$i], HTTPMethod::PUT, $handler, $handlerMethod));
            }
            $this->routes[] = $rt;
            return $rt;
        }
        else
        {
            $rt = new RouteCollection();
            $rt->addRoute(new Route($path, HTTPMethod::PUT, $handler, $handlerMethod));
            $this->routes[] = $rt;
            return $rt;
        }
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
        if(is_array($path))
        {
            $rt = new RouteCollection();
            for($i = 0; $i < count($path); $i++)
            {
                $rt->addRoute(new Route($path[$i], HTTPMethod::POST, $handler, $handlerMethod));
            }
            $this->routes[] = $rt;
            return $rt;
        }
        else
        {
            $rt = new RouteCollection();
            $rt->addRoute(new Route($path, HTTPMethod::POST, $handler, $handlerMethod));
            $this->routes[] = $rt;
            return $rt;
        }
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
        if(is_array($path))
        {
            $rt = new RouteCollection();
            for($i = 0; $i < count($path); $i++)
            {
                $rt->addRoute(new Route($path[$i], HTTPMethod::GET, $handler, $handlerMethod));
            }
            $this->routes[] = $rt;
            return $rt;
        }
        else
        {
            $rt = new RouteCollection();
            $rt->addRoute(new Route($path, HTTPMethod::GET, $handler, $handlerMethod));
            $this->routes[] = $rt;
            return $rt;
        }
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
        if(is_array($path))
        {
            $rt = new RouteCollection();
            for($i = 0; $i < count($path); $i++)
            {
                $rt->addRoute(new Route($path[$i], HTTPMethod::DELETE, $handler, $handlerMethod));
            }
            $this->routes[] = $rt;
            return $rt;
        }
        else
        {
            $rt = new RouteCollection();
            $rt->addRoute(new Route($path, HTTPMethod::DELETE, $handler, $handlerMethod));
            $this->routes[] = $rt;
            return $rt;
        }
    }

    /**
     * add routes that process requests send with DELETE method
     * @param string|array $path
     * @param string|\Closure|\Wixnit\Routing\Path|\Wixnit\App\View $handler
     * @param mixed $handlerMethod
     * @return RouteCollection
     */
    public function patch(string | array $path, string | Closure | Path | View $handler, ?string $handlerMethod=null): RouteCollection
    {
        if(is_array($path))
        {
            $rt = new RouteCollection();
            for($i = 0; $i < count($path); $i++)
            {
                $rt->addRoute(new Route($path[$i], HTTPMethod::PATCH, $handler, $handlerMethod));
            }
            $this->routes[] = $rt;
            return $rt;
        }
        else
        {
            $rt = new RouteCollection();
            $rt->addRoute(new Route($path, HTTPMethod::PATCH, $handler, $handlerMethod));
            $this->routes[] = $rt;
            return $rt;
        }
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
        if(is_array($path))
        {
            $rt = new RouteCollection();
            for($i = 0; $i < count($path); $i++)
            {
                $rt->addRoute(new Route($path[$i], HTTPMethod::HEAD, $handler, $handlerMethod));
            }
            $this->routes[] = $rt;
            return $rt;
        }
        else
        {
            $rt = new RouteCollection();
            $rt->addRoute(new Route($path, HTTPMethod::HEAD, $handler, $handlerMethod));
            $this->routes[] = $rt;
            return $rt;
        }
    }

    /**
     * resolve requests to their routes
     * @return void
     */
    public function mapRoutes()
    {
        //get the request method
        $method = Router::GetMethod();


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
        for($i = 0; $i < count($routeList); $i++)
        {
            if($routeList[$i]->matches($this->requestURL, $method))
            {
                //get the routed args
                $this->routedArgs = $routeList[$i]->getRoutedArgs();

                //prepre the request object for the request
                $req = $this->buildRequest();

                //run request interceptor
                if($this->requestInterceptor != null)
                {
                    $req = ($this->requestInterceptor)($req);
                }

                //execute the route
                $response = $routeList[$i]->execute($req);

                //run post application interceptor
                if($this->responseInterceptor != null)
                {
                    $response = ($this->responseInterceptor)($response, $routeList[$i]->getPath(), $routeList[$i]->getTag());
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
        return (($method == "post") || ($method == "get") || ($method == "put") || ($method == "patch") || ($method == "delete") || ($method == "option") || ($method == "head"));
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