<?php

    namespace Wixnit\Utilities;

    use Wixnit\Exception\BusinessCalendarException;

    /**
     * A configurable business/working calendar: which days of the week count as business
     * days, what hours (and breaks) count as working hours, and which dates are holidays -
     * plus the arithmetic that makes those useful (skip weekends/holidays when adding days,
     * measure elapsed *working* time between two moments, find a deadline N business hours
     * from now, and so on).
     *
     * Every method that takes a "date" accepts anything Date/DateTime themselves accept
     * (Date, DateTime, an int epoch, a parseable string, or null for "now") - and every
     * method that takes a range accepts either two such values, or a Span/Timespan directly.
     * Every method that returns a date-only result returns a Date; every duration-shaped
     * result returns a Duration; every range-shaped result returns a Timespan.
     *
     *   $calendar = BusinessCalendar::UnitedStates(); // Mon-Fri, 9-5, common US federal holidays
     *
     *   $calendar->isBusinessDay();                    // is today a business day?
     *   $calendar->isHoliday("2026-12-25");             // true
     *   $calendar->nextBusinessDay();                   // the next Date that's a business day
     *   $calendar->workingHours();                      // today's working-hours Timespan, or null
     *   $calendar->addBusinessDays(new Date(), 10);      // 10 business days from today
     *   $calendar->businessHoursBetween($opened, $now);  // elapsed working time, as a Duration
     *   $calendar->addBusinessDuration($opened, Duration::Hours(8)); // an 8-business-hour SLA deadline
     *
     * Configuration methods mutate and return $this, so a calendar is normally built up
     * fluently in one chain - see Default()/UnitedStates() below for worked examples.
     */
    class BusinessCalendar
    {
        private const MAX_SEARCH_DAYS = 3650; // ~10 years - a sane bound for "walk forward/back until..." loops

        /**
         * @var int[] ISO weekdays (1 = Monday ... 7 = Sunday) that count as business days
         */
        private array $businessDays = [1, 2, 3, 4, 5];

        /**
         * @var array{0: Time, 1: Time} the default daily working-hours window
         */
        private array $defaultHours;

        /**
         * @var array<int, array{0: Time, 1: Time}> per-weekday overrides of $defaultHours, keyed by ISO weekday
         */
        private array $hoursByWeekday = [];

        /**
         * @var array<int, array{start: Time, end: Time, days: int[]|null}> break windows (e.g. lunch) cut out of working hours
         */
        private array $breaks = [];

        /**
         * @var array<int, array> holiday rules - see addHoliday()/addRecurringHoliday()/addNthWeekdayHoliday()/addHolidayRange()
         */
        private array $holidayRules = [];

        public function __construct()
        {
            $this->defaultHours = [new Time("09:00:00"), new Time("17:00:00")];
        }


        #region configuration

        /**
         * set which days of the week count as business days
         * @param int[] $isoWeekdays 1 (Monday) through 7 (Sunday)
         * @return static
         */
        public function setBusinessDays(array $isoWeekdays): static
        {
            $this->businessDays = array_values($isoWeekdays);
            return $this;
        }

        /**
         * set daily working hours, either for every business day or for a specific subset of them
         * @param Time|string|int $start
         * @param Time|string|int $end
         * @param int[]|null $onlyDays if given, only these ISO weekdays get this window (e.g. a Saturday half-day); applies to every business day otherwise
         * @return static
         */
        public function setWorkingHours(Time | string | int $start, Time | string | int $end, ?array $onlyDays = null): static
        {
            $window = [new Time($start), new Time($end)];

            if($onlyDays === null)
            {
                $this->defaultHours = $window;
            }
            else
            {
                for($i = 0; $i < count($onlyDays); $i++)
                {
                    $this->hoursByWeekday[$onlyDays[$i]] = $window;
                }
            }
            return $this;
        }

        /**
         * cut a recurring break window (e.g. lunch) out of working hours
         * @param Time|string|int $start
         * @param Time|string|int $end
         * @param int[]|null $onlyDays if given, only applies on these ISO weekdays; every business day otherwise
         * @return static
         */
        public function addBreak(Time | string | int $start, Time | string | int $end, ?array $onlyDays = null): static
        {
            $this->breaks[] = ["start" => new Time($start), "end" => new Time($end), "days" => $onlyDays];
            return $this;
        }

        /**
         * register a one-off holiday on a specific date
         * @param Date|DateTime|int|string $date
         * @param string $name
         * @return static
         */
        public function addHoliday(Date | DateTime | int | string $date, string $name = "Holiday"): static
        {
            $this->holidayRules[] = ["type" => "fixed", "date" => new Date($date), "name" => $name];
            return $this;
        }

        /**
         * register a holiday that recurs on the same month/day every year (e.g. Dec 25)
         * @param int $month 1-12
         * @param int $day 1-31
         * @param string $name
         * @return static
         */
        public function addRecurringHoliday(int $month, int $day, string $name = "Holiday"): static
        {
            $this->holidayRules[] = ["type" => "annual", "month" => $month, "day" => $day, "name" => $name];
            return $this;
        }

        /**
         * register a holiday that recurs on the Nth occurrence of a weekday in a given month,
         * every year (e.g. "4th Thursday of November" for Thanksgiving, "last Monday of May" for Memorial Day)
         * @param int $month 1-12
         * @param int $nth 1-5 for "1st" through "5th", or -1 for "last"
         * @param int $weekday ISO weekday: 1 (Monday) through 7 (Sunday)
         * @param string $name
         * @return static
         */
        public function addNthWeekdayHoliday(int $month, int $nth, int $weekday, string $name = "Holiday"): static
        {
            $this->holidayRules[] = ["type" => "nthWeekday", "month" => $month, "nth" => $nth, "weekday" => $weekday, "name" => $name];
            return $this;
        }

        /**
         * register a multi-day holiday/closure period (e.g. an end-of-year office shutdown), inclusive of both ends
         * @param Date|DateTime|int|string $start
         * @param Date|DateTime|int|string $end
         * @param string $name
         * @return static
         */
        public function addHolidayRange(Date | DateTime | int | string $start, Date | DateTime | int | string $end, string $name = "Holiday"): static
        {
            $this->holidayRules[] = ["type" => "range", "start" => new Date($start), "end" => new Date($end), "name" => $name];
            return $this;
        }

        /**
         * remove every registered holiday rule
         * @return static
         */
        public function clearHolidays(): static
        {
            $this->holidayRules = [];
            return $this;
        }
        #endregion


        #region business day queries

        /**
         * is $date a business day (a configured business-day weekday, and not a holiday)?
         * @param Date|DateTime|int|string|null $date defaults to today
         * @return bool
         */
        public function isBusinessDay(Date | DateTime | int | string | null $date = null): bool
        {
            $d = new Date($date);

            if(!in_array((int) date("N", $d->toEpochSeconds()), $this->businessDays, true))
            {
                return false;
            }
            return !$this->isHoliday($d);
        }

        /**
         * the next business day strictly after $date
         * @param Date|DateTime|int|string|null $date defaults to today
         * @return Date
         * @throws BusinessCalendarException if no business day is found within a reasonable search window
         */
        public function nextBusinessDay(Date | DateTime | int | string | null $date = null): Date
        {
            $d = (new Date($date))->addDays(1);

            for($i = 0; $i < self::MAX_SEARCH_DAYS; $i++)
            {
                if($this->isBusinessDay($d))
                {
                    return $d;
                }
                $d = $d->addDays(1);
            }
            throw BusinessCalendarException::NoBusinessDaysFound();
        }

        /**
         * the previous business day strictly before $date
         * @param Date|DateTime|int|string|null $date defaults to today
         * @return Date
         * @throws BusinessCalendarException if no business day is found within a reasonable search window
         */
        public function previousBusinessDay(Date | DateTime | int | string | null $date = null): Date
        {
            $d = (new Date($date))->addDays(-1);

            for($i = 0; $i < self::MAX_SEARCH_DAYS; $i++)
            {
                if($this->isBusinessDay($d))
                {
                    return $d;
                }
                $d = $d->addDays(-1);
            }
            throw BusinessCalendarException::NoBusinessDaysFound();
        }

        /**
         * move $days business days forward (or, with a negative number, backward) from $date,
         * skipping weekends and holidays entirely
         * @param Date|DateTime|int|string|null $date defaults to today
         * @param int $days
         * @return Date
         * @throws BusinessCalendarException if no business day is found within a reasonable search window
         */
        public function addBusinessDays(Date | DateTime | int | string | null $date = null, int $days = 1): Date
        {
            $d = new Date($date);
            $step = ($days >= 0) ? 1 : -1;
            $remaining = abs($days);
            $safety = 0;

            while($remaining > 0)
            {
                $d = $d->addDays($step);

                if($this->isBusinessDay($d))
                {
                    $remaining--;
                }

                $safety++;
                if($safety > self::MAX_SEARCH_DAYS)
                {
                    throw BusinessCalendarException::NoBusinessDaysFound();
                }
            }
            return $d;
        }

        /**
         * count business days between two dates, inclusive of both ends
         * @param Date|DateTime|int|string $start
         * @param Date|DateTime|int|string $end
         * @return int
         */
        public function businessDaysBetween(Date | DateTime | int | string $start, Date | DateTime | int | string $end): int
        {
            $startDate = new Date($start);
            $endDate = new Date($end);

            if($endDate->toEpochSeconds() < $startDate->toEpochSeconds())
            {
                return 0;
            }

            $count = 0;
            $cursor = clone $startDate;

            while($cursor->toEpochSeconds() <= $endDate->toEpochSeconds())
            {
                if($this->isBusinessDay($cursor))
                {
                    $count++;
                }
                $cursor = $cursor->addDays(1);
            }
            return $count;
        }

        /**
         * count business days within a Span/Timespan
         * @param Span $span
         * @return int
         */
        public function businessDaysIn(Span $span): int
        {
            return $this->businessDaysBetween((int) $span->start, (int) $span->stop);
        }
        #endregion


        #region holiday queries

        /**
         * is $date a registered holiday?
         * @param Date|DateTime|int|string|null $date defaults to today
         * @return bool
         */
        public function isHoliday(Date | DateTime | int | string | null $date = null): bool
        {
            return $this->getHoliday($date) !== null;
        }

        /**
         * get the holiday matching $date, if any
         * @param Date|DateTime|int|string|null $date defaults to today
         * @return Holiday|null
         */
        public function getHoliday(Date | DateTime | int | string | null $date = null): ?Holiday
        {
            $d = new Date($date);

            for($i = 0; $i < count($this->holidayRules); $i++)
            {
                $matched = $this->ruleMatchesDate($this->holidayRules[$i], $d);

                if($matched !== null)
                {
                    return new Holiday($matched, $this->holidayRules[$i]["name"]);
                }
            }
            return null;
        }

        /**
         * the name of the holiday on $date, or null if it isn't one
         * @param Date|DateTime|int|string|null $date defaults to today
         * @return string|null
         */
        public function holidayName(Date | DateTime | int | string | null $date = null): ?string
        {
            return $this->getHoliday($date)?->name;
        }

        /**
         * the next upcoming holiday strictly after $after
         * @param Date|DateTime|int|string|null $after defaults to today
         * @return Holiday|null null if none found within a reasonable search window
         */
        public function nextHoliday(Date | DateTime | int | string | null $after = null): ?Holiday
        {
            $d = (new Date($after))->addDays(1);

            for($i = 0; $i < self::MAX_SEARCH_DAYS; $i++)
            {
                $holiday = $this->getHoliday($d);

                if($holiday !== null)
                {
                    return $holiday;
                }
                $d = $d->addDays(1);
            }
            return null;
        }

        /**
         * every holiday between two dates, inclusive of both ends, in chronological order
         * @param Date|DateTime|int|string $start
         * @param Date|DateTime|int|string $end
         * @return Holiday[]
         */
        public function holidaysBetween(Date | DateTime | int | string $start, Date | DateTime | int | string $end): array
        {
            $startDate = new Date($start);
            $endDate = new Date($end);

            $ret = [];
            $cursor = clone $startDate;

            while($cursor->toEpochSeconds() <= $endDate->toEpochSeconds())
            {
                $holiday = $this->getHoliday($cursor);

                if($holiday !== null)
                {
                    $ret[] = $holiday;
                }
                $cursor = $cursor->addDays(1);
            }
            return $ret;
        }

        /**
         * every holiday within a Span/Timespan, in chronological order
         * @param Span $span
         * @return Holiday[]
         */
        public function holidaysIn(Span $span): array
        {
            return $this->holidaysBetween((int) $span->start, (int) $span->stop);
        }
        #endregion


        #region working hours

        /**
         * the working-hours window for $date, as a Timespan - or null if $date isn't a business day at all
         * @param Date|DateTime|int|string|null $date defaults to today
         * @return Timespan|null
         */
        public function workingHours(Date | DateTime | int | string | null $date = null): ?Timespan
        {
            $d = new Date($date);

            if(!$this->isBusinessDay($d))
            {
                return null;
            }

            [$start, $end] = $this->hoursFor($d);
            return new Timespan($d->toEpochSeconds() + $start->toSeconds(), $d->toEpochSeconds() + $end->toSeconds());
        }

        /**
         * does $moment fall within working hours (business day, within the daily window, and
         * not inside a configured break)?
         * @param DateTime|Date|int|string|null $moment defaults to now
         * @return bool
         */
        public function isWorkingHours(DateTime | Date | int | string | null $moment = null): bool
        {
            $dt = new DateTime($moment);
            $t = $dt->toEpochSeconds();

            $segments = $this->workingSegmentsOn($dt->toDate());

            for($i = 0; $i < count($segments); $i++)
            {
                if(($t >= $segments[$i]["start"]) && ($t <= $segments[$i]["end"]))
                {
                    return true;
                }
            }
            return false;
        }
        #endregion


        #region business-duration arithmetic

        /**
         * the total *working* time elapsed between two moments - business days only, within
         * working hours only, breaks excluded - as a Duration. Useful for SLA-style metrics
         * ("how many business hours has this ticket been open").
         * @param DateTime|Date|int|string $start
         * @param DateTime|Date|int|string $end
         * @return Duration
         */
        public function businessHoursBetween(DateTime | Date | int | string $start, DateTime | Date | int | string $end): Duration
        {
            $startDt = new DateTime($start);
            $endDt = new DateTime($end);

            if($endDt->toEpochSeconds() <= $startDt->toEpochSeconds())
            {
                return new Duration(0);
            }

            $total = 0;
            $cursor = $startDt->toDate();
            $endDate = $endDt->toDate();

            while($cursor->toEpochSeconds() <= $endDate->toEpochSeconds())
            {
                $segments = $this->workingSegmentsOn($cursor);

                for($i = 0; $i < count($segments); $i++)
                {
                    $segStart = max($segments[$i]["start"], (int) $startDt->toEpochSeconds());
                    $segEnd = min($segments[$i]["end"], (int) $endDt->toEpochSeconds());

                    if($segEnd > $segStart)
                    {
                        $total += ($segEnd - $segStart);
                    }
                }
                $cursor = $cursor->addDays(1);
            }
            return new Duration($total);
        }

        /**
         * the total working time within a Span/Timespan, as a Duration
         * @param Span $span
         * @return Duration
         */
        public function businessHoursIn(Span $span): Duration
        {
            return $this->businessHoursBetween((int) $span->start, (int) $span->stop);
        }

        /**
         * the moment $duration worth of *working* time from now lands, starting at $start -
         * skipping nights, weekends, holidays, and breaks entirely. Useful for deadlines
         * ("this must be resolved within 8 business hours").
         * @param DateTime|Date|int|string $start
         * @param Duration $duration
         * @return DateTime
         * @throws BusinessCalendarException if no working time is found within a reasonable search window
         */
        public function addBusinessDuration(DateTime | Date | int | string $start, Duration $duration): DateTime
        {
            $remaining = (int) $duration->toSeconds();
            $cursor = new DateTime($start);

            if($remaining <= 0)
            {
                return $cursor;
            }

            $day = $cursor->toDate();
            $dayStartCursor = (int) $cursor->toEpochSeconds();

            for($daysChecked = 0; $daysChecked <= self::MAX_SEARCH_DAYS; $daysChecked++)
            {
                $segments = $this->workingSegmentsOn($day);

                for($i = 0; $i < count($segments); $i++)
                {
                    $effectiveStart = max($segments[$i]["start"], $dayStartCursor);

                    if($effectiveStart >= $segments[$i]["end"])
                    {
                        continue; // this segment is entirely behind our starting point
                    }

                    $available = $segments[$i]["end"] - $effectiveStart;

                    if($remaining <= $available)
                    {
                        return new DateTime($effectiveStart + $remaining);
                    }
                    $remaining -= $available;
                }

                $day = $day->addDays(1);
                $dayStartCursor = $day->toEpochSeconds(); // midnight of the new day - nothing to clip on subsequent days
            }
            throw BusinessCalendarException::NoBusinessDaysFound();
        }
        #endregion


        #region presets

        /**
         * a plain calendar: Monday-Friday, 9am-5pm, no holidays configured
         * @return static
         */
        public static function Default(): static
        {
            return (new static())->setBusinessDays([1, 2, 3, 4, 5])->setWorkingHours("09:00:00", "17:00:00");
        }

        /**
         * Default(), plus the common United States federal holidays. A reasonable starting
         * point, not a legal reference - adjust with addHoliday()/clearHolidays() as needed.
         * @return static
         */
        public static function UnitedStates(): static
        {
            $calendar = static::Default();

            $calendar->addRecurringHoliday(1, 1, "New Year's Day");
            $calendar->addNthWeekdayHoliday(1, 3, 1, "Martin Luther King Jr. Day");
            $calendar->addNthWeekdayHoliday(2, 3, 1, "Washington's Birthday");
            $calendar->addNthWeekdayHoliday(5, -1, 1, "Memorial Day");
            $calendar->addRecurringHoliday(6, 19, "Juneteenth");
            $calendar->addRecurringHoliday(7, 4, "Independence Day");
            $calendar->addNthWeekdayHoliday(9, 1, 1, "Labor Day");
            $calendar->addNthWeekdayHoliday(10, 2, 1, "Columbus Day");
            $calendar->addRecurringHoliday(11, 11, "Veterans Day");
            $calendar->addNthWeekdayHoliday(11, 4, 4, "Thanksgiving Day");
            $calendar->addRecurringHoliday(12, 25, "Christmas Day");

            return $calendar;
        }
        #endregion


        #region private helpers

        /**
         * the [start, end] Time pair in effect for a given date (a per-weekday override if one
         * is set, otherwise the default hours) - does not check whether $date is a business day
         * @param Date $date
         * @return array{0: Time, 1: Time}
         */
        private function hoursFor(Date $date): array
        {
            $weekday = (int) date("N", $date->toEpochSeconds());
            return $this->hoursByWeekday[$weekday] ?? $this->defaultHours;
        }

        /**
         * the configured breaks that apply on a given date, resolved to concrete epoch-second
         * start/end pairs for that specific day
         * @param Date $date
         * @return array<int, array{startEpoch: int, endEpoch: int}>
         */
        private function breaksFor(Date $date): array
        {
            $weekday = (int) date("N", $date->toEpochSeconds());
            $ret = [];

            for($i = 0; $i < count($this->breaks); $i++)
            {
                $break = $this->breaks[$i];

                if(($break["days"] === null) || in_array($weekday, $break["days"], true))
                {
                    $ret[] = [
                        "startEpoch" => $date->toEpochSeconds() + $break["start"]->toSeconds(),
                        "endEpoch" => $date->toEpochSeconds() + $break["end"]->toSeconds(),
                    ];
                }
            }
            return $ret;
        }

        /**
         * the working segments (working hours, minus any breaks) for a given date, as
         * epoch-second [start, end] pairs. Empty if $date isn't a business day.
         * @param Date $date
         * @return array<int, array{start: int, end: int}>
         */
        private function workingSegmentsOn(Date $date): array
        {
            if(!$this->isBusinessDay($date))
            {
                return [];
            }

            [$start, $end] = $this->hoursFor($date);
            $segments = [["start" => $date->toEpochSeconds() + $start->toSeconds(), "end" => $date->toEpochSeconds() + $end->toSeconds()]];

            $breaks = $this->breaksFor($date);

            for($i = 0; $i < count($breaks); $i++)
            {
                $segments = $this->subtractInterval($segments, $breaks[$i]["startEpoch"], $breaks[$i]["endEpoch"]);
            }
            return $segments;
        }

        /**
         * cut [$cutStart, $cutEnd] out of a list of [start, end] segments, splitting a segment
         * in two if the cut falls in its middle
         * @param array<int, array{start: int, end: int}> $segments
         * @param int $cutStart
         * @param int $cutEnd
         * @return array<int, array{start: int, end: int}>
         */
        private function subtractInterval(array $segments, int $cutStart, int $cutEnd): array
        {
            $result = [];

            for($i = 0; $i < count($segments); $i++)
            {
                $seg = $segments[$i];

                if(($cutEnd <= $seg["start"]) || ($cutStart >= $seg["end"]))
                {
                    $result[] = $seg; // no overlap
                    continue;
                }

                if($cutStart > $seg["start"])
                {
                    $result[] = ["start" => $seg["start"], "end" => min($cutStart, $seg["end"])];
                }
                if($cutEnd < $seg["end"])
                {
                    $result[] = ["start" => max($cutEnd, $seg["start"]), "end" => $seg["end"]];
                }
            }
            return $result;
        }

        /**
         * check a single holiday rule against a date, returning the matching Date if it's a
         * hit (which, for annual/nthWeekday rules, is $date itself - included for a uniform
         * return shape) or null otherwise
         * @param array $rule
         * @param Date $date
         * @return Date|null
         */
        private function ruleMatchesDate(array $rule, Date $date): ?Date
        {
            switch($rule["type"])
            {
                case "fixed":
                    return $rule["date"]->equals($date) ? $rule["date"] : null;

                case "annual":
                    return (($rule["month"] === $date->month) && ($rule["day"] === $date->day)) ? $date : null;

                case "nthWeekday":
                    $computed = $this->nthWeekdayOfMonth($date->year, $rule["month"], $rule["weekday"], $rule["nth"]);
                    return (($computed !== null) && $computed->equals($date)) ? $date : null;

                case "range":
                    return (($date->toEpochSeconds() >= $rule["start"]->toEpochSeconds()) && ($date->toEpochSeconds() <= $rule["end"]->toEpochSeconds())) ? $date : null;

                default:
                    return null;
            }
        }

        /**
         * compute the concrete date of the Nth occurrence of a weekday in a given month/year
         * (e.g. year=2026, month=11, weekday=4 (Thursday), nth=4 -> the 4th Thursday of
         * November 2026), or the last occurrence if $nth is -1
         * @param int $year
         * @param int $month
         * @param int $weekday ISO weekday: 1 (Monday) through 7 (Sunday)
         * @param int $nth 1-5, or -1 for "last"
         * @return Date|null null if $weekday/$nth aren't valid, or the month doesn't have an Nth occurrence (e.g. no 5th Friday)
         */
        private function nthWeekdayOfMonth(int $year, int $month, int $weekday, int $nth): ?Date
        {
            $weekdayNames = [1 => "monday", 2 => "tuesday", 3 => "wednesday", 4 => "thursday", 5 => "friday", 6 => "saturday", 7 => "sunday"];
            $ordinals = [1 => "first", 2 => "second", 3 => "third", 4 => "fourth", 5 => "fifth"];

            if(!isset($weekdayNames[$weekday]))
            {
                return null;
            }

            $ordinal = ($nth === -1) ? "last" : ($ordinals[$nth] ?? null);
            if($ordinal === null)
            {
                return null;
            }

            $monthName = date("F", mktime(0, 0, 0, $month, 1, $year));
            $timestamp = strtotime("$ordinal ".$weekdayNames[$weekday]." of $monthName $year");

            if($timestamp === false)
            {
                return null;
            }

            $result = new Date($timestamp);

            //guard against strtotime spilling into an adjacent month for a nonexistent
            //occurrence (e.g. asking for the 5th Friday of a month that only has 4)
            return ($result->month === $month) ? $result : null;
        }
        #endregion
    }
