<?php

namespace Wixnit\Utilities;

use Stringable;
use Wixnit\Enum\DBFieldType;
use Wixnit\Interfaces\ISerializable;

/**
 * A length of time, independent of any specific calendar date — "3
 * hours", "90 minutes", "2 weeks". If you need a specific point in
 * time, see DateTime; for a specific calendar date, see Date; for a
 * time-of-day with no date, see Time.
 *
 * Internally this is a single integer: total seconds. Every unit
 * (minutes, hours, days, ...) is computed from that on demand rather
 * than stored — so there's exactly one source of truth and no risk of
 * the parts disagreeing with the whole.
 */
final class Duration implements ISerializable, Stringable
{
    private const MINUTE = 60;
    private const HOUR = 60 * 60;
    private const DAY = 60 * 60 * 24;
    private const WEEK = self::DAY * 7;
    private const MONTH = self::DAY * 30;       // approximate — a calendar has no fixed month length
    private const YEAR = self::DAY * 365.25;    // approximate, leap-year-adjusted

    private int $seconds = 0;

    public function __construct($duration_in_seconds = null)
    {
        $this->init($duration_in_seconds);
    }

    /**
     * hydrate the duration object
     * @param mixed $duration_in_seconds
     * @return void
     */
    private function init($duration_in_seconds = null): void
    {
        $this->seconds = $duration_in_seconds === null ? 0 : Convert::ToInt($duration_in_seconds);
    }

    // -----------------------------------------------------------------
    // Accessors (all computed from the single $seconds value)
    // -----------------------------------------------------------------

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    public function getMinutes(): float
    {
        return $this->seconds / self::MINUTE;
    }

    public function getHours(): float
    {
        return $this->seconds / self::HOUR;
    }

    public function getDays(): float
    {
        return $this->seconds / self::DAY;
    }

    public function getWeeks(): float
    {
        return $this->seconds / self::WEEK;
    }

    /** Approximate — treats a month as 30 days, since months don't have a fixed length outside a specific calendar. */
    public function getMonths(): float
    {
        return $this->seconds / self::MONTH;
    }

    /** Approximate — treats a year as 365.25 days (leap-year-adjusted). */
    public function getYears(): float
    {
        return $this->seconds / self::YEAR;
    }

    // Whole-unit ("floor of the absolute value, sign preserved") variants.
    public function inWholeMinutes(): int
    {
        return $this->wholeUnitsOf(self::MINUTE);
    }

    public function inWholeHours(): int
    {
        return $this->wholeUnitsOf(self::HOUR);
    }

    public function inWholeDays(): int
    {
        return $this->wholeUnitsOf(self::DAY);
    }

    public function inWholeWeeks(): int
    {
        return $this->wholeUnitsOf(self::WEEK);
    }

    private function wholeUnitsOf(float $unitSeconds): int
    {
        $sign = $this->seconds < 0 ? -1 : 1;

        return $sign * intdiv((int) abs($this->seconds), (int) $unitSeconds);
    }

    /**
     * Break this duration down into whole years/months/weeks/days/
     * hours/minutes/seconds, each unit's remainder cascading into the
     * next — for "3 days, 4 hours, 12 minutes" style displays. Always
     * non-negative; check isNegative() separately if the sign matters.
     * @return array{years:int,months:int,weeks:int,days:int,hours:int,minutes:int,seconds:int}
     */
    public function toParts(): array
    {
        $remainder = (int) abs($this->seconds);

        $years = intdiv($remainder, (int) self::YEAR);
        $remainder %= (int) self::YEAR;

        $months = intdiv($remainder, self::MONTH);
        $remainder %= self::MONTH;

        $weeks = intdiv($remainder, self::WEEK);
        $remainder %= self::WEEK;

        $days = intdiv($remainder, self::DAY);
        $remainder %= self::DAY;

        $hours = intdiv($remainder, self::HOUR);
        $remainder %= self::HOUR;

        $minutes = intdiv($remainder, self::MINUTE);
        $remainder %= self::MINUTE;

        return [
            'years' => $years,
            'months' => $months,
            'weeks' => $weeks,
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $remainder,
        ];
    }

    // -----------------------------------------------------------------
    // Arithmetic (returns new Duration instances — Duration is a pure value)
    // -----------------------------------------------------------------

    public function add(Duration $other): self
    {
        return new self($this->seconds + $other->seconds);
    }

    public function subtract(Duration $other): self
    {
        return new self($this->seconds - $other->seconds);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->seconds * $factor));
    }

    public function absolute(): self
    {
        return new self(abs($this->seconds));
    }

    public function negate(): self
    {
        return new self(-$this->seconds);
    }

    public function isNegative(): bool
    {
        return $this->seconds < 0;
    }

    public function isZero(): bool
    {
        return $this->seconds === 0;
    }

    // -----------------------------------------------------------------
    // Comparison
    // -----------------------------------------------------------------

    public function equals(Duration $other): bool
    {
        return $this->seconds === $other->seconds;
    }

    public function compareTo(Duration $other): int
    {
        return $this->seconds <=> $other->seconds;
    }

    public function greaterThan(Duration $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function lessThan(Duration $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    // -----------------------------------------------------------------
    // Conversions (kept for backward compatibility with the previous API)
    // -----------------------------------------------------------------

    public function toSeconds(): int
    {
        return $this->seconds;
    }

    public function toMinutes(): float
    {
        return $this->getMinutes();
    }

    /** @deprecated Typo alias for toMinutes(), kept for backward compatibility. */
    public function toMinuites(): float
    {
        return $this->toMinutes();
    }

    public function toHours(): float
    {
        return $this->getHours();
    }

    public function toDays(): float
    {
        return $this->getDays();
    }

    // -----------------------------------------------------------------
    // Display
    // -----------------------------------------------------------------

    /** e.g. "3d 4h 12m" — the largest two non-zero units, for compact display. Use toParts() directly for full control. */
    public function __toString(): string
    {
        if ($this->isZero()) {
            return '0s';
        }

        $labels = ['years' => 'y', 'months' => 'mo', 'weeks' => 'w', 'days' => 'd', 'hours' => 'h', 'minutes' => 'm', 'seconds' => 's'];
        $parts = $this->toParts();
        $rendered = [];

        foreach ($labels as $unit => $label) {
            if ($parts[$unit] > 0) {
                $rendered[] = $parts[$unit] . $label;
            }
            if (count($rendered) === 2) {
                break;
            }
        }

        $prefix = $this->isNegative() ? '-' : '';

        return $prefix . implode(' ', $rendered ?: ['0s']);
    }

    // -----------------------------------------------------------------
    // Static factories (each converts a single unit's worth into total seconds)
    // -----------------------------------------------------------------

    public static function Seconds(int $seconds): Duration
    {
        return new Duration($seconds);
    }

    public static function Minutes(float $minutes): Duration
    {
        return new Duration((int) round($minutes * self::MINUTE));
    }

    public static function Hours(float $hours): Duration
    {
        return new Duration((int) round($hours * self::HOUR));
    }

    public static function Days(float $days): Duration
    {
        return new Duration((int) round($days * self::DAY));
    }

    public static function Weeks(float $weeks): Duration
    {
        return new Duration((int) round($weeks * self::WEEK));
    }

    /** Approximate — treats a month as 30 days. */
    public static function Months(float $months): Duration
    {
        return new Duration((int) round($months * self::MONTH));
    }

    /** Approximate — treats a year as 365.25 days. */
    public static function Years(float $years): Duration
    {
        return new Duration((int) round($years * self::YEAR));
    }

    public static function Zero(): Duration
    {
        return new Duration(0);
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
