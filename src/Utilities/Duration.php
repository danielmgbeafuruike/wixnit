<?php

    namespace Wixnit\Utilities;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;

    class Duration implements ISerializable
    {
        public int $minutes = 0;
        public int $hours = 0;
        public int $days = 0;
        public int $years = 0;
        public int $months = 0;
        public int $weeks = 0;
        public int $seconds = 0;

        function __construct($duration_in_seconds =null)
        {
            $this->init($duration_in_seconds);
        }

        /**
         * hydrate the duration object
         * @param mixed $arg
         * @return void
         */
        private function init($duration_in_seconds =null)
        {
            if($duration_in_seconds != null)
            {
                $value = Convert::ToInt($duration_in_seconds);

                //calculate years in supplies seconds
                $year = (((60 * 60) * 24) * (365 + (((60 * 60) * 24) / 4)));
                $this->years = $value / $year;

                $remainder = $value % $year;

                //calculate months in the seconds
                $month =  (((60 * 60) * 24) * 30);
                $this->months = $remainder / $month;

                $remainder = $remainder % $month;

                //calculate weeks in the seconds
                $week =  (((60 * 60) * 24) * 7);
                $this->weeks = $remainder / $week;

                $remainder = $remainder % $week;

                //calculate days in the seconds
                $day =  ((60 * 60) * 24);
                $this->days = $remainder / $day;

                $remainder = $remainder % $day;

                //calculate hours in the seconds
                $hour =  (60 * 60);
                $this->hours = $remainder / $hour;

                $remainder = $remainder % $hour;

                //calculate minutes in the seconds
                $minutes =  60;
                $this->minutes = $remainder / $minutes;

                $remainder = $remainder % $minutes;
                
                //the seconds left
                $this->seconds = $remainder;
            }
        }

        public function toSeconds()
        {
            $ret = $this->seconds +
                (60 * $this->minutes) +
                ((60 * 60) * $this->hours) +
                (((60 * 60) * 24) * $this->days) +
                ((((60 * 60) * 24) * 7) * $this->weeks) +
                ((((60 * 60) * 24) * 30) * $this->months) +
                ((((60 * 60) * 24) * 365.25) * $this->years);

            return $ret;
        }

        public function toMinuites(): int
        {
            return $this->toSeconds() / 60;
        }

        public function toHours(): int
        {
            return $this->toSeconds() / (60 * 60);
        }

        public function toDays(): int
        {
            return $this->toSeconds() / ((60 * 60) * 24);
        }


        #region static methods

        /**
         * @param $seconds
         * @return Duration
         * @comment this method creates a duration with only seconds set
         */
        static function Seconds($seconds)
        {
            $ret = new Duration();
            $ret->seconds = $seconds;
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
            $ret->minutes = $minutes;
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
            $ret->hours = $hours;
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
            $ret->days = $days;
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
            $ret->weeks = $weeks;
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
            $ret->minutes = $months;
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
            $ret->years = $years;
            return $ret;
        }
        #endregion


        #region implementing ISerializable

        /**
         * get db field type for creating the appropriate db field type for saving the class to db
         * @return DBFieldType
         */
        public function _dbType(): DBFieldType
        {
            return DBFieldType::INT;
        }

        /**
         * prepare the object for saving to db
         * @return int
         */
        public function _serialize(): int
        {
            return $this->toSeconds();
        }

        /**
         * re-populate object from data rceived from db
         * @param mixed $data
         * @return void
         */
        public function _deserialize($data): void
        {
            $this->init($data);
        }
        #region
    }