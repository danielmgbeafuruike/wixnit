<?php

namespace Wixnit\Utilities;

use DateTime as PHPDateTime;
use RuntimeException;
use stdClass;

/**
 * A span of time between two specific moments, stored (via Span) as
 * epoch seconds. Adds calendar-aware operations on top of Span's
 * generic numeric-interval math: splitting into days/weeks/months,
 * counting working days, merging, and Duration-aware shift/expand/shrink.
 */
class Timespan extends Span
{
    public function __construct($start = null, $stop = null, bool $spanLastDay = false)
    {
        $startDateTime = ($start === null) ? new DateTime(0) : new DateTime($start);
        $stopDateTime = ($stop === null) ? new DateTime(time()) : new DateTime($stop);

        $stopEpoch = $spanLastDay
            ? strtotime(date('Y-m-d', $stopDateTime->toEpochSeconds())) + 86400 - 1 // 23:59:59 of that day
            : $stopDateTime->toEpochSeconds();

        parent::__construct($startDateTime->toEpochSeconds(), $stopEpoch);
    }

    // -----------------------------------------------------------------
    // Date/DateTime accessors
    // -----------------------------------------------------------------

    public function getStartDateTime(): DateTime
    {
        return new DateTime((int) $this->getStart());
    }

    public function getStopDateTime(): DateTime
    {
        return new DateTime((int) $this->getStop());
    }

    public function getStartDate(): Date
    {
        return new Date((int) $this->getStart());
    }

    public function getStopDate(): Date
    {
        return new Date((int) $this->getStop());
    }

    // -----------------------------------------------------------------
    // Duration
    // -----------------------------------------------------------------

    public function duration(): Duration
    {
        return new Duration((int) $this->length());
    }

    // -----------------------------------------------------------------
    // Predicates
    // -----------------------------------------------------------------

    /** Does this Timespan contain the given moment? contains(Span) from the parent class still works for span-in-span containment. */
    public function contains(float|int|string|Span|Date|DateTime $value): bool
    {
        if ($value instanceof Date || $value instanceof DateTime) {
            $value = $value->toEpochSeconds();
        } elseif (is_string($value)) {
            $value = (new DateTime($value))->toEpochSeconds();
        }

        return parent::contains($value);
    }

    // intersects()/touches()/union()/intersection() are inherited from
    // Span unchanged — a Timespan IS a Span, so the generic numeric
    // interval logic already does the right thing on epoch seconds.

    // -----------------------------------------------------------------
    // Splitting
    // -----------------------------------------------------------------

    /** Split into segments aligned to calendar-day boundaries (midnight to midnight), with the first/last segments clipped to this span's actual edges. @return static[] */
    public function splitDaily(): array
    {
        $segments = [];
        $cursor = (int) $this->lowerBound();
        $end = (int) $this->upperBound();

        while ($cursor < $end) {
            $nextMidnight = (new Date($cursor))->addDays(1)->toEpochSeconds();
            $segmentEnd = min($nextMidnight, $end);

            $segments[] = new static($cursor, $segmentEnd);
            $cursor = $segmentEnd;
        }

        return $segments;
    }

    /** Split into segments aligned to calendar weeks (Monday to Monday), clipped to this span's actual edges. @return static[] */
    public function splitWeekly(): array
    {
        $segments = [];
        $cursor = (int) $this->lowerBound();
        $end = (int) $this->upperBound();

        while ($cursor < $end) {
            $nextMonday = self::nextMondayAfter($cursor);
            $segmentEnd = min($nextMonday, $end);

            $segments[] = new static($cursor, $segmentEnd);
            $cursor = $segmentEnd;
        }

        return $segments;
    }

    /** Split into segments aligned to calendar months, clipped to this span's actual edges. @return static[] */
    public function splitMonthly(): array
    {
        $segments = [];
        $cursor = (int) $this->lowerBound();
        $end = (int) $this->upperBound();

        while ($cursor < $end) {
            $year = (int) date('Y', $cursor);
            $month = (int) date('m', $cursor);
            $nextMonthStart = mktime(0, 0, 0, $month + 1, 1, $year);
            $segmentEnd = min($nextMonthStart, $end);

            $segments[] = new static($cursor, $segmentEnd);
            $cursor = $segmentEnd;
        }

        return $segments;
    }

