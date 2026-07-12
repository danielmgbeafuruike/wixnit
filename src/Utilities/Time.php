<?php

namespace Wixnit\Utilities;

use Stringable;
use Wixnit\Enum\DBFieldType;
use Wixnit\Interfaces\ISerializable;

/**
 * A wall-clock time of day, with no date component — "09:00", "5:30 PM".
 * Pairs with Date (via Date::at()) to build a full DateTime, and with
 * DateTime (via DateTime::toTime()) to pull the time-of-day back out.
 *
 * Internally a single integer: seconds since midnight (0-86399).
 * add()/subtract() wrap around midnight, since a clock face is
 * cyclical — 23:30 plus 1 hour is 00:30, not "24:30".
 */
final class Time implements ISerializable, Stringable
{
    private const SECONDS_PER_DAY = 86400;

    private int $seconds = 0;

    public function __construct($arg = null)
    {
        $this->init($arg);
    }

    /**
     * hydrate the time object
     * @param mixed $arg
     * @return void
     */
    private function init($arg = null): void
    {
        if ($arg instanceof Time) {
            $this->seconds = $arg->seconds;
        } elseif ($arg instanceof DateTime) {
            $this->seconds = $arg->toEpochSeconds() % self::SECONDS_PER_DAY;
        } elseif ($arg instanceof Date) {
            // A Date has no time-of-day component by definition — always midnight.
            $this->seconds = 0;
        } elseif (is_int($arg)) {
            $this->seconds = self::wrap($arg);
        } elseif (is_string($arg)) {
            $parsed = self::parseComponents($arg);

            if ($parsed !== null) {
                [$hour, $minute, $second] = $parsed;
                $this->seconds = $hour * 3600 + $minute * 60 + $second;
            }
            // else: malformed input — leave default (00:00:00) rather than
            // throwing, consistent with the rest of this library's
            // lenient hydration convention.
        }
    }

    private static function parseComponents(string $value): ?array
    {
        $value = trim($value);

        // 24-hour: "H:i:s" or "H:i"
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m)) {
            return [(int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0];
        }

        // 12-hour with AM/PM: "g:i A" or "g:i:s A"
        if (preg_match('/^(0?[1-9]|1[0-2]):([0-5]\d)(?::([0-5]\d))?\s*([AaPp][Mm])$/', $value, $m)) {
            $hour = (int) $m[1] % 12;

            if (strtoupper($m[4]) === 'PM') {
                $hour += 12;
            }

            return [$hour, (int) $m[2], isset($m[3]) ? (int) $m[3] : 0];
        }

        return null;
    }

    private static function wrap(int $seconds): int
    {
        return (($seconds % self::SECONDS_PER_DAY) + self::SECONDS_PER_DAY) % self::SECONDS_PER_DAY;
    }

    // -----------------------------------------------------------------
    // Static factories
    // -----------------------------------------------------------------

    public static function Now(): Time
    {
        return new Time(new DateTime(time()));
    }

    public static function Midnight(): Time
    {
        return new Time(0);
    }

    public static function Noon(): Time
    {
        return new Time(12 * 3600);
    }

    // -----------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------

    public function getHours(): int
    {
        return intdiv($this->seconds, 3600);
    }

    public function getMinutes(): int
    {
        return intdiv($this->seconds % 3600, 60);
    }

    public function getSeconds(): int
    {
        return $this->seconds % 60;
    }

    // -----------------------------------------------------------------
    // Conversions
    // -----------------------------------------------------------------

    public function toSeconds(): int
    {
        return $this->seconds;
    }

    public function toMilliseconds(): int
    {
        return $this->seconds * 1000;
    }

    // -----------------------------------------------------------------
    // Arithmetic
    // -----------------------------------------------------------------

    /** Get a new Time with a Duration (or a raw number of seconds) added, wrapping around midnight. */
    public function add(Duration|int $amount): static
    {
        $delta = $amount instanceof Duration ? $amount->toSeconds() : $amount;

        return new static($this->seconds + $delta);
    }

    /** Get a new Time with a Duration (or a raw number of seconds) subtracted, wrapping around midnight. */
    public function subtract(Duration|int $amount): static
    {
        $delta = $amount instanceof Duration ? $amount->toSeconds() : $amount;

        return new static($this->seconds - $delta);
    }

    /**
     * The absolute difference between this time and $other, as a
     * Duration. This is simple clock-face distance (|a - b| in
     * seconds) — it does NOT account for which one is "earlier in the
     * day" by wrapping through midnight, since a bare Time doesn't
     * know what day it's on. For that, combine with a Date via
     * Date::at() to get a DateTime and use DateTime::diff() instead.
     */
    public function difference(Time $other): Duration
    {
        return new Duration(abs($this->seconds - $other->seconds));
    }

    // -----------------------------------------------------------------
    // Comparison
    // -----------------------------------------------------------------

    public function equals(Time $other): bool
    {
        return $this->seconds === $other->seconds;
    }

    public function compareTo(Time $other): int
    {
        return $this->seconds <=> $other->seconds;
    }

    public function isBefore(Time $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isAfter(Time $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /**
     * Is this time within [$start, $end]? Handles overnight ranges
     * where $start > $end (e.g. isBetween(22:00, 06:00) matches 23:30
     * and 02:00).
     */
    public function isBetween(Time $start, Time $end): bool
    {
        if ($start->compareTo($end) <= 0) {
            return $this->compareTo($start) >= 0 && $this->compareTo($end) <= 0;
        }

        return $this->compareTo($start) >= 0 || $this->compareTo($end) <= 0;
    }

    // -----------------------------------------------------------------
    // Part of day
    // -----------------------------------------------------------------

    /** 05:00–11:59 */
    public function isMorning(): bool
    {
        return $this->getHours() >= 5 && $this->getHours() < 12;
    }

    /** 12:00–16:59 */
    public function isAfternoon(): bool
    {
        return $this->getHours() >= 12 && $this->getHours() < 17;
    }

    /** 17:00–20:59 */
    public function isEvening(): bool
    {
        return $this->getHours() >= 17 && $this->getHours() < 21;
    }

    // -----------------------------------------------------------------
    // Formatting
    // -----------------------------------------------------------------

    public function format(string $format = 'H:i:s'): string
    {
        return date($format, $this->seconds);
    }

    /** "09:05:00", zero-padded 24-hour with seconds. */
    public function __toString(): string
    {
        return sprintf('%02d:%02d:%02d', $this->getHours(), $this->getMinutes(), $this->getSeconds());
    }

    // -----------------------------------------------------------------
    // ISerializable
    // -----------------------------------------------------------------

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
        return $this->seconds;
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
}
