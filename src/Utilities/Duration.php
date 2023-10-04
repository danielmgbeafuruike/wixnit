<?php

    namespace Wixnit\Utilities;

    use Wixnit\Interfaces\IBase;

    class Duration implements IBase
    {
        public $Minutes = 0;
        public $Hours = 0;
        public $Days = 0;
        public $Years = 0;
        public $Months = 0;
        public $Weeks = 0;
        public int $Seconds = 0;

        function __construct($duration_in_seconds =null)
        {
            if($duration_in_seconds != null)
            {
                $value = Convert::ToInt($duration_in_seconds);

                //calculate years in supplies seconds
                $year = (((60 * 60) * 24) * (365 + (((60 * 60) * 24) / 4)));
                $this->Years = $value / $year;

                $remainder = $value % $year;

                //calculate months in the seconds
                $month =  (((60 * 60) * 24) * 30);
                $this->Months = $remainder / $month;

                $remainder = $remainder % $month;

                //calculate weeks in the seconds
                $week =  (((60 * 60) * 24) * 7);
                $this->Weeks = $remainder / $week;

                $remainder = $remainder % $week;

                //calculate days in the seconds
                $day =  ((60 * 60) * 24);
                $this->Days = $remainder / $day;

                $remainder = $remainder % $day;

                //calculate hours in the seconds
                $hour =  (60 * 60);
                $this->Hours = $remainder / $hour;

                $remainder = $remainder % $hour;

                //calculate minutes in the seconds
                $minutes =  60;
                $this->Minutes = $remainder / $minutes;

                $remainder = $remainder % $minutes;
                
                //the seconds left
                $this->Seconds = $remainder;
            }
        }


        public function ToString()
        {
            // TODO: Implement ToString() method.
        }


        public function ToInt()
        {
            $ret = $this->Seconds +
                (60 * $this->Minutes) +
                ((60 * 60) * $this->Hours) +
                (((60 * 60) * 24) * $this->Days) +
                ((((60 * 60) * 24) * 7) * $this->Weeks) +
                ((((60 * 60) * 24) * 30) * $this->Months) +
                ((((60 * 60) * 24) * 365.25) * $this->Years);

            return $ret;
        }

        public function ToBool(): bool
        {
            return Convert::ToBool($this->ToInt());
        }


        /**
         * @param WixDate
         * @return WixDate
         * @comment this methods subtracts the duration from the datetime parameter supplied to it
         */
        function TimePast(WixDate $time): WixDate
        {
            return new WixDate(Convert::ToInt($time) - $this->ToInt());
        }

        /**
         * @param WixDate
         * @return WixDate
         * @comment this method adds the duration from the datetime parameter supplied to it
         */
        function InFuture(WixDate $time): WixDate
        {
            return new WixDate(Convert::ToInt($time) + $this->ToInt());
        }

        /**
         * @param $seconds
         * @return Duration
         * @comment this method creates a duration with only seconds set
         */
        static function Seconds($seconds)
        {
            $ret = new Duration();
            $ret->Seconds = $seconds;
            return $ret;
        }

        /**
         * @param $minutes
         * @return Duration
         * @comment this method creates a duration with only minutes set
         */
        static function Minutes($minutes): Duration
        {
            $ret = new Duration();
            $ret->Minutes = $minutes;
            return $ret;
        }

        /**
         * @param $hours
         * @return Duration
         * @comment this method create a duration with only hours set
         */
        static function Hours($hours): Duration
        {
            $ret = new Duration();
            $ret->Hours = $hours;
            return $ret;
        }

        /**
         * @param $days
         * @return Duration
         * @comment this method creates a duration with only days set
         */
        static function Days($days): Duration
        {
            $ret = new Duration();
            $ret->Days = $days;
            return $ret;
        }

        /**
         * @param $weeks
         * @return Duration
         * @comment this method create a duration with only weeks set
         */
        static function Weeks($weeks): Duration
        {
            $ret = new Duration();
            $ret->Weeks = $weeks;
            return $ret;
        }

        /**
         * @param $months
         * @return Duration
         * @comment this method creates a duration with only months set
         */
        static function Months($months): Duration
        {
            $ret = new Duration();
            $ret->Minutes = $months;
            return $ret;
        }

        /**
         * @param $years
         * @return Duration
         * @comment this method creates a duration with only years set
         */
        static function Years($years): Duration
        {
            $ret = new Duration();
            $ret->Years = $years;
            return $ret;
        }
    }