<?php

    namespace Wixnit\Utilities;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;

    /**
     * Represents a calendar date with no time-of-day component (e.g. a birthday, a due
     * date, an invoice date) - internally normalized to midnight so that two Dates on
     * the same calendar day are always equal, regardless of what time they were created at.
     *
     * If you need a specific point in time (date + time), use the `DateTime` class instead.
     */
    class Date implements ISerializable
    {
        protected int $value = 0;

        public int $day = 0;
        public int $month = 0;
        public int $year = 0;
        public string $weekDay = "";
        public string $monthName = "";

        function __construct($arg=null)
        {
            $this->init($arg);
        }

        /**
         * hydrate the date object, normalizing whatever was passed in to midnight of that calendar day
         * @param mixed $arg
         * @return void
         */
        private function init($arg=null)
        {
            if($arg === null)
            {
                $timestamp = time();
            }
            else if(is_int($arg))
            {
                $timestamp = $arg;
            }
            else if(($arg instanceof Date) || ($arg instanceof DateTime))
            {
                $timestamp = $arg->toEpochSeconds();
            }
            else if(is_string($arg) && ((count(explode("-", $arg)) > 1) || (count(explode("/", $arg)) > 1)))
            {
                $timestamp = strtotime($arg);
            }
            else
            {
                $timestamp = Convert::ToInt($arg);
            }

            //normalize to midnight of that calendar day
            $this->value = strtotime(date("Y-m-d", $timestamp)." 00:00:00");

            $this->day = (int) date("d", $this->value);
            $this->month = (int) date("m", $this->value);
            $this->year = (int) date("Y", $this->value);
            $this->weekDay = date("D", $this->value);
            $this->monthName = date("M", $this->value);
        }

        /**
         * convert the date to epoch seconds (midnight of that calendar day)
         * @return int
         */
        public function toEpochSeconds(): int
        {
            return $this->value;
        }

        /**
         * format the date using PHP's date() format characters, e.g. $date->format("F j, Y")
         * @param string $format
         * @return string
         */
        public function format(string $format = "Y-m-d"): string
        {
            return date($format, $this->value);
        }

        /**
         * convert to a full DateTime at midnight on this date
         * @return DateTime
         */
        public function toDateTime(): DateTime
        {
            return new DateTime($this->value);
        }

        /**
         * check whether this date falls on the same calendar day as another date
         * @param Date|DateTime|int|string $other
         * @return bool
         */
        public function equals(Date | DateTime | int | string $other): bool
        {
            $other = new Date($other);
            return $this->value === $other->value;
        }

        /**
         * is this date a Saturday or Sunday?
         * @return bool
         */
        public function isWeekend(): bool
        {
            $dayOfWeek = (int) date("N", $this->value);
            return ($dayOfWeek == 6) || ($dayOfWeek == 7);
        }

        /**
         * is the year of this date a leap year?
         * @return bool
         */
        public function isLeapYear(): bool
        {
            return (bool) date("L", $this->value);
        }

        /**
         * is this date today?
         * @return bool
         */
        public function isToday(): bool
        {
            return $this->equals(new Date());
        }

        /**
         * add (or, with a negative number, subtract) days to this date, in place
         * @param int $days
         * @return static
         */
        public function addDays(int $days): static
        {
            $this->init($this->value + ($days * 86400));
            return $this;
        }

        /**
         * add (or, with a negative number, subtract) calendar months to this date, in place
         * @param int $months
         * @return static
         */
        public function addMonths(int $months): static
        {
            $this->init(mktime(0, 0, 0, $this->month + $months, $this->day, $this->year));
            return $this;
        }

        /**
         * calculate an age in full years from this date (treated as a birth date) to today
         * @return int
         */
        public function age(): int
        {
            return $this->toDateTime()->age();
        }


        #region static methods

        /**
         * get today's date
         * @return Date
         */
        public static function Today(): Date
        {
            return new Date();
        }

        /**
         * get yesterday's date
         * @return Date
         */
        public static function Yesterday(): Date
        {
            return (new Date())->addDays(-1);
        }

        /**
         * get tomorrow's date
         * @return Date
         */
        public static function Tomorrow(): Date
        {
            return (new Date())->addDays(1);
        }
        #endregion


        #region implementation of ISerializable methods

        /**
         * get db field type for creating the appropriate db field type for saving the class to db
         * @return DBFieldType
         */
        public function _dbType(): DBFieldType
        {
            return DBFieldType::DATE;
        }

        /**
         * prepare the object for saving to db - stored as a "Y-m-d" string so it maps cleanly to a DATE column
         * @return string
         */
        public function _serialize(): string
        {
            return $this->format("Y-m-d");
        }

        /**
         * re-populate object from data received from db
         * @param mixed $data
         * @return void
         */
        public function _deserialize($data): void
        {
            $this->init($data);
        }
        #end region
    }
