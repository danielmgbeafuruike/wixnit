<?php

    namespace Wixnit\Console;

    use Wixnit\Exception\ConsoleException;
    use Wixnit\Utilities\Env;

    /**
     * One object for both directions of a command's I/O - output and input - since a
     * prompt is inherently a read-then-write pair. Injected into every Command as
     * $this->io by Kernel.
     *
     *   $this->io->info("Running migrations...");
     *   $this->io->table(['Name', 'Email'], $rows);
     *   $name = $this->io->ask("What's your name?", default: "Anonymous");
     *   $confirmed = $this->io->confirm("Proceed?", default: true);
     *
     * Colour output only happens when stdout is actually a TTY, and never when the
     * NO_COLOR environment variable is set (https://no-color.org) - piping a command's
     * output to a file or another program never gets raw ANSI escape codes mixed into
     * it. Every write also accumulates into an in-memory buffer regardless of where
     * it's really going, retrievable via output() - this is what makes
     * Kernel::call()/ConsoleIO::ForTesting() able to assert on a command's output
     * without touching a real terminal (see §9 of the console design).
     */
    class ConsoleIO
    {
        private const COLOR_RESET = "\033[0m";
        private const COLOR_BOLD = "\033[1m";
        private const COLOR_RED = "\033[31m";
        private const COLOR_GREEN = "\033[32m";
        private const COLOR_YELLOW = "\033[33m";
        private const COLOR_CYAN = "\033[36m";
        private const COLOR_GRAY = "\033[90m";

        /** @var resource */
        private $outStream;
        /** @var resource */
        private $errStream;
        /** @var resource */
        private $inStream;

        private Verbosity $verbosity;
        private bool $noInteraction;
        private bool $decorated;

        private string $buffer = "";

        /**
         * @param resource|null $outStream defaults to STDOUT
         * @param resource|null $inStream defaults to STDIN
         * @param resource|null $errStream defaults to STDERR
         * @param Verbosity $verbosity
         * @param bool $noInteraction
         * @param bool|null $decorated null auto-detects (TTY + no NO_COLOR); pass explicitly to force on/off
         */
        function __construct(
            $outStream = null,
            $inStream = null,
            $errStream = null,
            Verbosity $verbosity = Verbosity::NORMAL,
            bool $noInteraction = false,
            ?bool $decorated = null,
        ) {
            $this->outStream = $outStream ?? (defined("STDOUT") ? STDOUT : fopen("php://output", "w"));
            $this->inStream = $inStream ?? (defined("STDIN") ? STDIN : fopen("php://stdin", "r"));
            $this->errStream = $errStream ?? (defined("STDERR") ? STDERR : fopen("php://stderr", "w"));
            $this->verbosity = $verbosity;
            $this->noInteraction = $noInteraction;
            $this->decorated = $decorated ?? self::detectDecoration($this->outStream);
        }

        /**
         * A ConsoleIO backed entirely by in-memory streams - no real terminal is
         * touched. Used by Kernel::call() so commands can be exercised from a test
         * without shelling out; output() retrieves everything the command wrote.
         * @param bool $noInteraction defaults to true - a test runner is never a human
         *   waiting at a prompt
         * @return self
         */
        public static function ForTesting(bool $noInteraction = true): self
        {
            return new self(fopen("php://memory", "r+"), fopen("php://memory", "r+"), fopen("php://memory", "r+"), Verbosity::NORMAL, $noInteraction, false);
        }

        public function verbosity(): Verbosity
        {
            return $this->verbosity;
        }

        public function isQuiet(): bool
        {
            return $this->verbosity === Verbosity::QUIET;
        }

        public function isNoInteraction(): bool
        {
            return $this->noInteraction;
        }

        public function isDecorated(): bool
        {
            return $this->decorated;
        }

        /**
         * Everything written through this instance so far (stdout and stderr combined,
         * in the order it was written, with any colour codes stripped out) - mainly for
         * asserting against in tests, via Kernel::call().
         * @return string
         */
        public function output(): string
        {
            return $this->buffer;
        }

        #region output

        /**
         * write raw text with no trailing newline, no styling
         * @param string $text
         * @param Verbosity $level minimum verbosity this should be shown at
         * @return void
         */
        public function write(string $text, Verbosity $level = Verbosity::NORMAL): void
        {
            if(!$this->verbosity->shows($level))
            {
                return;
            }
            $this->emit($text, $this->outStream);
        }

        /**
         * plain, unstyled text, followed by a newline
         * @param string $message
         * @return void
         */
        public function line(string $message): void
        {
            $this->write($message."\n");
        }

        /**
         * one or more blank lines
         * @param int $count
         * @return void
         */
        public function newLine(int $count = 1): void
        {
            $this->write(str_repeat("\n", max(1, $count)));
        }

        /**
         * an informational message, styled cyan
         * @param string $message
         * @return void
         */
        public function info(string $message): void
        {
            $this->write($this->style($message, self::COLOR_CYAN)."\n");
        }

        /**
         * a success message, styled green with a checkmark
         * @param string $message
         * @return void
         */
        public function success(string $message): void
        {
            $this->write($this->style("✓ ".$message, self::COLOR_GREEN)."\n");
        }

        /**
         * a warning, styled yellow
         * @param string $message
         * @return void
         */
        public function warning(string $message): void
        {
            $this->write($this->style("! ".$message, self::COLOR_YELLOW)."\n");
        }

        /**
         * an error - unlike every other output method, this is never suppressed by
         * --quiet, and is written to stderr rather than stdout, so it stays visible even
         * when the rest of a command's output has been piped or redirected away.
         * @param string $message
         * @return void
         */
        public function error(string $message): void
        {
            $this->emit($this->style("✗ ".$message, self::COLOR_RED)."\n", $this->errStream);
        }

        /**
         * only shown with -v or higher
         * @param string $message
         * @return void
         */
        public function verbose(string $message): void
        {
            $this->write($this->style($message, self::COLOR_GRAY)."\n", Verbosity::VERBOSE);
        }

        /**
         * only shown with -vvv
         * @param string $message
         * @return void
         */
        public function debug(string $message): void
        {
            $this->write($this->style($message, self::COLOR_GRAY)."\n", Verbosity::DEBUG);
        }

        /**
         * an aligned table, columns sized to their widest cell
         * @param string[] $headers
         * @param array<int, array<int|string, mixed>> $rows each row a list (or assoc
         *   array - only the values are used) of cells, in the same order as $headers
         * @return void
         */
        public function table(array $headers, array $rows): void
        {
            if(!$this->verbosity->shows(Verbosity::NORMAL))
            {
                return;
            }

            $widths = [];
            foreach($headers as $index => $header)
            {
                $widths[$index] = mb_strlen((string) $header);
            }
            foreach($rows as $row)
            {
                foreach(array_values($row) as $index => $cell)
                {
                    $widths[$index] = max($widths[$index] ?? 0, mb_strlen((string) $cell));
                }
            }

            $this->writeTableSeparator($widths);
            $this->writeTableRow($headers, $widths, true);
            $this->writeTableSeparator($widths);

            foreach($rows as $row)
            {
                $this->writeTableRow(array_values($row), $widths, false);
            }
            $this->writeTableSeparator($widths);
        }

        /**
         * a progress bar, advanced with ->advance()/->each()
         * @param int $total
         * @return ProgressBar
         */
        public function progressBar(int $total): ProgressBar
        {
            return new ProgressBar($this, $total);
        }

        #endregion

        #region input

        /**
         * ask a free-text question
         * @param string $question
         * @param string|null $default returned as-is, with no interaction attempted, if
         *   the answer is left blank - or immediately, if --no-interaction is set
         * @return string
         * @throws ConsoleException if --no-interaction is set and $default is null
         */
        public function ask(string $question, ?string $default = null): string
        {
            if($this->noInteraction)
            {
                if($default !== null)
                {
                    return $default;
                }
                throw ConsoleException::PromptRequiresInteraction($question);
            }

            $hint = ($default !== null) ? " [{$default}]" : "";
            $this->write($this->style("? ", self::COLOR_CYAN)."{$question}{$hint}: ");
            $answer = trim($this->readLine());

            return ($answer === "") ? ($default ?? "") : $answer;
        }

        /**
         * ask a question whose answer isn't echoed back to the terminal (a password,
         * token, etc) - falls back to a normal, visible ask() on platforms where
         * terminal echo can't be toggled (Windows, or stdin isn't a real TTY)
         * @param string $question
         * @param string|null $default
         * @return string
         * @throws ConsoleException if --no-interaction is set and $default is null
         */
        public function secret(string $question, ?string $default = null): string
        {
            if($this->noInteraction)
            {
                if($default !== null)
                {
                    return $default;
                }
                throw ConsoleException::PromptRequiresInteraction($question);
            }

            $hint = ($default !== null) ? " [{$default}]" : "";
            $this->write($this->style("? ", self::COLOR_CYAN)."{$question}{$hint}: ");

            $canToggleEcho = $this->decorated && (stripos(PHP_OS, "WIN") !== 0) && (function_exists("shell_exec"));
            $originalMode = null;

            if($canToggleEcho)
            {
                $originalMode = @shell_exec("stty -g");
                @shell_exec("stty -echo");
            }

            $answer = trim($this->readLine());

            if($canToggleEcho && ($originalMode !== null))
            {
                @shell_exec("stty ".trim($originalMode));
            }
            $this->write("\n");

            return ($answer === "") ? ($default ?? "") : $answer;
        }

        /**
         * ask a yes/no question
         * @param string $question
         * @param bool|null $default returned (with no interaction attempted) if the
         *   answer is left blank; null means there is no default
         * @return bool
         * @throws ConsoleException if --no-interaction is set and $default is null
         */
        public function confirm(string $question, ?bool $default = null): bool
        {
            if($this->noInteraction)
            {
                if($default !== null)
                {
                    return $default;
                }
                throw ConsoleException::PromptRequiresInteraction($question);
            }

            $hint = ($default === null) ? "y/n" : ($default ? "Y/n" : "y/N");
            $this->write($this->style("? ", self::COLOR_CYAN)."{$question} ({$hint}): ");
            $answer = strtolower(trim($this->readLine()));

            if($answer === "")
            {
                return $default ?? false;
            }
            return in_array($answer, ["y", "yes"], true);
        }

        /**
         * ask the person to pick one of several choices, by number or by typing the
         * value itself; reprompts (interactively) on an unrecognized answer rather than
         * silently falling back to the default
         * @param string $question
         * @param array<int|string, string> $choices
         * @param mixed $default returned (with no interaction attempted) if the answer
         *   is left blank; null means there is no default
         * @return mixed the chosen value from $choices
         * @throws ConsoleException if --no-interaction is set and $default is null
         */
        public function choice(string $question, array $choices, mixed $default = null): mixed
        {
            if($this->noInteraction)
            {
                if($default !== null)
                {
                    return $default;
                }
                throw ConsoleException::PromptRequiresInteraction($question);
            }

            $values = array_values($choices);

            $this->write($this->style("? ", self::COLOR_CYAN)."{$question}\n");
            foreach($values as $index => $choice)
            {
                $marker = (($default !== null) && ($choice === $default)) ? "*" : " ";
                $this->write("  [{$index}]{$marker} {$choice}\n");
            }

            $hint = ($default !== null) ? " [{$default}]" : "";
            $this->write("> {$hint}: ");
            $answer = trim($this->readLine());

            if(($answer === "") && ($default !== null))
            {
                return $default;
            }
            if(is_numeric($answer) && array_key_exists((int) $answer, $values))
            {
                return $values[(int) $answer];
            }
            if(in_array($answer, $values, true))
            {
                return $answer;
            }

            $this->warning("Please choose a valid option.");
            return $this->choice($question, $choices, $default);
        }

        #endregion

        private function readLine(): string
        {
            if(!is_resource($this->inStream))
            {
                return "";
            }
            $line = fgets($this->inStream);
            return ($line === false) ? "" : $line;
        }

        private function writeTableRow(array $cells, array $widths, bool $isHeader): void
        {
            $parts = [];
            foreach(array_values($cells) as $index => $cell)
            {
                $text = str_pad((string) $cell, $widths[$index] ?? mb_strlen((string) $cell));
                $parts[] = $isHeader ? $this->style($text, self::COLOR_BOLD) : $text;
            }
            $this->write("| ".implode(" | ", $parts)." |\n");
        }

        private function writeTableSeparator(array $widths): void
        {
            $parts = [];
            foreach($widths as $width)
            {
                $parts[] = str_repeat("-", $width + 2);
            }
            $this->write("+".implode("+", $parts)."+\n");
        }

        private function style(string $text, string $color): string
        {
            return $this->decorated ? ($color.$text.self::COLOR_RESET) : $text;
        }

        /**
         * @param string $text
         * @param resource $stream
         * @return void
         */
        private function emit(string $text, $stream): void
        {
            $this->buffer .= preg_replace('/\033\[[0-9;]*m/', '', $text);
            if(is_resource($stream))
            {
                @fwrite($stream, $text);
            }
        }

        /**
         * @param resource $stream
         * @return bool
         */
        private static function detectDecoration($stream): bool
        {
            if(Env::Has("NO_COLOR"))
            {
                return false;
            }
            if(!is_resource($stream))
            {
                return false;
            }
            if(function_exists("stream_isatty"))
            {
                return @stream_isatty($stream);
            }
            if(function_exists("posix_isatty"))
            {
                return @posix_isatty($stream);
            }
            return false;
        }
    }
