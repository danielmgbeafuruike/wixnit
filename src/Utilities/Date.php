<?php

    namespace Wixnit\Utilities;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;


    class Date implements ISerializable
    {
        protected int $value = 0;

        public int $day = 0;
        public int $month = 0;
        public int $year = 0;
        public int $minute = 0;
        public int $second = 0;
        public int $hour = 0;
        public string $weekDay = "";
        public string $monthName = "";

        function __construct($arg=null)
        {
            $this->init($arg);
        }

        /**
         * hydrate the date object
         * @param mixed $arg
         * @return void
         */
        private function init($arg=null)
        {
            if(is_int($arg))
            {
                $this->value = $arg;
            }
            else if($arg instanceof Date)
            {
                $this->value = $arg->toEpochSeconds();
            }
            else if(is_string($arg) && (count(explode("-", $arg)) > 1))
            {
                $this->value = strtotime($arg);
            }
            else if(is_string($arg) && (count(explode("/", $arg)) > 1))
            {
                $this->value = strtotime($arg);
            }
            else
            {
                $this->value = Convert::ToInt($arg);
            }

            $this->day = date("d", $this->value);
            $this->month = date("m", $this->value);
            $this->year = date("Y", $this->value);
            $this->hour = date("h", $this->value);
            $this->minute = date("i", $this->value);
            $this->second = date("s", $this->value);
            $this->weekDay = date("D", $this->value);
            $this->monthName = date("M", $this->value);
        }
        
        /**
         * convert the date time to epoch seconds
         * @return int
         */
        public function toEpochSeconds(): int
        {
            return $this->value;
        }


        #region static methods

        /**
         * checks if supplied argument is a date and time within today
         * @param \Wixnit\Utilities\Date|int|string $date
         * @return bool
         */
        public static function IsToday(Date | int | string $date): bool
        {
            $start = strtotime(date("m/d/Y", time()));
            $end = ($start + ((60 * 60) * 24)) - 1;
            $tm = new Date($date);

            return (($tm->toEpochSeconds() >= $start) && ($tm->toEpochSeconds() <= $end));
        }

        /**
         * checks if supplied argument is a date and time within tomorrow
         * @param \Wixnit\Utilities\Date|int|string $date
         * @return bool
         */
        public static function IsTomorow(Date | int | string $date): bool
        {
            $day = (((60 * 60) * 24) - 1);
            $start = strtotime(date("m/d/Y", time())) + $day;
            $end = $start + $day;
            $tm = new Date($date);

            return (($tm->toEpochSeconds() >= $start) && ($tm->toEpochSeconds() <= $end));
        }

        /**
         * checks if supplied argument is a date and time is from a past date
         * @param mixed $date
         * @return bool
         */
        public static function IsPast(Date | int | string $date): bool
        {
            $today = strtotime(date("m/d/Y", time()));
            $tm = new Date($date);

            return ($tm->toEpochSeconds() < $today);
        }

        /**
         * checks if supplied argument is a date and time within a future date
         * @param mixed $date
         * @return bool
         */
        public static function IsFuture(Date | int | string $date): bool
        {
            $today = strtotime(date("m/d/Y", time()));
            $endOfToday = ($today + ((60 * 60) * 24));
            $tm = new Date($date);

            return ($tm->toEpochSeconds() >= $endOfToday);
        }

        /**
         * checks if supplied argument is a date and time is withing the same day
         * @param mixed $time_1
         * @param mixed $time_2
         * @return bool
         */
        public static function IsSameDay(Date | int | string $time_1, Date | int | string $time_2): bool
        {
            $tm1 = strtotime("m/d/Y", (new Date($time_1))->toEpochSeconds());
            $tm2 = strtotime("m/d/Y", (new Date($time_2))->toEpochSeconds());

            return ($tm1 == $tm2);
        }
        #endregion



        #region implementation of ISerializable methods

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
            return $this->toEpochSeconds();
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
        #end region
    }