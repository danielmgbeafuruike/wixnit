<?php

    namespace Wixnit\Console;

    /**
     * A simple, dependency-free progress bar, driven either manually (->advance()) or
     * by handing it the whole collection to process (->each()):
     *
     *   $bar = $this->io->progressBar(count($rows));
     *   foreach ($rows as $row) {
     *       process($row);
     *       $bar->advance();
     *   }
     *   $bar->finish();
     *
     *   // or, equivalently:
     *   $this->io->progressBar(count($rows))->each($rows, fn($row) => process($row));
     *
     * Only actually redraws when output is decorated (a real TTY, colour not disabled
     * by NO_COLOR) - piping a command's output to a file or CI log doesn't get a wall
     * of "\r" carriage-return spam, but every callback in ->each() still runs exactly
     * the same either way.
     */
    class ProgressBar
    {
        private const WIDTH = 30;

        private ConsoleIO $io;
        private int $total;
        private int $current = 0;
        private bool $finished = false;

        function __construct(ConsoleIO $io, int $total)
        {
            $this->io = $io;
            $this->total = max($total, 0);
            $this->render();
        }

        /**
         * @param int $step how many units of progress to advance
         * @return void
         */
        public function advance(int $step = 1): void
        {
            $this->current = min($this->total, $this->current + $step);
            $this->render();
        }

        /**
         * jump straight to a given position rather than advancing incrementally
         * @param int $current
         * @return void
         */
        public function setProgress(int $current): void
        {
            $this->current = max(0, min($this->total, $current));
            $this->render();
        }

        /**
         * mark the bar as complete (fills it the rest of the way) and move to a new line
         * @return void
         */
        public function finish(): void
        {
            $this->current = $this->total;
            $this->render();
            $this->finished = true;
            $this->io->write("\n");
        }

        /**
         * process every item in $items, calling $callback for each and advancing once
         * per item, then finish()ing automatically
         * @param iterable $items
         * @param callable $callback fn(mixed $item, int|string $key): mixed
         * @return void
         */
        public function each(iterable $items, callable $callback): void
        {
            foreach($items as $key => $item)
            {
                $callback($item, $key);
                $this->advance();
            }

            if(!$this->finished)
            {
                $this->finish();
            }
        }

        private function render(): void
        {
            if(!$this->io->isDecorated())
            {
                return;
            }

            $ratio = ($this->total > 0) ? ($this->current / $this->total) : 1.0;
            $filled = (int) round(self::WIDTH * $ratio);
            $bar = str_repeat("=", $filled).($filled < self::WIDTH ? ">" : "").str_repeat(" ", max(0, self::WIDTH - $filled - 1));
            $percent = str_pad((string) (int) round($ratio * 100), 3, " ", STR_PAD_LEFT);

            $this->io->write("\r [{$bar}] {$percent}% ({$this->current}/{$this->total})");
        }
    }
