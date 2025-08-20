<?php

    namespace Wixnit\Interfaces;

    use Wixnit\App\View;
    use Wixnit\Routing\Request;
    use Wixnit\Routing\Response;

    interface IRouteGuard
    {
        public function checkAccess(Request $req): bool;

        public function onFail(): Response | View;
    }