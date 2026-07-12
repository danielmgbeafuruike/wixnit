<?php

    namespace Wixnit\Exception;

    class EventException extends WixnitException
    {
        public static function InvalidListener(string $eventClass, mixed $listener): self
        {
            $description = is_object($listener) ? get_class($listener) : (is_string($listener) ? $listener : gettype($listener));

            return new self(
                "Invalid listener for event '$eventClass': '$description' is not callable and ".
                "doesn't have a public handle() method.",
                ["eventClass" => $eventClass, "listener" => $description]
            );
        }

        public static function ListenerFailed(string $eventClass, string $listenerDescription, string $reason): self
        {
            return new self(
                "Listener '$listenerDescription' for event '$eventClass' failed: $reason",
                ["eventClass" => $eventClass, "listener" => $listenerDescription]
            );
        }
    }
