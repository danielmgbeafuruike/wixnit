<?php

    namespace wixnit\App;

    class Views
    {
        private string $base_path = "";

        function __construct(string $root_path)
        {
            $this->base_path = rtrim($root_path, '/');
        }

        public function get(string $viewName)
        {
            return new View($this->base_path."/".trim($viewName, '/'));
        }
    }