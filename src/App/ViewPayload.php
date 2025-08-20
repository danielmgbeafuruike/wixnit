<?php

    namespace Wixnit\App;

    use Wixnit\Routing\Request;

    class ViewPayload
    {
        public Request $request;
        public array $payload = [];


        function __construct()
        {
            $this->request = new Request();
        }

        public static function Init(): ViewPayload
        {
            $ret = new ViewPayload();

            if(isset($GLOBALS['WIXNIT_VIEW_PAYLOAD']))
            {
                $pl = $GLOBALS['WIXNIT_VIEW_PAYLOAD'];

                if(isset($pl['request']))
                {
                    $ret->request = $pl['request'];
                }
                if(isset($pl['args']))
                {
                    $ret->payload = $pl['args'];
                }
            }
            return $ret;
        }
    }