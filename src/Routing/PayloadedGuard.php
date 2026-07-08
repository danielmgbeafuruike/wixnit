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

        public function addPayload(mixed $payload): void
        {
            if(is_null($this->payload))
            {
                $this->payload = $payload;
            }
            else if(is_array($this->payload))
            {
                $this->payload[] = $payload;
            }
            else
            {
                $this->payload = [$this->payload, $payload];
            }
        }
    }