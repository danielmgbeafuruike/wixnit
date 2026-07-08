<?php

    namespace Wixnit\Exception;

    use Exception;

    /**
     * Thrown for mistakes in how validation rules themselves are *written* - an unknown
     * rule name, or a parameterized rule missing its parameter - as distinct from a
     * ValidationError, which represents a piece of *data* failing a correctly-defined rule.
     * This is caught early (as soon as test() runs), aimed at the developer defining the
     * rule string, not the end user submitting data.
     */
    class ValidationConfigurationException extends Exception
    {
        public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }

        /**
         * @param string $rule the unrecognized rule name
         * @param string $field the field it was declared on
         * @param array $known the list of registered rule names, for a "did you mean" style hint
         * @return self
         */
        public static function UnknownRule(string $rule, string $field, array $known = []): self
        {
            $suggestion = "Check for a typo in the rule string for '{$field}'.";

            $closest = self::closestMatch($rule, $known);
            if($closest !== null)
            {
                $suggestion .= " Did you mean '{$closest}'?";
            }
            else
            {
                $suggestion .= " Register custom rules with Validation::extend('{$rule}', ...) before calling test().";
            }

            return new self(
                "Unknown validation rule '{$rule}' used on field '{$field}'.\n".
                "  Why: '{$rule}' isn't one of the built-in rules and hasn't been registered with Validation::extend().\n".
                "  Suggestion: {$suggestion}"
            );
        }

        /**
         * @param string $rule the rule that requires a parameter
         * @param string $field the field it was declared on
         * @param string $example an example of correctly-parameterized usage
         * @return self
         */
        public static function MissingParameter(string $rule, string $field, string $example): self
        {
            return new self(
                "Rule '{$rule}' on field '{$field}' is missing its required parameter.\n".
                "  Why: '{$rule}' needs a value after a colon to know what to check against.\n".
                "  Fix: write it as '{$example}'."
            );
        }

        /**
         * Best-effort "did you mean" suggestion using Levenshtein distance against known rule names.
         * @param string $rule
         * @param array $known
         * @return string|null
         */
        private static function closestMatch(string $rule, array $known): ?string
        {
            $best = null;
            $bestDistance = null;

            foreach($known as $candidate)
            {
                $distance = levenshtein($rule, $candidate);

                if(($distance <= 2) && (($bestDistance === null) || ($distance < $bestDistance)))
                {
                    $best = $candidate;
                    $bestDistance = $distance;
                }
            }
            return $best;
        }
    }
