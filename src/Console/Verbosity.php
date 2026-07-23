<?php

    namespace Wixnit\Console;

    /**
     * How chatty a command run should be - set once from the global -v/-vv/-vvv/-q
     * options (see GlobalOptions) and consulted by every ConsoleIO output method to
     * decide whether it actually writes anything.
     */
    enum Verbosity : int
    {
        /** -q / --quiet: only error() writes anything */
        case QUIET = 0;

        /** the default: line()/info()/success()/warning()/error()/table() all write */
        case NORMAL = 1;

        /** -v: NORMAL plus verbose() */
        case VERBOSE = 2;

        /** -vv: VERBOSE plus a formatted stack trace on an uncaught exception */
        case VERY_VERBOSE = 3;

        /** -vvv: everything, including debug() */
        case DEBUG = 4;

        /**
         * whether output written at $level should actually be shown at this verbosity
         * @param Verbosity $level
         * @return bool
         */
        public function shows(Verbosity $level): bool
        {
            return $this->value >= $level->value;
        }
    }