    private static function nextMondayAfter(int $epoch): int
    {
        $dayOfWeek = (int) date('N', $epoch); // 1 (Mon) .. 7 (Sun)
        $mondayThisWeekMidnight = strtotime(date('Y-m-d', $epoch - (($dayOfWeek - 1) * 86400)) . ' 00:00:00');

        return $mondayThisWeekMidnight + (7 * 86400);
    }

    // -----------------------------------------------------------------
    // Calendar-day enumeration
    // -----------------------------------------------------------------

    /** Every calendar day in this span that is NOT a Saturday/Sunday. @return Date[] */
    public function workingDays(): array
    {
        return $this->daysMatching(fn (Date $date) => !$date->isWeekend());
    }

    /** Every calendar day in this span that IS a Saturday or Sunday. @return Date[] */
    public function weekends(): array
    {
        return $this->daysMatching(fn (Date $date) => $date->isWeekend());
    }

    /** The count of working days in this span — a shorthand for count($this->workingDays()). */
    public function businessDays(): int
    {
        return count($this->workingDays());
    }

    /** @return Date[] */
    private function daysMatching(callable $predicate): array
    {
        $result = [];
        $cursor = new Date((int) $this->lowerBound());
        $endEpoch = (new Date((int) $this->upperBound()))->toEpochSeconds();

        while ($cursor->toEpochSeconds() <= $endEpoch) {
            if ($predicate($cursor)) {
                $result[] = $cursor;
            }

            $cursor = $cursor->addDays(1);
        }

        return $result;
    }

    // -----------------------------------------------------------------
    // Combining / adjusting
    // -----------------------------------------------------------------

    /**
     * Get a new Timespan combining this and $other into one contiguous span.
     * @throws RuntimeException if they neither overlap nor touch — use union() if you want the enclosing hull regardless.
     */
    public function merge(Timespan $other): static
    {
        if (!$this->intersects($other) && !$this->touches($other)) {
            throw new RuntimeException(
                'Cannot merge two Timespans that neither overlap nor touch — use union() for the enclosing hull instead.'
            );
        }

        return new static(
            min($this->getStart(), $other->getStart()),
            max($this->getStop(), $other->getStop())
        );
    }

    /** The empty gap between this and $other, or null if they overlap or touch (no gap). */
    public function gap(Timespan $other): ?static
    {
        if ($this->intersects($other)) {
            return null;
        }

        return $this->getStop() <= $other->getStart()
            ? new static($this->getStop(), $other->getStart())
            : new static($other->getStop(), $this->getStart());
    }

    /** Get a new Timespan with both start and stop moved by the same offset. */
    public function shift(Duration|int $amount): static
    {
        $seconds = $this->toSecondsValue($amount);

        return new static($this->start + $seconds, $this->stop + $seconds);
    }

    /** Get a new Timespan stretched outward at both ends. Accepts a Duration or a raw second count. */
    public function expand(Duration|int|float $amount, Duration|int|float|null $stopAmount = null): static
    {
        return parent::expand($this->toSecondsValue($amount), $stopAmount === null ? null : $this->toSecondsValue($stopAmount));
    }

    /** Get a new Timespan pulled inward at both ends. Accepts a Duration or a raw second count. */
    public function shrink(Duration|int|float $amount, Duration|int|float|null $stopAmount = null): static
    {
        return parent::shrink($this->toSecondsValue($amount), $stopAmount === null ? null : $this->toSecondsValue($stopAmount));
    }

    private function toSecondsValue(Duration|int|float $value): float
    {
        return $value instanceof Duration ? (float) $value->toSeconds() : (float) $value;
    }

    // -----------------------------------------------------------------
    // Trimming (legacy API, kept and fixed)
    // -----------------------------------------------------------------

    /**
     * get a new Timespan with some time removed from the start
     * @param mixed $time
     * @return static
     */
    public function trimStart($time = ""): static
    {
        $delta = ($time === "") ? 0 : $this->stringToTimeSec($time);

        return new static($this->start + $delta, $this->stop);
    }

    /**
     * get a new Timespan with some time removed from the end
     * @param mixed $time
     * @return static
     */
    public function trimEnd($time = ""): static
    {
        $delta = ($time === "") ? 0 : $this->stringToTimeSec($time);

        return new static($this->start, $this->stop - $delta);
    }

    /**
     * get a new Timespan with some time removed from both start and end
     * @param mixed $time
     * @return static
     */
    public function trimStartEnd($time = ""): static
    {
        return $this->trimStart($time)->trimEnd($time);
    }

    #region private methods

