<?php

    namespace Wixnit\App;

    use Wixnit\Routing\Request;

    class ViewPayload
    {
        /**
         * Container key View and ViewPayload use to bridge render-time data across to
         * the template being rendered - a single shared constant instead of the literal
         * string "WIXNIT_VIEW_PAYLOAD" being duplicated across two files, and backed by
         * Container (request-scoped-in-intent) rather than a raw $GLOBALS entry that
         * outlives the render it was meant for.
         */
        public const CONTAINER_KEY = "wixnit.view.payload";

        public ?Request $request = null;
        public array $payload = [];

        /**
         * Build a ViewPayload from whatever View most recently set via Container.
         * Returns an "empty" ViewPayload (null request, empty payload array) if called
         * outside of a render - rather than a Request object that looks valid but isn't.
         * @return ViewPayload
         */
        public static function Init(): ViewPayload
        {
            $ret = new ViewPayload();

            if(Container::has(self::CONTAINER_KEY))
            {
                $pl = Container::get(self::CONTAINER_KEY);

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

        /**
         * Read a single payload value, with a default when it isn't present, instead
         * of raw array access.
         * @param string $key
         * @param mixed $default
         * @return mixed
         */
        public function get(string $key, mixed $default = null): mixed
        {
            return $this->payload[$key] ?? $default;
        }
    }
