<?php

    namespace Wixnit\Validation;

    /**
     * A single validation failure: which field failed, which rule caught it, the value
     * that was rejected, and a human-readable message explaining why.
     */
    class ValidationError
    {
        public string $field;
        public string $rule;
        public mixed $value;
        public string $message;

        function __construct(string $field = "", string $rule = "", mixed $value = null, string $message = "")
        {
            $this->field = $field;
            $this->rule = $rule;
            $this->value = $value;
            $this->message = $message;
        }

        /**
         * Shape used by getErrors()/Api::ValidationError() - kept flat and array-based
         * since that's the most convenient shape for JSON API error responses.
         * @return array{field: string, rule: string, message: string}
         */
        public function toArray(): array
        {
            return [
                "field" => $this->field,
                "rule" => $this->rule,
                "message" => $this->message,
            ];
        }

        public function __toString(): string
        {
            return $this->message;
        }
    }
