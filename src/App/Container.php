<?php

    namespace Wixnit\App;

    use Closure;
    use Wixnit\Exception\ServiceNotFoundException;

    class Container
    {
        private static array $instances = [];
        private static array $factories = [];

        /**
         * Register an already-built service instance under a key.
         * @param string $key
         * @param mixed $service
         * @return void
         */
        public static function set(string $key, mixed $service): void
        {
            self::$instances[$key] = $service;
            unset(self::$factories[$key]);
        }

        /**
         * Register a lazy factory for a service - $factory is only invoked the first
         * time get() is actually called for $key, not at bind() time. Lets you register
         * a service without having to fully construct it (and its dependencies) up front.
         * @param string $key
         * @param Closure $factory
         * @param bool $singleton when true (default), the factory's result is cached after
         *   the first resolution and reused; when false, a fresh instance is built on every get()
         * @return void
         */
        public static function bind(string $key, Closure $factory, bool $singleton = true): void
        {
            self::$factories[$key] = ["factory" => $factory, "singleton" => $singleton];
            unset(self::$instances[$key]);
        }

        /**
         * Resolve a service by key - either a previously set() instance, or the result
         * of a bind() factory (invoking it if it hasn't been resolved yet).
         * @param string $key
         * @param string|null $expectedType optional class/interface name (or a gettype()
         *   string like "array"/"string" for scalars) the resolved service must match -
         *   catches registration typos at the call site instead of failing downstream
         * @return mixed
         * @throws ServiceNotFoundException if $key isn't registered, or the resolved
         *   service doesn't satisfy $expectedType
         */
        public static function get(string $key, ?string $expectedType = null): mixed
        {
            if(isset(self::$instances[$key]))
            {
                $service = self::$instances[$key];
            }
            else if(isset(self::$factories[$key]))
            {
                $binding = self::$factories[$key];
                $service = ($binding["factory"])();

                if($binding["singleton"])
                {
                    self::$instances[$key] = $service;
                }
            }
            else
            {
                throw ServiceNotFoundException::NotRegistered($key);
            }

            if($expectedType !== null)
            {
                $matches = is_object($service) ? ($service instanceof $expectedType) : (gettype($service) === $expectedType);

                if(!$matches)
                {
                    $actualType = is_object($service) ? get_class($service) : gettype($service);
                    throw ServiceNotFoundException::TypeMismatch($key, $expectedType, $actualType);
                }
            }

            return $service;
        }

        /**
         * Check whether a key has either an eager instance or a factory binding registered.
         * @param string $key
         * @return bool
         */
        public static function has(string $key): bool
        {
            return isset(self::$instances[$key]) || isset(self::$factories[$key]);
        }

        /**
         * Unregister a single service - clears both its instance (if any) and its
         * factory binding (if any).
         * @param string $key
         * @return void
         */
        public static function remove(string $key): void
        {
            unset(self::$instances[$key]);
            unset(self::$factories[$key]);
        }

        /**
         * Clear every registered service and factory. Mainly useful for test isolation
         * between test cases, since every entry here is static/process-wide - and
         * important to call between requests if this ever runs on a persistent-worker
         * SAPI (Swoole/RoadRunner/FrankenPHP), where static state otherwise leaks
         * across requests rather than resetting per-request like classic FastCGI.
         * @return void
         */
        public static function flush(): void
        {
            self::$instances = [];
            self::$factories = [];
        }
    }
