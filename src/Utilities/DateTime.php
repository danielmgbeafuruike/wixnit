<?php

    namespace Wixnit\Utilities;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;

    /**
     * Represents a specific point in time (a date AND a time of day), stored internally
     * as epoch seconds. This is what used to be the `Date` class - if you're looking for
     * a date-only value with no time-of-day component (e.g. a birthday, a due date), see
     * the separate, lighter-weight `Date` class instead.
     */
    class DateTime implements ISerializable
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
            else if(($arg instanceof DateTime) || ($arg instanceof Date))
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

            $this->day = (int) date("d", $this->value);
            $this->month = (int) date("m", $this->value);
            $this->year = (int) date("Y", $this->value);
            $this->hour = (int) date("h", $this->value);
            $this->minute = (int) date("i", $this->value);
            $this->second = (int) date("s", $this->value);
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

        /**
         * format the date time using PHP's date() format characters, e.g. $date->format("Y-m-d")
         * @param string $format
         * @return string
         */
        public function format(string $format = "Y-m-d H:i:s"): string
        {
            return date($format, $this->value);
        }

        /**
         * convert to the simpler, date-only Date value (drops the time-of-day component)
         * @return Date
         */
        public function toDate(): Date
        {
            return new Date($this->value);
        }


        #region fluent modification methods

        /**
         * add (or, with a negative number, subtract) a number of days to this date time, in place
         * @param int $days
         * @return static
         */
        public function addDays(int $days): static
        {
            $this->init($this->value + ($days * 86400));
            return $this;
        }

        /**
         * add (or, with a negative number, subtract) a number of hours to this date time, in place
         * @param int $hours
         * @return static
         */
        public function addHours(int $hours): static
        {
            $this->init($this->value + ($hours * 3600));
            return $this;
        }

        /**
         * add (or, with a negative number, subtract) a number of minutes to this date time, in place
         * @param int $minutes
         * @return static
         */
        public function addMinutes(int $minutes): static
        {
            $this->init($this->value + ($minutes * 60));
            return $this;
        }

        /**
         * add (or, with a negative number, subtract) a number of calendar months to this date time, in place.
         * Handles month/year overflow correctly (e.g. adding 1 month to December rolls into January of next year).
         * @param int $months
         * @return static
         */
        public function addMonths(int $months): static
        {
            $ts = mktime($this->hour, $this->minute, $this->second, $this->month + $months, $this->day, $this->year);
            $this->init($ts);
            return $this;
        }

        /**
         * add (or, with a negative number, subtract) a number of years to this date time, in place
         * @param int $years
         * @return static
         */
        public function addYears(int $years): static
        {
            $ts = mktime($this->hour, $this->minute, $this->second, $this->month, $this->day, $this->year + $years);
            $this->init($ts);
            return $this;
        }

        /**
         * move this date time to midnight (00:00:00) on the first day of its month, in place
         * @return static
         */
        public function startOfMonth(): static
        {
            $ts = mktime(0, 0, 0, $this->month, 1, $this->year);
            $this->init($ts);
            return $this;
        }

        /**
         * move this date time to the last moment (23:59:59) of the last day of its month, in place
         * @return static
         */
        public function endOfMonth(): static
        {
            //day "0" of next month rolls back to the last day of the current month
            $ts = mktime(23, 59, 59, $this->month + 1, 0, $this->year);
            $this->init($ts);
            return $this;
        }
        #endregion


        #region comparisons

        /**
         * get the absolute difference between this date time and another as a Duration
         * @param DateTime|Date|int|string $other
         * @return Duration
         */
        public function diff(DateTime | Date | int | string $other): Duration
        {
            $otherSeconds = ($other instanceof DateTime || $other instanceof Date) ? $other->toEpochSeconds() : (new DateTime($other))->toEpochSeconds();
            return new Duration(abs($this->value - $otherSeconds));
        }

        /**
         * is this date time today?
         * @return bool
         */
        public function withinToday(): bool
        {
            return $this->toDate()->isToday();
        }

        /**
         * is this date time yesterday?
         * @return bool
         */
        public function withinYesterday(): bool
        {
            return DateTime::IsYesterday($this);
        }

        /**
         * is this date time a Saturday or Sunday?
         * @return bool
         */
        public function isWeekend(): bool
        {
            $dayOfWeek = (int) date("N", $this->value);
            return ($dayOfWeek == 6) || ($dayOfWeek == 7);
        }

        /**
         * is the year of this date time a leap year?
         * @return bool
         */
        public function isLeapYear(): bool
        {
            return (bool) date("L", $this->value);
        }

        /**
         * format this date time as a short, human-friendly relative string,
         * e.g. "just now", "5 minutes ago", "in 3 days", "2 years ago"
         * @return string
         */
        public function formatHuman(): string
        {
            $diff = time() - $this->value;
            $isFuture = $diff < 0;
            $diff = abs($diff);

            if($diff < 60)
            {
                return "just now";
            }

            $units = [
                ["year", 31536000],
                ["month", 2592000],
                ["week", 604800],
                ["day", 86400],
                ["hour", 3600],
                ["minute", 60],
            ];

            for($i = 0; $i < count($units); $i++)
            {
                [$label, $seconds] = $units[$i];
                $count = intdiv($diff, $seconds);

                if($count >= 1)
                {
                    $unit = ($count == 1) ? $label : $label."s";
                    return $isFuture ? "in $count $unit" : "$count $unit ago";
                }
            }
            return "just now";
        }

        /**
         * calculate an age in full years from this date time (treated as a birth date) to now
         * @return int
         */
        public function age(): int
        {
            $now = new DateTime(time());
            $age = $now->year - $this->year;

            if(($now->month < $this->month) || (($now->month == $this->month) && ($now->day < $this->day)))
            {
                $age--;
            }
            return $age;
        }
        #endregion


        #region static methods

        /**
         * checks if supplied argument is a date and time within today
         * @param \Wixnit\Utilities\DateTime|int|string $date
         * @return bool
         */
        public static function IsToday(DateTime | Date | int | string $date): bool
        {
            $start = strtotime(date("m/d/Y", time()));
            $end = ($start + ((60 * 60) * 24)) - 1;
            $tm = new DateTime($date);

            return (($tm->toEpochSeconds() >= $start) && ($tm->toEpochSeconds() <= $end));
        }

        /**
         * checks if supplied argument is a date and time within yesterday
         * @param \Wixnit\Utilities\DateTime|int|string $date
         * @return bool
         */
        public static function IsYesterday(DateTime | Date | int | string $date): bool
        {
            $day = (60 * 60) * 24;
            $endOfYesterday = strtotime(date("m/d/Y", time())) - 1;
            $startOfYesterday = $endOfYesterday - $day + 1;
            $tm = new DateTime($date);

            return (($tm->toEpochSeconds() >= $startOfYesterday) && ($tm->toEpochSeconds() <= $endOfYesterday));
        }

        /**
         * checks if supplied argument is a date and time within tomorrow
         * @param \Wixnit\Utilities\DateTime|int|string $date
         * @return bool
         */
        public static function IsTomorow(DateTime | Date | int | string $date): bool
        {
            $day = (((60 * 60) * 24) - 1);
            $start = strtotime(date("m/d/Y", time())) + $day;
            $end = $start + $day;
            $tm = new DateTime($date);

            return (($tm->toEpochSeconds() >= $start) && ($tm->toEpochSeconds() <= $end));
        }

        /**
         * checks if supplied argument is a date and time is from a past date
         * @param mixed $date
         * @return bool
         */
        public static function IsPast(DateTime | Date | int | string $date): bool
        {
            $today = strtotime(date("m/d/Y", time()));
            $tm = new DateTime($date);

            return ($tm->toEpochSeconds() < $today);
        }

        /**
         * checks if supplied argument is a date and time within a future date
         * @param mixed $date
         * @return bool
         */
        public static function IsFuture(DateTime | Date | int | string $date): bool
        {
            $today = strtotime(date("m/d/Y", time()));
            $endOfToday = ($today + ((60 * 60) * 24));
            $tm = new DateTime($date);

            return ($tm->toEpochSeconds() >= $endOfToday);
        }

        /**
         * checks if supplied argument is a date and time is withing the same day
         * @param mixed $time_1
         * @param mixed $time_2
         * @return bool
         */
        public static function IsSameDay(DateTime | Date | int | string $time_1, DateTime | Date | int | string $time_2): bool
        {
            $tm1 = strtotime("m/d/Y", (new DateTime($time_1))->toEpochSeconds());
            $tm2 = strtotime("m/d/Y", (new DateTime($time_2))->toEpochSeconds());

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
