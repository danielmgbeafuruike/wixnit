<?php

    namespace Wixnit\Console;

    /**
     * The handful of options available on every command without needing to be
     * declared as a #[Option] - handled by Kernel itself, pulled out of $argv before a
     * command's own signature is even considered:
     *
     *   -v, -vv, -vvv       verbosity level - verbose, very verbose, debug
     *   -q, --quiet         suppress all non-error output
     *   -n, --no-interaction  never prompt - fail instead if a prompt would be needed
     *   -h, --help          show the command's usage instead of running it
     *
     * Recognized wherever they appear in the token list, except after a literal `--`
     * terminator - tokens past that point belong entirely to the command being invoked,
     * even if one of them happens to look like a global option.
     */
    class GlobalOptions
    {
        function __construct(
            public readonly Verbosity $verbosity = Verbosity::NORMAL,
            public readonly bool $noInteraction = false,
            public readonly bool $help = false,
        ) {}

        /**
         * Pull every recognized global token out of $argv, wherever it appears (up to a
         * literal "--" terminator, which is left untouched for the command's own parser
         * to interpret).
         * @param string[] $argv
         * @return array{0: GlobalOptions, 1: string[]} the parsed globals, and every
         *   remaining token in its original order
         */
        public static function extract(array $argv): array
        {
            $verbosity = Verbosity::NORMAL;
            $quiet = false;
            $noInteraction = false;
            $help = false;

            $remaining = [];
            $pastTerminator = false;

            foreach($argv as $token)
            {
                if($pastTerminator)
                {
                    $remaining[] = $token;
                    continue;
                }

                switch($token)
                {
                    case "--":
                        $pastTerminator = true;
                        $remaining[] = $token;
                        break;

                    case "-v":
                        $verbosity = Verbosity::VERBOSE;
                        break;

                    case "-vv":
                        $verbosity = Verbosity::VERY_VERBOSE;
                        break;

                    case "-vvv":
                        $verbosity = Verbosity::DEBUG;
                        break;

                    case "-q":
                    case "--quiet":
                        $quiet = true;
                        break;

                    case "-n":
                    case "--no-interaction":
                        $noInteraction = true;
                        break;

                    case "-h":
                    case "--help":
                        $help = true;
                        break;

                    default:
                        $remaining[] = $token;
                        break;
                }
            }

            // --quiet wins over any -v/-vv/-vvv also present - "be silent" should never
            // be quietly overridden by a verbosity flag earlier or later in the same invocation
            $effectiveVerbosity = $quiet ? Verbosity::QUIET : $verbosity;

            return [new GlobalOptions($effectiveVerbosity, $noInteraction, $help), $remaining];
        }
    }
