<?php

    namespace Wixnit\Utilities;

    /**
     * A simple static stopwatch for timing code, e.g.:
     *
     *   Stopwatch::Start("import");
     *   // ... do work ...
     *   $lap = Stopwatch::Lap("import");
     *   // ... do more work ...
     *   $total = Stopwatch::Stop("import");
     *
     * Multiple independent stopwatches can run at once by giving each a different name.
     */
    class Stopwatch
    {
        /**
         * @var array<string, array{start: float, last: float, stop: float|null}>
         */
        private static array $watches = [];

        /**
         * start (or restart) a named stopwatch
         * @param string $name
         * @return void
         */
        public static function Start(string $name = "default"): void
        {
            $now = microtime(true);
            Stopwatch::$watches[$name] = ["start" => $now, "last" => $now, "stop" => null];
        }

        /**
         * record a lap on a named stopwatch and get the number of seconds since the last
         * lap (or since Start(), if this is the first lap)
         * @param string $name
         * @return float seconds elapsed since the last lap
         */
        public static function Lap(string $name = "default"): float
        {
            if(!isset(Stopwatch::$watches[$name]))
            {
                Stopwatch::Start($name);
            }

            $now = microtime(true);
            $elapsed = $now - Stopwatch::$watches[$name]["last"];
            Stopwatch::$watches[$name]["last"] = $now;

            return $elapsed;
        }

        /**
         * stop a named stopwatch and get the total number of seconds since it was started
         * @param string $name
         * @return float total seconds elapsed since Start()
         */
        public static function Stop(string $name = "default"): float
        {
            if(!isset(Stopwatch::$watches[$name]))
            {
                return 0.0;
            }

            $now = microtime(true);
            Stopwatch::$watches[$name]["stop"] = $now;

            return $now - Stopwatch::$watches[$name]["start"];
        }

        /**
         * get the number of seconds elapsed on a named stopwatch, without stopping it.
         * If the stopwatch has already been stopped, this returns its final total instead of still counting up.
         * @param string $name
         * @return float
         */
        public static function Elapsed(string $name = "default"): float
        {
            if(!isset(Stopwatch::$watches[$name]))
            {
                return 0.0;
            }

            $watch = Stopwatch::$watches[$name];
            $end = $watch["stop"] ?? microtime(true);

            return $end - $watch["start"];
        }

        /**
         * remove a named stopwatch's recorded state
         * @param string $name
         * @return void
         */
        public static function Reset(string $name = "default"): void
        {
            unset(Stopwatch::$watches[$name]);
        }
    }
