<?php

    namespace Wixnit\Utilities;

    use DateTime;
    use stdClass;

    class Timespan extends Span
    {
        function __construct($start=null, $stop=null, $spanLastDay=false)
        {
            $sta = ($start == null) ? new Date(0) : new Date($start);
            $sto = ($stop == null) ? new Date(time()) : new Date($stop);

            $this->start = $sta->toEpochSeconds();

            if($spanLastDay === false)
            {
                $this->stop = $sto->toEpochSeconds();
            }
            else if(($start === true) || ($stop === true) || ($spanLastDay === true))
            {
                $this->stop = strtotime( date("m/d/Y", $sto->toEpochSeconds())) + ((60 * 60) * 24);
            }
        }

        /**
         * remove some time from the start of the timespan
         * @param mixed $time
         * @return Timespan
         */
        public function trimStart($time=""): Timespan
        {
            $val = (($time == "") ? 0 : $this->stringToTimeSec($time));
            $this->start += $val;
            return $this;
        }

        /**
         * remove some time from the end of the timespan
         * @param mixed $time
         * @return Timespan
         */
        public function trimEnd($time=""): Timespan
        {
            $val = (($time == "") ? 0 : $this->stringToTimeSec($time));
            $this->stop += $val;
            return $this;
        }

        /**
         * remove some time from both start and end of the timespan
         * @param mixed $time
         * @return Timespan
         */
        public function trimStartEnd($time=""): Timespan
        {
            $this->trimStart($time);
            $this->trimEnd($time);
            return $this;
        }


        #region private methods

        private function stringToTimeSec(string $time): int
        {
            $val = explode(":", $time);

            if(count($val) == 2)
            {
                return ((Convert::ToInt($val[0]) * (60 * 60)) + (Convert::ToInt($val[1]) * 60));
            }
            else
            {
                return 0;
            }
        }
        #endregion


        #region static methods

        /**
         * create a timespan from a span
         * @param Span $span
         * @return Timespan
         */
        public static function FromSpan(Span $span): Timespan
        {
            return new Timespan($span->start, $span->stop);
        }

        /**
         * create a timespan from a date
         * @param Date $date
         * @return Timespan
         */
        public static function FromDate(Date $date): Timespan
        {
            return new Timespan($date->toEpochSeconds(), $date->toEpochSeconds() + ((60 * 60) * 24));
        }

        /**
         * create a timespan from a string
         * @param string $time
         * @return Timespan
         */
        public static function FromString(string $time): Timespan
        {
            $val = explode(":", $time);

            if(count($val) == 2)
            {
                return new Timespan(0, ((Convert::ToInt($val[0]) * (60 * 60)) + (Convert::ToInt($val[1]) * 60)));
            }
            else
            {
                return new Timespan();
            }
        }

        /**
         * create a timespan from seconds
         * @param int $seconds
         * @return Timespan
         */
        public static function FromSeconds(int $seconds): Timespan
        {
            return new Timespan(0, $seconds);
        }

        /**
         * create a timespan from a DateTime object
         * @param DateTime $dateTime
         * @return Timespan
         */
        public static function FromDateTime(DateTime $dateTime): Timespan
        {
            return new Timespan($dateTime->getTimestamp(), $dateTime->getTimestamp() + ((60 * 60) * 24));
        }

        /**
         * create a timespan from an object
         * @param stdClass $obj
         * @return Timespan
         */
        public static function FromObject(stdClass $obj): Timespan
        {
            if(isset($obj->start) && isset($obj->stop))
            {
                return new Timespan($obj->start, $obj->stop);
            }
            else
            {
                return new Timespan();
            }
        }

        /**
         * create a timespan that represent the current month
         * @param string $date
         * @return Timespan
         */
        public static function ThisMonth(): Timespan
        {
            $m = Convert::ToInt(date("m"));
            if($m == 1)
            {
                $m = 12;
            }
            else
            {
                $m--;
            }
            $f = strtotime($m."/1/".date("y"));
            $sp = new Timespan($f, ($f + ((60 * 60) * 24) * 30));
            return $sp;
        }

        /**
         * create a timespan that represent the last month
         * @return Timespan
         */
        public static function LastMonth(): Timespan
        {
            $m = Convert::ToInt(date("m"));
            if($m == 1)
            {
                $m = 12;
            }
            else
            {
                $m--;
            }
            $f = strtotime($m."/1/".date("y"));
            $sp = new Timespan($f, ($f + ((60 * 60) * 24) * 30));
            return $sp;
        }

        /**
         * create a timespan that represent the current year
         * @return Timespan
         */
        public static function MonthSpan($from=null): Timespan
        {
            $f = ($from == null) ? new Date(time()) : new Date($from);
            $sp = new Timespan(($f->toEpochSeconds() - ((60 * 60) * 24) * 30), $f);
            //$sp = new Timespan();
            return $sp;
        }

        /**
         * create a timespan that represent the current year
         * @return Timespan
         */
        public static function ThisYear($from=null): Timespan
        {
            $f = ($from === null) ? new Date(time()) : new Date($from);
            $sp = new Timespan((strtotime("1/1/".date("d"))), $f);
            return $sp;
        }

        /**
         * create a timespan that represent last year
         * @return Timespan
         */
        public static function LastYear(): Timespan
        {
            $f = strtotime("1/1/".(Convert::ToInt(date("y")) - 1));
            $sp = new Timespan($f, (strtotime("31/12/".date("d"))));
            return $sp;
        }

        /**
         * create a timespan that represent the current week
         * @return Timespan
         */
        public static function ThisWeek()
        {
            $f = strtotime("last monday");
            $sp = new Timespan($f, ($f + ((60 * 60) * 24) * 7));
            return $sp;
        }

        /**
         * create a timespan that represent the last week
         * @return Timespan
         */
        public static function LastWeek()
        {
            $f = strtotime("last monday - 7 days");
            $sp = new Timespan($f, ($f + ((60 * 60) * 24) * 7));
            return $sp;
        }
        #endregion
    }