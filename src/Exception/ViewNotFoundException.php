<?php

    namespace Wixnit\Exception;

    use Exception;
    use Throwable;

    class ViewNotFoundException extends Exception
    {
        public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }

        public static function AtPath(string $viewName): self
        {
            return new self(sprintf('The view "%s" was not found', basename($viewName)));
        }
    }
