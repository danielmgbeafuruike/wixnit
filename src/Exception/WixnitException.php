<?php

    namespace Wixnit\Exception;

    use Exception;
    use Throwable;

    /**
     * Base class for all of Wixnit's own exceptions.
     *
     * On top of the normal Exception behaviour, it keeps a bag of extra
     * "context" (arbitrary key/value pairs describing what was being attempted -
     * a file path, a table name, an expected type, etc) so error messages can
     * stay short while still exposing everything useful for debugging via
     * getContext()/getDetails().
     */
    class WixnitException extends Exception
    {
        /**
         * @var array<string, mixed> extra structured context about what went wrong
         */
        protected array $context = [];

        public function __construct(string $message = "", array $context = [], int $code = 0, ?Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
            $this->context = $context;
        }

        /**
         * get the structured context that was attached to this exception (file paths, values involved, etc)
         * @return array<string, mixed>
         */
        public function getContext(): array
        {
            return $this->context;
        }

        /**
         * build a full, human readable explanation of the error: the message, where it
         * happened, any context that was attached, and the chain of previous exceptions
         * that caused it (if any). Meant to be safe to log or show to a developer.
         * @return string
         */
        public function getDetails(): string
        {
            $lines = [];
            $lines[] = get_class($this).": ".$this->getMessage();
            $lines[] = "at ".$this->getFile().":".$this->getLine();

            if(count($this->context) > 0)
            {
                $lines[] = "context: ".json_encode($this->context);
            }

            $previous = $this->getPrevious();
            while($previous != null)
            {
                $lines[] = "caused by ".get_class($previous).": ".$previous->getMessage();
                $previous = $previous->getPrevious();
            }
            return implode("\n", $lines);
        }

        public function __toString(): string
        {
            return $this->getDetails();
        }
    }
