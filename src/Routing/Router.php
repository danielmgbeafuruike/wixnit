<?php

namespace Wixnit\Routing;

use Wixnit\App\Controller;
use Wixnit\App\View;

class Router
{
    const GET = "GET";
    const POST = "POST";
    const PUT = "PUT";
    const HEAD = "HEAD";
    const OPTION = "OPTION";
    const PATCH = "PATCH";
    const DELETE = "DELETE";
    const ANY = "*";

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


    function __construct($path=null, $dataRoutes=null)
    {
        $this->dataRoute = (is_array($dataRoutes) ? $dataRoutes : []);
        $this->requestURL = trim(parse_url(($path != null) ? $path : ($_SERVER['REQUEST_URI'] ?? ($_SERVER['ORIG_PATH_INFO'] ?? ($_SERVER['PATH_INFO'] ?? ""))))['path'], "/");
        $this->requestURL = $this->stripDataPath($this->requestURL);
        $paths = explode("/", $this->requestURL);

        $this->page = $paths[0];

        for($i = 1; $i < count($paths); $i++)
        {
            $this->args[] = $paths[$i];
        }
    }

    public function Any($route, $arg, $method=null)
    {
        if(is_array($route))
        {
            for($i = 0; $i < count($route); $i++)
            {
                $this->routes[] = ["method"=>"*", "path"=>trim($route[$i], "/"), "arg"=>$arg, "call"=>$method];
            }
        }
        else
        {
            $this->routes[] = ["method"=>"*", "path"=>trim($route, "/"), "arg"=>$arg, "call"=>$method];
        }
    }

    public function Put($route, $arg, $method=null)
    {
        if(is_array($route))
        {
            for($i = 0; $i < count($route); $i++)
            {
                $this->routes[] = ["method"=>"PUT", "path"=>trim($route[$i], "/"), "arg"=>$arg, "call"=>$method];
            }
        }
        else
        {
            $this->routes[] = ["method"=>"PUT", "path"=>trim($route, "/"), "arg"=>$arg, "call"=>$method];
        }
    }

    public function Post($route, $arg, $method=null)
    {
        if(is_array($route))
        {
            for($i = 0; $i < count($route); $i++)
            {
                $this->routes[] = ["method"=>"POST", "path"=>trim($route[$i], "/"), "arg"=>$arg, "call"=>$method];
            }
        }
        else
        {
            $this->routes[] = ["method"=>"POST", "path"=>trim($route, "/"), "arg"=>$arg, "call"=>$method];
        }
    }

    public function Get($route, $arg, $method=null)
    {
        if(is_array($route))
        {
            for($i = 0; $i < count($route); $i++)
            {
                $this->routes[] = ["method"=>"GET", "path"=>trim($route[$i], "/"), "arg"=>$arg, "call"=>$method];
            }
        }
        else
        {
            $this->routes[] = ["method"=>"GET", "path"=>trim($route, "/"), "arg"=>$arg, "call"=>$method];
        }
    }

    public function Delete($route, $arg, $method=null)
    {
        if(is_array($route))
        {
            for($i = 0; $i < count($route); $i++)
            {
                $this->routes[] = ["method"=>"DELETE", "path"=>trim($route[$i], "/"), "arg"=>$arg, "call"=>$method];
            }
        }
        else
        {
            $this->routes[] = ["method"=>"DELETE", "path"=>trim($route, "/"), "arg"=>$arg, "call"=>$method];
        }
    }

    public function Patch($route, $arg, $method=null)
    {
        if(is_array($route))
        {
            for($i = 0; $i < count($route); $i++)
            {
                $this->routes[] = ["method"=>"PATCH", "path"=>trim($route[$i], "/"), "arg"=>$arg, "call"=>$method];
            }
        }
        else
        {
            $this->routes[] = ["method"=>"PATCH", "path"=>trim($route, "/"), "arg"=>$arg, "call"=>$method];
        }
    }

    public function Head($route, $arg, $method=null)
    {
        if(is_array($route))
        {
            for($i = 0; $i < count($route); $i++)
            {
                $this->routes[] = ["method"=>"HEAD", "path"=>trim($route[$i], "/"), "arg"=>$arg, "call"=>$method];
            }
        }
        else
        {
            $this->routes[] = ["method"=>"HEAD", "path"=>trim($route, "/"), "arg"=>$arg, "call"=>$method];
        }
    }

