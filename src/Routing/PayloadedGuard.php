<?php

    namespace Wixnit\Routing;

    abstract class PayloadedGuard
    {
        private $payload = null;

        public function setPayload(mixed $paload): void
        {
            $this->payload = $paload;
        }

        public function getPaylaod(): mixed
        {
            return $this->payload;
        }
    }