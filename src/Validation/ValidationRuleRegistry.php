<?php

    namespace Wixnit\Validation;

    use DateTime;

    /**
     * Registry of validation rules keyed by name. Each rule is:
     *   [
     *     'test'     => function(mixed $value, array $params, array $context): bool,
     *     'message'  => function(array $params, array $context): string,
     *     'requires' => bool,     //whether this rule needs at least one ":param"
     *     'example'  => string,   //shown in the error if 'requires' is true and none was given
     *   ]
     *
     * $context is always: ['field' => string, 'label' => string, 'data' => array, 'sizeMode' => string, 'ruleNames' => array]
     *
     * Built-ins are registered once, lazily, the first time the registry is used. Custom
     * rules can be added at any time with Validation::extend() (a thin wrapper around
     * ValidationRuleRegistry::register()) and are available to every Validation instance.
     */
    class ValidationRuleRegistry
    {
        private static array $rules = [];
        private static bool $booted = false;

        /**
         * Register a rule (built-in or custom). Re-registering an existing name overwrites it,
         * so this also doubles as the mechanism for overriding a built-in rule's behavior/message.
         * @param string $name
         * @param callable $test function(mixed $value, array $params, array $context): bool
         * @param callable|string|null $message function(array $params, array $context): string, or a plain string
         * @param bool $requiresParams
         * @param string $example
         * @return void
         */
        public static function register(string $name, callable $test, callable|string|null $message = null, bool $requiresParams = false, string $example = ""): void
        {
            self::boot();

            $name = strtolower(trim($name));

            self::$rules[$name] = [
                'test' => $test,
                'message' => is_string($message)
                    ? fn($params, $context) => $message
                    : ($message ?? fn($params, $context) => "{$context['label']} is not valid"),
                'requires' => $requiresParams,
                'example' => $example,
            ];
        }

        /**
         * @param string $name
         * @return bool
         */
        public static function has(string $name): bool
        {
            self::boot();
            return isset(self::$rules[strtolower($name)]);
        }

        /**
         * @param string $name
         * @return array|null
         */
        public static function get(string $name): ?array
        {
            self::boot();
            return self::$rules[strtolower($name)] ?? null;
        }

        /**
         * @return array<string> every registered rule name, built-in and custom
         */
        public static function names(): array
        {
            self::boot();
            return array_keys(self::$rules);
        }

        /**
         * Measure a value according to which "mode" its sibling rules imply - a numeric
         * rule means min/max/between/size compare the numeric value itself, an array rule
         * means they compare element count, and otherwise they compare string length.
         * @param mixed $value
         * @param string $mode "numeric"|"array"|"string"
         * @return int|float
         */
        public static function sizeOf(mixed $value, string $mode): int|float
        {
            return match($mode)
            {
                "array" => is_array($value) ? count($value) : 0,
                "numeric" => is_numeric($value) ? ($value + 0) : 0,
                default => mb_strlen((string)$value),
            };
        }

        private static function boot(): void
        {
            if(self::$booted)
            {
                return;
            }
            self::$booted = true;
            self::registerBuiltIns();
        }

        private static function registerBuiltIns(): void
        {
            //#region type rules

            self::register("string",
                fn($v, $p, $c) => is_string($v),
                fn($p, $c) => "{$c['label']} must be a string"
            );

            self::register("number",
                fn($v, $p, $c) => is_numeric($v),
                fn($p, $c) => "{$c['label']} must be a number"
            );
            self::register("numeric", ...self::alias("number"));

            self::register("integer",
                fn($v, $p, $c) => filter_var($v, FILTER_VALIDATE_INT) !== false,
                fn($p, $c) => "{$c['label']} must be a whole number"
            );
            self::register("int", ...self::alias("integer"));

            self::register("float",
                fn($v, $p, $c) => filter_var($v, FILTER_VALIDATE_FLOAT) !== false,
                fn($p, $c) => "{$c['label']} must be a decimal number"
            );
            self::register("decimal", ...self::alias("float"));

            self::register("bool",
                fn($v, $p, $c) => in_array($v, [true, false, 0, 1, "0", "1", "true", "false"], true),
                fn($p, $c) => "{$c['label']} must be true or false"
            );
            self::register("boolean", ...self::alias("bool"));

            self::register("array",
                fn($v, $p, $c) => is_array($v),
                fn($p, $c) => "{$c['label']} must be a list of values"
            );

            //#endregion

            //#region format rules

            self::register("email",
                fn($v, $p, $c) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
                fn($p, $c) => "{$c['label']} must be a valid email address"
            );

            self::register("phone",
                function($v, $p, $c)
                {
                    $stripped = preg_replace('/[^\d+]/', '', (string)$v);
                    return preg_match('/^\+?\d{7,15}$/', $stripped) === 1;
                },
                fn($p, $c) => "{$c['label']} must be a valid phone number"
            );

            self::register("url",
                function($v, $p, $c)
                {
                    $candidate = preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', (string)$v) ? $v : "http://" . $v;
                    return filter_var($candidate, FILTER_VALIDATE_URL) !== false;
                },
                fn($p, $c) => "{$c['label']} must be a valid URL"
            );
            self::register("link", ...self::alias("url"));
            self::register("website", ...self::alias("url"));
            self::register("uri", ...self::alias("url"));

            self::register("alpha",
                fn($v, $p, $c) => preg_match('/^[a-zA-Z]+$/', (string)$v) === 1,
                fn($p, $c) => "{$c['label']} may only contain letters"
            );

            self::register("alpha_num",
                fn($v, $p, $c) => preg_match('/^[a-zA-Z0-9]+$/', (string)$v) === 1,
                fn($p, $c) => "{$c['label']} may only contain letters and numbers"
            );
            self::register("alphanumeric", ...self::alias("alpha_num"));

            self::register("plain",
                fn($v, $p, $c) => preg_match("/^[a-zA-Z\-' ]*$/", (string)$v) === 1,
                fn($p, $c) => "{$c['label']} may only contain letters, spaces, hyphens and apostrophes"
            );

            self::register("uuid",
                fn($v, $p, $c) => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string)$v) === 1,
                fn($p, $c) => "{$c['label']} must be a valid UUID"
            );

            self::register("ip",
                fn($v, $p, $c) => filter_var($v, FILTER_VALIDATE_IP) !== false,
                fn($p, $c) => "{$c['label']} must be a valid IP address"
            );
            self::register("ipv4",
                fn($v, $p, $c) => filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
                fn($p, $c) => "{$c['label']} must be a valid IPv4 address"
            );
            self::register("ipv6",
                fn($v, $p, $c) => filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
                fn($p, $c) => "{$c['label']} must be a valid IPv6 address"
            );

            self::register("json",
                function($v, $p, $c)
                {
                    if(!is_string($v)) return false;
                    json_decode($v);
                    return json_last_error() === JSON_ERROR_NONE;
                },
                fn($p, $c) => "{$c['label']} must be valid JSON"
            );

            //#endregion

            //#region date/time rules

            self::register("date",
                fn($v, $p, $c) => strtotime((string)$v) !== false,
                fn($p, $c) => "{$c['label']} must be a valid date"
            );

            self::register("date_format",
                function($v, $p, $c)
                {
                    $format = $p[0] ?? "Y-m-d";
                    $parsed = DateTime::createFromFormat($format, (string)$v);
                    return ($parsed !== false) && ($parsed->format($format) === $v);
                },
                fn($p, $c) => "{$c['label']} must match the format " . ($p[0] ?? "Y-m-d"),
                requiresParams: true,
                example: "date_format:Y-m-d"
            );

            self::register("time",
                fn($v, $p, $c) => preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string)$v) === 1,
                fn($p, $c) => "{$c['label']} must be a valid time (HH:MM or HH:MM:SS)"
            );

            self::register("after",
                function($v, $p, $c)
                {
                    $other = $c['data'][$p[0]] ?? ($p[0] ?? null);
                    $value = strtotime((string)$v);
                    $bound = strtotime((string)$other);
                    return ($value !== false) && ($bound !== false) && ($value > $bound);
                },
                fn($p, $c) => "{$c['label']} must be a date after " . ($p[0] ?? ""),
                requiresParams: true,
                example: "after:2024-01-01"
            );

            self::register("before",
                function($v, $p, $c)
                {
                    $other = $c['data'][$p[0]] ?? ($p[0] ?? null);
                    $value = strtotime((string)$v);
                    $bound = strtotime((string)$other);
                    return ($value !== false) && ($bound !== false) && ($value < $bound);
                },
                fn($p, $c) => "{$c['label']} must be a date before " . ($p[0] ?? ""),
                requiresParams: true,
                example: "before:2024-01-01"
            );

            //#endregion

            //#region size rules

            self::register("min",
                fn($v, $p, $c) => self::sizeOf($v, $c['sizeMode']) >= (float)($p[0] ?? 0),
                fn($p, $c) => rtrim("{$c['label']} must be at least {$p[0]} " . self::sizeUnit($c)),
                requiresParams: true,
                example: "min:6"
            );

            self::register("max",
                fn($v, $p, $c) => self::sizeOf($v, $c['sizeMode']) <= (float)($p[0] ?? 0),
                fn($p, $c) => rtrim("{$c['label']} must be at most {$p[0]} " . self::sizeUnit($c)),
                requiresParams: true,
                example: "max:12"
            );

            self::register("between",
                function($v, $p, $c)
                {
                    $size = self::sizeOf($v, $c['sizeMode']);
                    return ($size >= (float)($p[0] ?? 0)) && ($size <= (float)($p[1] ?? 0));
                },
                fn($p, $c) => rtrim("{$c['label']} must be between {$p[0]} and " . ($p[1] ?? "?") . " " . self::sizeUnit($c)),
                requiresParams: true,
                example: "between:1,10"
            );

            self::register("size",
                fn($v, $p, $c) => self::sizeOf($v, $c['sizeMode']) == (float)($p[0] ?? 0),
                fn($p, $c) => rtrim("{$c['label']} must be exactly {$p[0]} " . self::sizeUnit($c)),
                requiresParams: true,
                example: "size:4"
            );

            self::register("digits",
                fn($v, $p, $c) => (preg_match('/^\d+$/', (string)$v) === 1) && (strlen((string)$v) == (int)($p[0] ?? -1)),
                fn($p, $c) => "{$c['label']} must be exactly {$p[0]} digits",
                requiresParams: true,
                example: "digits:4"
            );

            self::register("digits_between",
                function($v, $p, $c)
                {
                    if(preg_match('/^\d+$/', (string)$v) !== 1) return false;
                    $len = strlen((string)$v);
                    return ($len >= (int)($p[0] ?? 0)) && ($len <= (int)($p[1] ?? 0));
                },
                fn($p, $c) => "{$c['label']} must be between {$p[0]} and " . ($p[1] ?? "?") . " digits",
                requiresParams: true,
                example: "digits_between:4,6"
            );

            //#endregion

            //#region comparison / list rules

            self::register("in",
                fn($v, $p, $c) => in_array((string)$v, array_map("strval", $p), true),
                fn($p, $c) => "{$c['label']} must be one of: " . implode(", ", $p),
                requiresParams: true,
                example: "in:admin,editor,viewer"
            );

            self::register("not_in",
                fn($v, $p, $c) => !in_array((string)$v, array_map("strval", $p), true),
                fn($p, $c) => "{$c['label']} must not be one of: " . implode(", ", $p),
                requiresParams: true,
                example: "not_in:banned,deleted"
            );

            self::register("regex",
                fn($v, $p, $c) => @preg_match($p[0] ?? "//", (string)$v) === 1,
                fn($p, $c) => "{$c['label']} is not in the correct format",
                requiresParams: true,
                example: "regex:/^[A-Z]{3}-\\d+\$/"
            );

            self::register("same",
                fn($v, $p, $c) => (string)$v === (string)($c['data'][$p[0]] ?? null),
                fn($p, $c) => "{$c['label']} must match " . self::humanize($p[0] ?? ""),
                requiresParams: true,
                example: "same:password"
            );

            self::register("different",
                fn($v, $p, $c) => (string)$v !== (string)($c['data'][$p[0]] ?? null),
                fn($p, $c) => "{$c['label']} must be different from " . self::humanize($p[0] ?? ""),
                requiresParams: true,
                example: "different:username"
            );

            self::register("confirmed",
                fn($v, $p, $c) => (string)$v === (string)($c['data'][$c['field'] . "_confirmation"] ?? null),
                fn($p, $c) => "{$c['label']} confirmation does not match"
            );

            //#endregion
        }

        /**
         * @param string $existingRule
         * @return array{0: callable, 1: callable, 2: bool, 3: string} argument list to spread into register() for a plain alias
         */
        private static function alias(string $existingRule): array
        {
            $rule = self::$rules[$existingRule];
            return [$rule['test'], $rule['message'], $rule['requires'], $rule['example']];
        }

        /**
         * @param array $context
         * @return string "characters", "items", or "" depending on sizeMode
         */
        private static function sizeUnit(array $context): string
        {
            return match($context['sizeMode'])
            {
                "array" => "items",
                "numeric" => "",
                default => "characters",
            };
        }

        /**
         * @param string $field
         * @return string a friendlier label derived from a field/property name
         */
        public static function humanize(string $field): string
        {
            $spaced = str_replace(["_", "."], " ", $field);
            return trim($spaced);
        }
    }