    public function MapRoutes()
    {
        $method = strtoupper((isset($_REQUEST['_method']) && $this->isValidMethod($_REQUEST['_method'])) ? $_REQUEST['_method'] : $_SERVER['REQUEST_METHOD']);

        for($i = 0; $i < count($this->routes); $i++)
        {
            if((($this->routes[$i]['method'] == $method) || ($this->routes[$i]['method'] == "*")) && $this->matchRoute($this->routes[$i]['path'], $this->requestURL))
            {
                if($this->routes[$i]['arg'] instanceof View)
                {
                    $instance = $this->routes[$i]['arg'];
                    $instance->Render();
                }
                else if($this->routes[$i]['arg'] instanceof Path)
                {
                    if(file_exists($this->routes[$i]['arg']->Path))
                    {
                        $instance = $this->routes[$i]['arg'];
                        $instance->Require();
                        return;
                    }
                    if($this->errorPagePath != "")
                    {
                        http_response_code(404);
                        require_once ($this->errorPagePath);
                    }
                    else
                    {
                        $this->showResourceNotFound();
                    }
                }
                else if((new \ReflectionClass($this->routes[$i]['arg']))->isInstantiable() || (($this->routes[$i]['arg'] instanceof Controller)))
                {
                    $ref = new \ReflectionClass($this->routes[$i]['arg']);
                    $instance = (($this->routes[$i]['arg'] instanceof Controller) ? $this->routes[$i]['arg'] : $ref->newInstance());

                    if($this->routes[$i]['call'] != null)
                    {
                        $callMethod = $this->routes[$i]['call'];
                        $instance->$callMethod($this->routedArgs);
                    }
                    else if($instance instanceof Controller)
                    {
                        if($method == "POST")
                        {
                            $instance->Create($this->routedArgs);
                        }
                        else if($method == "PUT")
                        {
                            $instance->Update($this->routedArgs);
                        }
                        else if($method == "DELETE")
                        {
                            $instance->Delete($this->routedArgs);
                        }
                        else if($method == "PATCH")
                        {
                            $instance->Patch($this->routedArgs);
                        }
                        else if($method == "GET")
                        {
                            $instance->Get($this->routedArgs);
                        }
                        else if($method == "HEAD")
                        {
                            $instance->Head($this->routedArgs);
                        }
                        else if($method == "OPTION")
                        {
                            $instance->Option($this->routedArgs);
                        }
                    }
                    else
                    {
                        if($this->errorPagePath != "")
                        {
                            http_response_code(404);
                            require_once ($this->errorPagePath);
                        }
                        else
                        {
                            $this->showResourceNotFound();
                        }
                    }
                }
                else if((is_string($this->routes[$i]['arg']) && function_exists($this->routes[$i]['arg'])) || (is_object($this->routes[$i]['arg']) && ($this->routes[$i]['arg'] instanceof \Closure)))
                {
                    $this->routes[$i]['arg']($_REQUEST);
                }
                else
                {
                    if($this->errorPagePath != "")
                    {
                        http_response_code(404);
                        require_once ($this->errorPagePath);
                    }
                    else
                    {
                        $this->showResourceNotFound();
                    }
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

    public static function Add($request_method, string $route, $arg, string $method=null): array
    {
        return ["method"=>strtoupper($request_method), "path"=>trim($route, "/"), "arg"=>$arg, "call"=>$method];
    }

    public function Group(string $route, array $routes)
    {
        for($i = 0; $i < count($routes); $i++)
        {
            if(array_key_exists("path", $routes[$i]))
            {
                $routes[$i]["path"] = trim($route."/".$routes[$i]['path'], "/");

                if(array_key_exists("method", $routes[$i]) && array_key_exists("arg", $routes[$i]) && array_key_exists("call", $routes[$i]))
                {
                    $this->routes[] = $routes[$i];
                }
            }
        }
    }

    public function HomeRoute($arg, $method=null)
    {
        $this->homePath = $arg;
    }

    public function Redirect($from, $to)
    {
        $this->redirects[] = ["from"=>$from, "to"=>$to];
    }

    public static function ResolvePath($path, $pathInfo=null): string
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

                $ds = isset($_SERVER['ORIG_PATH_INFO']) ? explode("/", trim($_SERVER['ORIG_PATH_INFO']), "/") : explode("/", trim($_SERVER['PATH_INFO'], "/"));
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

        return $prepend.$ret;
    }

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

                $ds = isset($_SERVER['ORIG_PATH_INFO']) ? explode("/", trim($_SERVER['ORIG_PATH_INFO']), "/") : explode("/", trim($_SERVER['PATH_INFO'], "/"));
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

        $dPath = Router::GetDataRoutString();
        return $prepend.(($dPath != "") ? Router::GetDataRoutString()."/" : "").$ret;
    }

    public function GlobalizeDataRoutes()
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

    public static function GetDataRoutString(): string
    {
        if(isset($GLOBALS['data-route-string']))
        {
            return $GLOBALS['data-route-string'];
        }
        return "";
    }

    public static function GetDataRoutes(): array
    {
        if(isset($GLOBALS['data-routes']))
        {
            return $GLOBALS['data-routes'];
        }
        return [];
    }

    public static function GetDataRouteArgs(): array
    {
        if(isset($GLOBALS['route-args']))
        {
            return $GLOBALS['route-args'];
        }
        return [];
    }

    public static function CurrentPath(): string
    {
        if(isset($GLOBALS['current-path']))
        {
            return $GLOBALS['current-path'];
        }
        return "";
    }

    public static function URLify($arg): string
    {
        $tr = explode(" ", strtolower(trim($arg)));

        $ret = "";

        for($i = 0; $i < count($tr); $i++)
        {
            if($ret == "")
            {
                $ret .= $tr[$i];
            }
            else
            {
                $ret .= "-". $tr[$i];
            }
        }
        return $ret;
    }

    public function DataRoute(array $routes=[])
    {
        $this->dataRoute = $routes;
    }

    private function isValidMethod($method): bool
    {
        $pp = strtolower(trim($method));
        return (($method == "post") || ($method == "get") || ($method == "put") || ($method == "patch") || ($method == "delete") || ($method == "option") || ($method == "head"));
    }

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
     * @param $route
     * @param $current_url
     * @return bool
     * @comment used for regexing routes internally
     */
    private function matchRoute($route, $current_url): bool
    {
        $r = explode("/", $route);
        $c = explode("/", $current_url);

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
        return false;
    }

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
}