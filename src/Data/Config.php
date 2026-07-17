<?php

    namespace Wixnit\Data;

    use stdClass;
    use Wixnit\Exception\ConfigException;

    /**
     * Application configuration: a single dot-notation store for arbitrary settings,
     * loaded from PHP files, JSON files, whole directories, environment variables, or
     * set directly at runtime.
     *
     *   Config::Set('mail.host', 'smtp.example.com');
     *   Config::Get('mail.host');                    // "smtp.example.com"
     *   Config::Get('mail.port', 587);                // 587 (default, key not set)
     *
     *   // config/app.php returning ['name' => 'My App', 'debug' => false]
     *   // config/mail.php returning ['host' => '...', 'port' => 587]
     *   Config::LoadDirectory(__DIR__ . '/config');
     *   Config::Get('app.name');                       // "My App"
     *   Config::Get('mail.port');                       // 587
     *
     *   Config::LoadEnv(__DIR__ . '/.env');
     *   Config::Env('APP_DEBUG', false);                // reads getenv('APP_DEBUG')/$_ENV
     *
     * Legacy note: Load() and DBCredentials() are the two original methods this class
     * shipped with, and both keep their exact original signatures/behavior - including
     * DBCredentials() writing to $GLOBALS["WIXNIT_MYSQL_Connection_Credentials"], which
     * Wixnit\Data\DBConfig::getConnection() reads directly as its final fallback when
     * resolving a database connection. That global write is NOT decorative and must not
     * be removed even though the same data is now also mirrored into the unified store
     * (as database.connections.default) for introspection alongside everything else.
     */
    class Config
    {
        private static array $store = [];

        #region core store

        /**
         * Set a value using dot notation ("mail.host") - intermediate segments are
         * created as arrays automatically.
         * @param string $key
         * @param mixed $value
         * @return void
         */
        public static function Set(string $key, mixed $value): void
        {
            $segments = explode('.', $key);
            $node = &self::$store;

            foreach ($segments as $i => $segment) {
                if ($i === (count($segments) - 1)) {
                    $node[$segment] = $value;
                    break;
                }

                if (!isset($node[$segment]) || !is_array($node[$segment])) {
                    $node[$segment] = [];
                }
                $node = &$node[$segment];
            }
        }

        /**
         * Read a value using dot notation. An empty key ("") returns the entire store.
         * @param string $key
         * @param mixed $default returned if the key (or any segment of its path) isn't set
         * @return mixed
         */
        public static function Get(string $key, mixed $default = null): mixed
        {
            if ($key === '') {
                return self::$store;
            }

            $node = self::$store;

            foreach (explode('.', $key) as $segment) {
                if (!is_array($node) || !array_key_exists($segment, $node)) {
                    return $default;
                }
                $node = $node[$segment];
            }
            return $node;
        }

        /**
         * Whether a key is set - correctly distinguishes "set to null" from "not set at
         * all" (unlike isset(), which treats an explicit null the same as missing).
         * @param string $key
         * @return bool
         */
        public static function Has(string $key): bool
        {
            $sentinel = new stdClass();
            return self::Get($key, $sentinel) !== $sentinel;
        }

        /**
         * Like Get(), but throws if the key isn't set - for config a misconfigured
         * deployment should fail loudly on, rather than silently limping along on a
         * null/default value deep inside application code.
         * @param string $key
         * @return mixed
         * @throws ConfigException
         */
        public static function Required(string $key): mixed
        {
            $sentinel = new stdClass();
            $value = self::Get($key, $sentinel);

            if ($value === $sentinel) {
                throw ConfigException::MissingRequiredKey($key);
            }
            return $value;
        }

        /**
         * Remove a single key. No-op if the key (or its parent path) doesn't exist.
         * @param string $key
         * @return void
         */
        public static function Forget(string $key): void
        {
            $segments = explode('.', $key);
            $lastSegment = array_pop($segments);
            $node = &self::$store;

            foreach ($segments as $segment) {
                if (!isset($node[$segment]) || !is_array($node[$segment])) {
                    return;
                }
                $node = &$node[$segment];
            }
            unset($node[$lastSegment]);
        }

        /**
         * Deep-merge an array of values into the store. With $namespace, every key of
         * $values is merged under that namespace (config/mail.php's contents merged
         * under "mail") rather than the store's root. Merges recursively at every level,
         * so a partial override (e.g. one environment-specific key) doesn't wipe out
         * sibling keys already set at that same path.
         * @param array $values
         * @param string|null $namespace
         * @return void
         */
        public static function Merge(array $values, ?string $namespace = null): void
        {
            if ($namespace === null) {
                self::$store = array_replace_recursive(self::$store, $values);
                return;
            }

            $existing = self::Get($namespace, []);
            $existing = is_array($existing) ? $existing : [];

            self::Set($namespace, array_replace_recursive($existing, $values));
        }

        /**
         * The entire store, as a plain nested array.
         * @return array
         */
        public static function All(): array
        {
            return self::$store;
        }

        /**
         * Clear every configured value. Mainly useful between tests - or, on a
         * persistent-worker SAPI (Swoole/RoadRunner/FrankenPHP), between requests, since
         * this store is static/process-wide and otherwise carries over between them the
         * same way Wixnit\App\Container's registry does.
         * @return void
         */
        public static function Flush(): void
        {
            self::$store = [];
        }

        #endregion



        #region typed getters

        public static function GetString(string $key, string $default = ""): string
        {
            $value = self::Get($key, $default);
            return is_array($value) ? $default : (string) $value;
        }

        public static function GetInt(string $key, int $default = 0): int
        {
            $value = self::Get($key, $default);
            return is_numeric($value) ? (int) $value : $default;
        }

        public static function GetFloat(string $key, float $default = 0.0): float
        {
            $value = self::Get($key, $default);
            return is_numeric($value) ? (float) $value : $default;
        }

        /**
         * Accepts real booleans as-is, and coerces common string/numeric forms
         * ("true"/"false", "1"/"0", "yes"/"no", "on"/"off") - useful since values loaded
         * from .env files or JSON are frequently strings even when conceptually boolean.
         * @param string $key
         * @param bool $default
         * @return bool
         */
        public static function GetBool(string $key, bool $default = false): bool
        {
            $value = self::Get($key, $default);

            if (is_bool($value)) {
                return $value;
            }
            if (is_string($value)) {
                return match (strtolower(trim($value))) {
                    'true', '1', 'yes', 'on' => true,
                    'false', '0', 'no', 'off', '' => false,
                    default => $default,
                };
            }
            if (is_numeric($value)) {
                return ((float) $value) != 0.0;
            }
            return $default;
        }

        public static function GetArray(string $key, array $default = []): array
        {
            $value = self::Get($key, $default);
            return is_array($value) ? $value : $default;
        }

        #endregion



        #region loading from files

        /**
         * Load the wixnit config file for use in your project.
         *
         * Preserved exactly as originally written: uses require_once (not require) since
         * a legacy config file may define a global dbConfig() function, and PHP fatally
         * errors on redeclaring a function - so loading the same path twice must not
         * re-execute its top-level code. One consequence of that: on a second Load() call
         * for the same $path, $namespace merging below is skipped (require_once returns
         * true, not the file's array, on repeat includes) - which is fine, since there'd
         * be nothing new to merge anyway.
         *
         * Extended, backward-compatibly, to also support a modern config file that
         * `return`s a plain array instead of (or alongside) defining dbConfig():
         *
         *   // legacy style - still fully supported
         *   function dbConfig() { Config::DBCredentials(...); }
         *
         *   // modern style - the returned array is merged into the store
         *   return ['name' => 'My App', 'debug' => false];
         *
         * @param string $path
         * @param string|null $namespace if the file returns an array, merge it under this
         *   namespace instead of the store's root
         * @return void
         * @throws ConfigException if $path doesn't exist
         */
        public static function Load(string $path, ?string $namespace = null): void
        {
            if (!file_exists($path)) {
                throw ConfigException::FileNotFound($path);
            }

            $result = require_once($path);

            if (function_exists("dbConfig")) {
                dbConfig();
            }

            if (is_array($result)) {
                self::Merge($result, $namespace);
            }
        }

        /**
         * Load every "*.php" file in a directory, each `return`ing an array, namespaced by
         * its own filename - config/app.php becomes the "app" namespace, config/mail.php
         * becomes "mail", and so on. The standard way to organize config in a real project
         * rather than calling Load() file by file.
         *
         * @param string $directory
         * @param string|null $environment if given and a same-named subdirectory exists
         *   (e.g. config/local/mail.php), those files are loaded afterward and deep-merged
         *   on top of the base ones under the same namespace - for environment-specific
         *   overrides that only replace the keys they actually set
         * @return void
         * @throws ConfigException if $directory doesn't exist
         */
        public static function LoadDirectory(string $directory, ?string $environment = null): void
        {
            $directory = rtrim($directory, '/');

            if (!is_dir($directory)) {
                throw ConfigException::DirectoryNotFound($directory);
            }

            self::loadPhpFilesInto($directory);

            if ($environment !== null) {
                $envDirectory = $directory . '/' . $environment;

                if (is_dir($envDirectory)) {
                    self::loadPhpFilesInto($envDirectory);
                }
            }
        }

        /**
         * Load a JSON file - its top-level keys are merged the same way an array
         * `return`ed from a PHP config file would be.
         * @param string $path
         * @param string|null $namespace
         * @return void
         * @throws ConfigException if $path doesn't exist or isn't valid JSON
         */
        public static function LoadJson(string $path, ?string $namespace = null): void
        {
            if (!file_exists($path)) {
                throw ConfigException::FileNotFound($path);
            }

            $decoded = json_decode((string) file_get_contents($path), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ConfigException::InvalidJson($path, json_last_error_msg());
            }

            self::Merge($decoded ?? [], $namespace);
        }

        /**
         * Write the entire current store out to a single plain PHP file (`return [...];`),
         * for LoadCached() to read back later without re-scanning/re-parsing every
         * individual config file - the same idea as Laravel's `config:cache`. Meant to be
         * run once as a deploy/build step, not on every request.
         * @param string $path
         * @return void
         * @throws ConfigException if $path isn't writable
         */
        public static function Cache(string $path): void
        {
            $contents = "<?php\n\nreturn " . var_export(self::$store, true) . ";\n";

            if (@file_put_contents($path, $contents, LOCK_EX) === false) {
                throw ConfigException::CacheWriteFailed($path);
            }
        }

        /**
         * Load a file previously written by Cache() - REPLACES the entire store (this is
         * meant to be the complete, final config from a prior full load, not one more
         * source to merge in). Typical production pattern: run LoadDirectory() + Cache()
         * once at deploy time, then call only LoadCached() at runtime, skipping directory
         * scanning entirely.
         * @param string $path
         * @return bool true if $path existed and was loaded, false if it didn't exist
         *   (in which case the store is left untouched - falling back to LoadDirectory()
         *   is a reasonable response to false)
         */
        public static function LoadCached(string $path): bool
        {
            if (!file_exists($path)) {
                return false;
            }

            $cached = require $path;

            if (is_array($cached)) {
                self::$store = $cached;
                return true;
            }
            return false;
        }

        private static function loadPhpFilesInto(string $directory): void
        {
            foreach (glob($directory . '/*.php') ?: [] as $file) {
                $namespace = basename($file, '.php');
                $result = require $file;

                if (is_array($result)) {
                    self::Merge($result, $namespace);
                }
            }
        }

        #endregion



        #region environment variables

        /**
         * Parse a ".env" file (KEY=value per line, "#" comments, optional single/double
         * quoting) into real process environment variables (putenv() + $_ENV), readable
         * afterward via Env(). By default, an already-set environment variable (e.g. one
         * genuinely provided by the server/container/CI, rather than this file) is left
         * alone - real deployment environment values take precedence over a checked-in
         * .env file unless $overwriteExisting is explicitly requested.
         * @param string $path
         * @param bool $overwriteExisting
         * @return void
         * @throws ConfigException if $path doesn't exist
         */
        public static function LoadEnv(string $path, bool $overwriteExisting = false): void
        {
            if (!file_exists($path)) {
                throw ConfigException::FileNotFound($path);
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            foreach ($lines as $line) {
                $line = trim($line);

                if (($line === '') || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$key, $rawValue] = explode('=', $line, 2);
                $key = trim($key);
                $value = self::parseEnvValue(trim($rawValue));

                if (($key === '') || (!$overwriteExisting && (getenv($key) !== false))) {
                    continue;
                }

                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }

        /**
         * Read an environment variable (checking $_ENV, then getenv()), with the common
         * "true"/"false"/"null"/"empty" string forms coerced to their real PHP equivalent -
         * environment variables are otherwise always plain strings, even conceptually
         * boolean/null ones.
         * @param string $key
         * @param mixed $default
         * @return mixed
         */
        public static function Env(string $key, mixed $default = null): mixed
        {
            $value = $_ENV[$key] ?? getenv($key);

            if (($value === false) || ($value === null)) {
                return $default;
            }

            return match (strtolower($value)) {
                'true' => true,
                'false' => false,
                'null' => null,
                'empty' => '',
                default => $value,
            };
        }

        /**
         * The current environment name - reads the "APP_ENV" environment variable,
         * defaulting to "production" (deliberately the safe/conservative default: if
         * environment detection is ever misconfigured, failing toward production-strict
         * behavior is safer than accidentally failing toward a permissive local/debug mode).
         * @return string
         */
        public static function Environment(): string
        {
            return (string) self::Env('APP_ENV', 'production');
        }

        public static function Is(string $environment): bool
        {
            return strcasecmp(self::Environment(), $environment) === 0;
        }

        public static function IsProduction(): bool
        {
            return self::Is('production');
        }

        public static function IsLocal(): bool
        {
            return self::Is('local');
        }

        private static function parseEnvValue(string $value): string
        {
            $isQuoted = (strlen($value) >= 2)
                && (($value[0] === '"' && str_ends_with($value, '"'))
                    || ($value[0] === "'" && str_ends_with($value, "'")));

            if ($isQuoted) {
                return substr($value, 1, -1);
            }

            // strip a trailing, unquoted inline comment: KEY=value # comment
            $hashPosition = strpos($value, ' #');
            if ($hashPosition !== false) {
                $value = rtrim(substr($value, 0, $hashPosition));
            }
            return $value;
        }

        #endregion



        #region database credentials (legacy)

        /**
         * set the DB credentials
         *
         * Preserved exactly as originally written, including the exact global key name:
         * Wixnit\Data\DBConfig::getConnection() reads $GLOBALS["WIXNIT_MYSQL_Connection_Credentials"]
         * directly as its final fallback when no other database connection has been
         * configured, so that write is load-bearing and must not be removed or renamed.
         *
         * Also now mirrored into the unified store under "database.connections.default",
         * purely additive, so it's introspectable the same way as any other config value
         * (Config::Get('database.connections.default.username'), etc.) without needing to
         * know about the legacy global at all.
         *
         * @param string $userName
         * @param string $password
         * @param string $dataBase
         * @param string $server
         * @return void
         */
        public static function DBCredentials(string $userName, string $password, string $dataBase, string $server = "localhost"): void
        {
            $credentials = ['server' => $server, 'username' => $userName, 'password' => $password, 'database' => $dataBase];

            $GLOBALS["WIXNIT_MYSQL_Connection_Credentials"] = $credentials;

            self::Set('database.connections.default', $credentials);
        }

        #endregion
    }