    private function stringToTimeSec(string $time): int
    {
        $val = explode(":", $time);

        if (count($val) == 2) {
            return ((Convert::ToInt($val[0]) * (60 * 60)) + (Convert::ToInt($val[1]) * 60));
        } else {
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
        return new Timespan($span->getStart(), $span->getStop());
    }

    /**
     * create a timespan spanning the full calendar day of a date
     * @param DateTime|Date $date
     * @return Timespan
     */
    public static function FromDate(DateTime|Date $date): Timespan
    {
        return new Timespan($date->toEpochSeconds(), $date->toEpochSeconds() + ((60 * 60) * 24));
    }

    /**
     * create a timespan from a "H:M" string, spanning 00:00 to that time
     * @param string $time
     * @return Timespan
     */
    public static function FromString(string $time): Timespan
    {
        $val = explode(":", $time);

        if (count($val) == 2) {
            return new Timespan(0, ((Convert::ToInt($val[0]) * (60 * 60)) + (Convert::ToInt($val[1]) * 60)));
        } else {
            return new Timespan();
        }
    }

    /**
     * create a timespan from a duration in seconds, spanning 0 to that many seconds
     * @param int $seconds
     * @return Timespan
     */
    public static function FromSeconds(int $seconds): Timespan
    {
        return new Timespan(0, $seconds);
    }

    /**
     * create a timespan spanning the full calendar day containing a PHP DateTime
     * @param PHPDateTime $dateTime
     * @return Timespan
     */
    public static function FromDateTime(PHPDateTime $dateTime): Timespan
    {
        return new Timespan($dateTime->getTimestamp(), $dateTime->getTimestamp() + ((60 * 60) * 24));
    }

    /**
     * create a timespan from an object with ->start and ->stop properties
     * @param stdClass $obj
     * @return Timespan
     */
    public static function FromObject(stdClass $obj): Timespan
    {
        if (isset($obj->start) && isset($obj->stop)) {
            return new Timespan($obj->start, $obj->stop);
        } else {
            return new Timespan();
        }
    }

    /**
     * the current calendar month, from its 1st day to now
     * @return Timespan
     */
    public static function ThisMonth(): Timespan
    {
        $now = time();
        $year = (int) date('Y', $now);
        $month = (int) date('m', $now);
        $start = mktime(0, 0, 0, $month, 1, $year);

        return new Timespan($start, $now);
    }

    /**
     * the previous calendar month, in full (1st day through its last)
     * @return Timespan
     */
    public static function LastMonth(): Timespan
    {
        $now = time();
        $year = (int) date('Y', $now);
        $month = (int) date('m', $now);
        $start = mktime(0, 0, 0, $month - 1, 1, $year);
        $end = mktime(23, 59, 59, $month, 0, $year); // day "0" of this month = last day of previous month

        return new Timespan($start, $end);
    }

    /**
     * a rolling 30-day span ending at $from (or now)
     * @param mixed $from
     * @return Timespan
     */
    public static function MonthSpan($from = null): Timespan
    {
        $f = ($from == null) ? new DateTime(time()) : new DateTime($from);
        $sp = new Timespan(($f->toEpochSeconds() - ((60 * 60) * 24) * 30), $f);

        return $sp;
    }

    /**
     * the current calendar year, from Jan 1st to $from (or now)
     * @param mixed $from
     * @return Timespan
     */
    public static function ThisYear($from = null): Timespan
    {
        $f = ($from === null) ? new DateTime(time()) : new DateTime($from);
        $start = mktime(0, 0, 0, 1, 1, $f->year);

        return new Timespan($start, $f);
    }

    /**
     * the previous calendar year, in full
     * @return Timespan
     */
    public static function LastYear(): Timespan
    {
        $year = ((int) date('Y')) - 1;
        $start = mktime(0, 0, 0, 1, 1, $year);
        $end = mktime(23, 59, 59, 12, 31, $year);

        return new Timespan($start, $end);
    }

    /**
     * the current calendar week (Monday through Sunday)
     * @return Timespan
     */
    public static function ThisWeek(): Timespan
    {
        $now = time();
        $start = self::nextMondayAfter($now) - (7 * 86400);

        return new Timespan($start, $start + (7 * 86400));
    }

    /**
     * the previous calendar week (Monday through Sunday)
     * @return Timespan
     */
    public static function LastWeek(): Timespan
    {
        $now = time();
        $start = self::nextMondayAfter($now) - (14 * 86400);

        return new Timespan($start, $start + (7 * 86400));
    }
    #endregion
}
