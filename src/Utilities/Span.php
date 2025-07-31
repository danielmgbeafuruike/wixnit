<?php

    namespace Wixnit\Utilities;

    class Span
    {
        public float $start = 0;
        public float $stop = 0;

        function __construct($start=null, $stop=null)
        {
            if($start != null)
            {
                $this->start = $start;
            }
            if($stop != null)
            {
                $this->stop = $stop;
            }
        }

        /**
         * get the difference between start and stop
         * @return int
         */
        public function difference()
        {
            return ($this->stop > $this->start) ? $this->stop - $this->start : $this->start - $this->stop;
        }

        /**
         * split the span into multiple spans
         * @param int $places
         * @param bool $intersected
         * @return Span[]
         */
        public function splitSpan($places, $intersected=false)
        {
            $div = $this->difference() / $places;
            $ret = [];

            if($this->stop > $this->start)
            {
                $st = $this->start;

                for($i = 0; $i < $places; $i++)
                {
                    array_push($ret,  new Span($st, ($st + $div)));
                    $st += $div;
                }
            }
            else
            {
                $st = $this->start;

                for($i = 0; $i < $places; $i++)
                {
                    array_push($ret, new Span($st * ($st - $div)));
                    $st -= $div;
                }
            }
            return $ret;
        }
    }