<?php

    namespace Wixnit\App;

    use Wixnit\Interfaces\ITranslator;

    class Views
    {
        private string $base_path = "";
        private array $routerArgs = [];
        private ITranslator $translator;

        function __construct(string $root_path, ITranslator | null $translator =null, array $routeArgs = [])
        {
            $this->base_path = rtrim($root_path, '/');

            if($translator != null)
            {
                $this->translator = $translator;
            }
            if(count($routeArgs) > 0)
            {
                $this->routerArgs = $routeArgs;
            }
        }

        public function get(string $viewName, ITranslator | null $translator =null, array $routeData = [])
        {
            $ret = new View($this->base_path."/".trim($viewName, '/'));

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
            else if(count($this->routerArgs) > 0)
            {
                $ret->setDataRoutes($this->routerArgs);
            }
            return $ret;
        }
    }