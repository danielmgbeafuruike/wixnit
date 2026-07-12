<?php

    namespace Wixnit\Exception;

    use Exception;
    use Throwable;

    class ViewRenderException extends Exception
    {
        public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }

        /**
         * Wraps whatever went wrong while a specific view file was rendering, so the
         * final exception (and its chain of ->getPrevious()) shows the whole path -
         * e.g. "layout.php" -> "page.php" -> "card.php" -> the original TypeError -
         * instead of just a raw error pointing at an anonymous line inside View.php.
         */
        public static function InView(string $viewPath, Throwable $previous): self
        {
            return new self(sprintf('Error rendering view "%s": %s', $viewPath, $previous->getMessage()), 0, $previous);
        }

        public static function UnmatchedBlock(string $call, string $viewPath): self
        {
            return new self(sprintf('%s() called without a matching opening call while rendering "%s"', $call, $viewPath));
        }
    }
