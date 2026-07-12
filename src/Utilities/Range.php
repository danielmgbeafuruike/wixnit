<?php

    namespace Wixnit\Utilities;

    class Range extends Span
    {
        private ?Span $constraint = null;

        function __construct($value1, $value2=null, $constraint=null)
        {
            $this->init($value1, $value2, $constraint);
        }

        /**
         * hydrate the range with values
         * @param mixed $value1
         * @param mixed $value2
         * @param mixed $constraint
         * @return void
         */
        private function init($value1, $value2=null, $constraint=null): void
        {
            if($value1 instanceof Span)
            {
                $this->start = $value1->start <= $value1->stop ? $value1->start : $value1->stop;
                $this->stop = $value1->start <= $value1->stop ? $value1->stop : $value1->start;
            }
            else if(is_array($value1))
            {
                // accept either a positional [min, max] pair or an associative
                // ['start' => x, 'stop' => y] / ['min' => x, 'max' => y] array
                $a = $value1[0] ?? $value1['start'] ?? $value1['min'] ?? null;
                $b = $value1[1] ?? $value1['stop'] ?? $value1['max'] ?? null;

                if($a !== null && $b !== null)
                {
                    $this->start = $a <= $b ? $a : $b;
                    $this->stop = $a <= $b ? $b : $a;
                }
            }
            else if(isset($value2))
            {
                $this->start = $value1 <= $value2 ? $value1 : $value2;
                $this->stop = $value1 <= $value2 ? $value2 : $value1;
            }

            if($constraint instanceof Span)
            {
                $this->constraint = $constraint;
                $this->clampToConstraint();
            }
        }

        /**
         * clamp the range's start/stop to stay within the constraining span, if one was provided
         * @return void
         */
        private function clampToConstraint(): void
        {
            if($this->constraint === null)
            {
                return;
            }

            $min = min($this->constraint->start, $this->constraint->stop);
            $max = max($this->constraint->start, $this->constraint->stop);

            $this->start = max($min, min($this->start, $max));
            $this->stop = max($min, min($this->stop, $max));
        }

        /**
         * convert the range to a span
         * @return Span
         */
        public function toSpan(): Span
        {
            return new Span($this->start, $this->stop);
        }

        /**
         * convert the range to a timespan
         * @return Timespan
         */
        public function toTimespan(bool $spaLastDay=true): Timespan
        {
            return new Timespan($this->start, $this->stop, $spaLastDay);
        }

        /**
         * check if a value is within the range (inclusive)
         * @param mixed $value
         * @return bool
         */
        public function inRange($value): bool
        {
            return (($value >= $this->start) && ($value <= $this->stop));
        }

        /**
         * clamp a value so it falls within the range
         * @param mixed $value
         * @return mixed
         */
        public function clamp($value)
        {
            if($value < $this->start)
            {
                return $this->start;
            }
            if($value > $this->stop)
            {
                return $this->stop;
            }
            return $value;
        }

        /**
         * get the constraining span, if any, that this range is clamped to
         * @return Span|null
         */
        public function getConstraint(): ?Span
        {
            return $this->constraint;
        }
    }
