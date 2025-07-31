<?php

    namespace Wixnit\Interfaces;

    use Wixnit\Routing\Request;
    use Wixnit\Routing\Response;

    interface IRouteGuard
    {
        public function checkAccess(Request $req): bool;

        public function onFail(): Response;
    }