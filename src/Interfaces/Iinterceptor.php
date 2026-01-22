<?php

    namespace Wixnit\Interfaces;

    use Wixnit\App\View;
    use Wixnit\Routing\Request;
    use Wixnit\Routing\Response;

    interface IInterceptor
    {
        public function handle(Response | null $respnse, string $route, string $tag): Response | null;
    }