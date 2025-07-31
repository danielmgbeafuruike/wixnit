<?php

    namespace Wixnit\Utilities;

    use stdClass;

    class Range extends Span
    {
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

            }
            else if(isset($value2))
            {
                $this->start = $value1 > $value2 ? $value1 : $value2;
                $this->stop = $value1 > $value2 ? $value2 : $value1;
            }

            if($constraint != null)
            {

            }
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
         * check if a value is within the range
         * @param mixed $value
         * @return bool
         */
        public function inRange($value): bool
        {
            return (($this->start >= $value) && ($this->stop <= $value));
        }
    }
    