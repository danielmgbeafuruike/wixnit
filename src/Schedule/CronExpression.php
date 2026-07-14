<?php

    namespace Wixnit\Schedule;

    use DateTimeImmutable;
    use DateTimeInterface;
    use DateTimeZone;
    use Wixnit\Exception\ScheduleException;

    /**
     * Parses and evaluates a standard 5-field cron expression: "minute hour day-of-month
     * month day-of-week". Each field accepts *, a single value, a list (1,2,3), a range
     * (1-5), a step (*\/5 or 1-30/5), or any combination thereof (1-5,10,20-25/5). The
     * month and day-of-week fields also accept 3-letter names (JAN-DEC, SUN-SAT), and the
     * whole expression accepts the standard @hourly/@daily/@weekly/@monthly/@quarterly/
     * @yearly/@annually shorthands.
     *
     * Matching follows the standard (if slightly surprising) cron rule for day-of-month vs.
     * day-of-week: if BOTH are restricted (not "*"), a date matches if EITHER matches; if
     * only one is restricted, only that one has to match.
     */
    class CronExpression
    {
        private const MONTH_NAMES = ["JAN" => 1, "FEB" => 2, "MAR" => 3, "APR" => 4, "MAY" => 5, "JUN" => 6, "JUL" => 7, "AUG" => 8, "SEP" => 9, "OCT" => 10, "NOV" => 11, "DEC" => 12];
        private const WEEKDAY_NAMES = ["SUN" => 0, "MON" => 1, "TUE" => 2, "WED" => 3, "THU" => 4, "FRI" => 5, "SAT" => 6];

        private const SHORTHANDS = [
            "@yearly" => "0 0 1 1 *",
            "@annually" => "0 0 1 1 *",
            "@monthly" => "0 0 1 * *",
            "@weekly" => "0 0 * * 0",
            "@daily" => "0 0 * * *",
            "@midnight" => "0 0 * * *",
            "@hourly" => "0 * * * *",
        ];

        private string $expression;
        private array $minutes;
        private array $hours;
        private array $daysOfMonth;
        private array $months;
        private array $daysOfWeek;
        private bool $dayOfMonthRestricted;
        private bool $dayOfWeekRestricted;

        /**
         * @param string $expression a 5-field cron expression, or one of the @-shorthands above
         * @throws ScheduleException if the expression can't be parsed
         */
        public function __construct(string $expression)
        {
            $this->expression = trim($expression);

            $resolved = self::SHORTHANDS[strtolower($this->expression)] ?? $this->expression;
            $fields = preg_split('/\s+/', trim($resolved));

            if (($fields === false) || (count($fields) !== 5)) {
                throw ScheduleException::InvalidCronExpression($this->expression);
            }

            [$minuteField, $hourField, $domField, $monthField, $dowField] = $fields;

            $this->minutes = self::parseField($minuteField, 0, 59);
            $this->hours = self::parseField($hourField, 0, 23);
            $this->daysOfMonth = self::parseField($domField, 1, 31);
            $this->months = self::parseField(self::translateNames($monthField, self::MONTH_NAMES), 1, 12);

            $rawDaysOfWeek = self::parseField(self::translateNames($dowField, self::WEEKDAY_NAMES), 0, 7);
            $this->daysOfWeek = array_values(array_unique(array_map(fn($day) => $day === 7 ? 0 : $day, $rawDaysOfWeek)));
            sort($this->daysOfWeek);

            $this->dayOfMonthRestricted = ($domField !== '*');
            $this->dayOfWeekRestricted = ($dowField !== '*');
        }

        /**
         * The original expression string this was constructed with (shorthand not expanded).
         * @return string
         */
        public function getExpression(): string
        {
            return $this->expression;
        }

        /**
         * Does this expression match the given point in time, to the minute?
         * @param DateTimeInterface $dateTime
         * @return bool
         */
        public function isDue(DateTimeInterface $dateTime): bool
        {
            $minute = (int) $dateTime->format('i');
            $hour = (int) $dateTime->format('G');
            $day = (int) $dateTime->format('j');
            $month = (int) $dateTime->format('n');
            $weekday = (int) $dateTime->format('w');

            if (!in_array($minute, $this->minutes, true)) {
                return false;
            }
            if (!in_array($hour, $this->hours, true)) {
                return false;
            }
            if (!in_array($month, $this->months, true)) {
                return false;
            }

            $domMatches = in_array($day, $this->daysOfMonth, true);
            $dowMatches = in_array($weekday, $this->daysOfWeek, true);

            if ($this->dayOfMonthRestricted && $this->dayOfWeekRestricted) {
                return $domMatches || $dowMatches;
            }
            return $domMatches && $dowMatches;
        }

        /**
         * Find the next point in time (minute-resolution, seconds zeroed) at or after $after
         * that this expression matches. Brute-forces minute by minute, bounded by
         * $maxIterations - this is a rarely-called introspection method (not used by
         * Schedule::RunDue(), which only calls isDue()), so simplicity/correctness is
         * favored over a smarter field-skipping search.
         * @param DateTimeInterface|null $after defaults to now
         * @param DateTimeZone|null $timezone defaults to the PHP default timezone
         * @param int $maxIterations default ~2 years of minutes
         * @return DateTimeImmutable
         * @throws ScheduleException if no match is found within $maxIterations minutes -
         *   most likely means the expression can never be satisfied (e.g. "0 0 30 2 *")
         */
        public function nextRunDate(?DateTimeInterface $after = null, ?DateTimeZone $timezone = null, int $maxIterations = 1051920): DateTimeImmutable
        {
            $timezone ??= new DateTimeZone(date_default_timezone_get());

            $base = ($after !== null)
                ? DateTimeImmutable::createFromInterface($after)
                : new DateTimeImmutable('now');

            $candidate = $base->setTimezone($timezone)->modify('+1 minute');
            $candidate = $candidate->setTime((int) $candidate->format('H'), (int) $candidate->format('i'), 0);

            for ($i = 0; $i < $maxIterations; $i++) {
                if ($this->isDue($candidate)) {
                    return $candidate;
                }
                $candidate = $candidate->modify('+1 minute');
            }

            throw ScheduleException::NoUpcomingRunFound($this->expression);
        }

        /**
         * Replace 3-letter names (case-insensitive) with their numeric value in one cron field,
         * leaving everything else (digits, *, ,, -, /) untouched.
         * @param string $field
         * @param array<string, int> $names
         * @return string
         */
        private static function translateNames(string $field, array $names): string
        {
            $upper = strtoupper($field);

            foreach ($names as $name => $value) {
                $upper = str_replace($name, (string) $value, $upper);
            }
            return $upper;
        }

        /**
         * Parse one cron field into the full sorted list of integer values it represents.
         * @param string $field
         * @param int $min
         * @param int $max
         * @return int[]
         * @throws ScheduleException
         */
        private static function parseField(string $field, int $min, int $max): array
        {
            $values = [];
            $segments = explode(',', $field);

            foreach ($segments as $segment) {
                $segment = trim($segment);
                $step = 1;
                $range = $segment;

                if (str_contains($segment, '/')) {
                    $stepParts = explode('/', $segment, 2);
                    $range = $stepParts[0];
                    $step = (int) $stepParts[1];

                    if ($step <= 0) {
                        throw ScheduleException::InvalidCronExpression($segment);
                    }
                }

                if ($range === '*') {
                    $rangeStart = $min;
                    $rangeEnd = $max;
                } else if (str_contains($range, '-')) {
                    $bounds = explode('-', $range, 2);

                    if (!is_numeric($bounds[0]) || !is_numeric($bounds[1])) {
                        throw ScheduleException::InvalidCronExpression($segment);
                    }
                    $rangeStart = (int) $bounds[0];
                    $rangeEnd = (int) $bounds[1];
                } else {
                    if (!is_numeric($range)) {
                        throw ScheduleException::InvalidCronExpression($segment);
                    }
                    $rangeStart = $rangeEnd = (int) $range;
                }

                if (($rangeStart < $min) || ($rangeEnd > $max) || ($rangeStart > $rangeEnd)) {
                    throw ScheduleException::InvalidCronExpression($segment);
                }

                for ($value = $rangeStart; $value <= $rangeEnd; $value += $step) {
                    $values[] = $value;
                }
            }

            $values = array_values(array_unique($values));
            sort($values);
            return $values;
        }
    }
