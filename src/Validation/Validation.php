<?php

    namespace Wixnit\Validation;

    use Wixnit\Exception\ValidationConfigurationException;

    /**
     * Validates a set of data against Laravel-style pipe-delimited rule strings:
     *
     *   $validation = new Validation($requestData);
     *   $validation->addValues([
     *       "name"     => "string|required",
     *       "password" => "string|required|min:6",
     *       "phone"    => "phone|required|max:12",
     *       "email"    => "email|required",
     *   ]);
     *
     *   if(!$validation->test())
     *   {
     *       print_r($validation->getErrors());
     *   }
     *
     * Rules are pipe-separated ("string|required|min:6"); a rule taking parameters uses a
     * colon, with multiple parameters comma-separated ("between:1,10", "in:a,b,c"). See
     * ValidationRuleRegistry for the full built-in rule set, and Validation::extend() to
     * register your own.
     */
    class Validation
    {
        private array $arg_data = [];

        //protected fields used for validating
        protected array $values = [];
        protected array $valueValidations = [];
        protected array $customMessages = [];
        protected array $customLabels = [];
        protected bool $stopOnFirstFailure = false;
        protected ?bool $lastResult = null;

        //public fields populated when test() is called
        public array $errorValues = [];
        public string $errorText = "";
        public array $errors = [];

        /**
         * @param array|null $request_data the data to validate against
         */
        public function __construct(array $request_data = null)
        {
            $this->arg_data = ($request_data != null) ? $request_data : $this->arg_data;
        }

        /**
         * Convenience constructor: build, configure, and return a Validation instance in one call.
         * @param array $data
         * @param array $rules field => rule string pairs, same shape as addValues()
         * @param array $messages optional custom messages, same shape as setMessages()
         * @return Validation
         */
        public static function make(array $data, array $rules, array $messages = []): Validation
        {
            $validation = new Validation($data);
            $validation->addValues($rules);

            if(count($messages) > 0)
            {
                $validation->setMessages($messages);
            }
            return $validation;
        }

        /**
         * Register a custom rule available to every Validation instance.
         * @param string $name
         * @param callable $test function(mixed $value, array $params, array $context): bool
         * @param callable|string|null $message function(array $params, array $context): string, or a plain string
         * @param bool $requiresParams whether the rule must be written with a ":param" (e.g. "min:6")
         * @param string $example shown in the configuration error if requiresParams is true and none was given
         * @return void
         */
        public static function extend(string $name, callable $test, callable|string|null $message = null, bool $requiresParams = false, string $example = ""): void
        {
            ValidationRuleRegistry::register($name, $test, $message, $requiresParams, $example);
        }

        /**
         * Replace the data being validated (the constructor-only limitation this used to have).
         * @param array $data
         * @return static
         */
        public function setData(array $data): static
        {
            $this->arg_data = $data;
            $this->lastResult = null;
            return $this;
        }

        /**
         * Add values to be validated. Existing rules for a field already added are replaced.
         * @param array $args field => rule string pairs, e.g. ["email" => "email|required"]
         * @return static
         */
        public function addValues(array $args = []): static
        {
            foreach($args as $field => $rule)
            {
                $this->addValue((string)$field, (string)$rule);
            }
            return $this;
        }

        /**
         * Add (or replace) the rule string for a single field.
         * @param string $field
         * @param string $rule
         * @return static
         */
        public function addValue(string $field, string $rule): static
        {
            $existingIndex = array_search($field, $this->values, true);

            if($existingIndex !== false)
            {
                $this->valueValidations[$existingIndex] = $rule;
            }
            else
            {
                $this->values[] = $field;
                $this->valueValidations[] = $rule;
            }
            $this->lastResult = null;
            return $this;
        }

        /**
         * Override the default message for a specific field, optionally for one specific rule
         * on that field. Resolution order when a field fails a rule is: field+rule specific,
         * then field-generic, then the rule's own default message.
         * @param string $field
         * @param string $messageOrRule if $message is null, this is the message for ALL rules on the field;
         *                              if $message is given, this is the specific rule name it applies to
         * @param string|null $message
         * @return static
         */
        public function setMessage(string $field, string $messageOrRule, ?string $message = null): static
        {
            if($message === null)
            {
                $this->customMessages[$field]["*"] = $messageOrRule;
            }
            else
            {
                $this->customMessages[$field][strtolower($messageOrRule)] = $message;
            }
            return $this;
        }

        /**
         * Bulk version of setMessage(). Accepts either ["field" => "message"] for a field-wide
         * override, or ["field.rule" => "message"] for a rule-specific override.
         * @param array $messages
         * @return static
         */
        public function setMessages(array $messages): static
        {
            foreach($messages as $key => $message)
            {
                if(str_contains($key, "."))
                {
                    [$field, $rule] = explode(".", $key, 2);
                    $this->setMessage($field, $rule, $message);
                }
                else
                {
                    $this->setMessage($key, $message);
                }
            }
            return $this;
        }

        /**
         * Give a field a friendlier name for use in default (non-overridden) messages,
         * e.g. setLabel('dob', 'date of birth') so errors read "date of birth is required"
         * instead of "dob is required".
         * @param string $field
         * @param string $label
         * @return static
         */
        public function setLabel(string $field, string $label): static
        {
            $this->customLabels[$field] = $label;
            return $this;
        }

        /**
         * Bulk version of setLabel(): ["field" => "friendly label"].
         * @param array $labels
         * @return static
         */
        public function setLabels(array $labels): static
        {
            foreach($labels as $field => $label)
            {
                $this->setLabel((string)$field, (string)$label);
            }
            return $this;
        }

        /**
         * When enabled, test() stops at the first field that fails instead of collecting
         * every failure across every field. Off by default.
         * @param bool $stop
         * @return static
         */
        public function stopOnFirstFailure(bool $stop = true): static
        {
            $this->stopOnFirstFailure = $stop;
            return $this;
        }

        /**
         * Run every field's rules against the data. Populates errors/errorValues/errorText.
         * @return bool true if every field passed
         */
        public function test(): bool
        {
            $this->errors = [];
            $this->errorValues = [];
            $this->errorText = "";

            for($i = 0; $i < count($this->values); $i++)
            {
                $field = $this->values[$i];
                $ruleString = $this->valueValidations[$i] ?? "";

                $this->testField($field, $ruleString);

                if($this->stopOnFirstFailure && (count($this->errors) > 0))
                {
                    break;
                }
            }

            $this->lastResult = (count($this->errors) === 0);
            return $this->lastResult;
        }

        /**
         * Runs test() if it hasn't already been run (or the data/rules have changed since),
         * and returns whether validation failed. Convenient for `if($validation->fails())`.
         * @return bool
         */
        public function fails(): bool
        {
            if($this->lastResult === null)
            {
                $this->test();
            }
            return !$this->lastResult;
        }

        /**
         * The inverse of fails() - runs test() if needed, returns whether everything passed.
         * @return bool
         */
        public function passes(): bool
        {
            return !$this->fails();
        }

        /**
         * Returns only the declared fields that passed validation and were actually present in
         * the data, as a clean whitelist safe to use directly - handy for stripping unexpected
         * keys out of raw request data before passing it on (e.g. into a Model's save()).
         * @return array
         */
        public function validated(): array
        {
            $this->fails(); //ensure test() has run

            $failed = array_unique($this->errorValues);
            $out = [];

            foreach($this->values as $field)
            {
                if(in_array($field, $failed, true))
                {
                    continue;
                }
                if($this->hasValue($this->arg_data, $field))
                {
                    $out[$field] = $this->getValue($this->arg_data, $field);
                }
            }
            return $out;
        }

        /**
         * @param string $field
         * @return string|null the first error message for a specific field, or null if it has none
         */
        public function firstError(string $field): ?string
        {
            foreach($this->errors as $error)
            {
                if($error instanceof ValidationError && ($error->field === $field))
                {
                    return $error->message;
                }
            }
            return null;
        }

        /**
         * Validate one field's rule string against the current data.
         * @param string $field
         * @param string $ruleString
         * @return void
         */
        private function testField(string $field, string $ruleString): void
        {
            $rules = $this->parseRules($ruleString, $field);
            $ruleNames = array_column($rules, "name");

            $present = $this->hasValue($this->arg_data, $field);
            $value = $present ? $this->getValue($this->arg_data, $field) : null;
            $isEmpty = (!$present) || ($value === null) || ($value === "") || (is_array($value) && (count($value) === 0));

            if($isEmpty)
            {
                if($this->isRequired($rules, $this->arg_data))
                {
                    $this->addError($field, "required", $value, $this->resolveMessage($field, "required", [], fn() => $this->label($field) . " is required"));
                }
                return;
            }

            $sizeMode = in_array("array", $ruleNames, true)
                ? "array"
                : ((array_intersect(["number", "numeric", "integer", "int", "float", "decimal"], $ruleNames)) ? "numeric" : "string");

            $context = [
                "field" => $field,
                "label" => $this->label($field),
                "data" => $this->arg_data,
                "sizeMode" => $sizeMode,
                "ruleNames" => $ruleNames,
            ];

            foreach($rules as $ruleDef)
            {
                $name = $ruleDef['name'];

                if(in_array($name, ["required", "nullable", "sometimes", "required_if", "required_unless"], true))
                {
                    continue;
                }

                if(!ValidationRuleRegistry::has($name))
                {
                    throw ValidationConfigurationException::UnknownRule($name, $field, ValidationRuleRegistry::names());
                }

                $rule = ValidationRuleRegistry::get($name);

                if($rule['requires'] && (count($ruleDef['params']) === 0 || $ruleDef['params'][0] === ""))
                {
                    throw ValidationConfigurationException::MissingParameter($name, $field, $rule['example'] ?: "{$name}:value");
                }

                $passed = ($rule['test'])($value, $ruleDef['params'], $context);

                if(!$passed)
                {
                    $default = ($rule['message'])($ruleDef['params'], $context);
                    $this->addError($field, $name, $value, $this->resolveMessage($field, $name, $ruleDef['params'], fn() => $default));

                    if($this->stopOnFirstFailure)
                    {
                        return;
                    }
                }
            }
        }

        /**
         * @param string $field
         * @param string $rule
         * @param mixed $value
         * @param string $message
         * @return void
         */
        private function addError(string $field, string $rule, mixed $value, string $message): void
        {
            $this->errors[] = new ValidationError($field, $rule, $value, $message);
            $this->errorValues[] = $field;
        }

        /**
         * Resolve the message for a field/rule combination: field+rule specific override,
         * then field-wide override, then the rule's own default.
         * @param string $field
         * @param string $rule
         * @param array $params
         * @param callable $default function(): string
         * @return string
         */
        private function resolveMessage(string $field, string $rule, array $params, callable $default): string
        {
            if(isset($this->customMessages[$field][strtolower($rule)]))
            {
                return $this->customMessages[$field][strtolower($rule)];
            }
            if(isset($this->customMessages[$field]["*"]))
            {
                return $this->customMessages[$field]["*"];
            }
            return $default();
        }

        /**
         * @param string $field
         * @return string
         */
        private function label(string $field): string
        {
            return $this->customLabels[$field] ?? ValidationRuleRegistry::humanize($field);
        }

        /**
         * @param array $rules parsed rules for the field, as returned by parseRules()
         * @param array $data
         * @return bool
         */
        private function isRequired(array $rules, array $data): bool
        {
            foreach($rules as $rule)
            {
                if($rule['name'] === "required")
                {
                    return true;
                }
                if(($rule['name'] === "required_if") && (count($rule['params']) >= 2))
                {
                    $other = $this->getValue($data, $rule['params'][0]);
                    if((string)$other === (string)$rule['params'][1])
                    {
                        return true;
                    }
                }
                if(($rule['name'] === "required_unless") && (count($rule['params']) >= 2))
                {
                    $other = $this->getValue($data, $rule['params'][0]);
                    if((string)$other !== (string)$rule['params'][1])
                    {
                        return true;
                    }
                }
            }
            return false;
        }

        /**
         * Splits a "string|required|min:6" style rule string into structured rule definitions.
         * The regex rule is treated specially so a pattern containing commas isn't split apart.
         * @param string $ruleString
         * @param string $field used only to produce a clearer exception if the string is malformed
         * @return array<array{name: string, params: array}>
         */
        private function parseRules(string $ruleString, string $field): array
        {
            $result = [];

            foreach(explode("|", $ruleString) as $token)
            {
                $token = trim($token);

                if($token === "")
                {
                    continue;
                }

                if(str_contains($token, ":"))
                {
                    [$name, $rest] = explode(":", $token, 2);
                    $name = strtolower(trim($name));

                    $params = ($name === "regex") ? [$rest] : array_map("trim", explode(",", $rest));
                }
                else
                {
                    $name = strtolower($token);
                    $params = [];
                }
                $result[] = ["name" => $name, "params" => $params];
            }
            return $result;
        }

        /**
         * Dot-notation-aware existence check ("address.city" reaches into nested arrays).
         * A key that literally contains a dot is checked directly first.
         * @param array $data
         * @param string $key
         * @return bool
         */
        private function hasValue(array $data, string $key): bool
        {
            if(array_key_exists($key, $data))
            {
                return true;
            }
            if(!str_contains($key, "."))
            {
                return false;
            }

            $current = $data;
            foreach(explode(".", $key) as $segment)
            {
                if(!is_array($current) || !array_key_exists($segment, $current))
                {
                    return false;
                }
                $current = $current[$segment];
            }
            return true;
        }

        /**
         * Dot-notation-aware value getter - see hasValue().
         * @param array $data
         * @param string $key
         * @return mixed
         */
        private function getValue(array $data, string $key): mixed
        {
            if(array_key_exists($key, $data))
            {
                return $data[$key];
            }
            if(!str_contains($key, "."))
            {
                return null;
            }

            $current = $data;
            foreach(explode(".", $key) as $segment)
            {
                if(!is_array($current) || !array_key_exists($segment, $current))
                {
                    return null;
                }
                $current = $current[$segment];
            }
            return $current;
        }

        /**
         * get the error text
         * @return string
         */
        public function getErrorText(): string
        {
            $this->errorText = "";

            foreach($this->errors as $error)
            {
                $this->errorText .= $error->field . ": " . $error->message . "\n";
            }
            return $this->errorText;
        }

        /**
         * get the fields that failed validation
         * @return array
         */
        public function getErrorValues(): array
        {
            return $this->errorValues;
        }

        /**
         * get the errors, each as ["field" => ..., "rule" => ..., "message" => ...]
         * @return array
         */
        public function getErrors(): array
        {
            return array_map(fn(ValidationError $e) => $e->toArray(), $this->errors);
        }

        /**
         * get the raw ValidationError objects, for callers that want more than the flat array shape
         * @return ValidationError[]
         */
        public function getErrorObjects(): array
        {
            return $this->errors;
        }
    }
