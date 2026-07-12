<?php

declare(strict_types=1);

namespace Wixnit\Utilities;

use DateTime;
use DateTimeZone;
use JsonSerializable;
use Stringable;
use Wixnit\Enum\DBFieldType;
use Wixnit\Exception\InvalidTimeOfDayException;
use Wixnit\Interfaces\ISerializable;

/**
 * A wall-clock time with no date — "9:00 AM", "17:30" — the thing you
 * reach for instead of smuggling a fake date into a DateTime just to
 * represent an opening time or a daily reminder slot.
 *
 * Canonical storage is a single integer: seconds since midnight
 * (0-86399), same "one unambiguous canonical form" idea as Money's
 * minor-unit integer. Arithmetic (addSeconds/addMinutes/addHours)
 * wraps around midnight rather than growing unbounded, since a time
 * of day is inherently cyclical — 23:30 + 1 hour is 00:30, not "24:30".
 */
final class Time implements ISerializable, Stringable, JsonSerializable
{
    private const SECONDS_PER_DAY = 86400;

    private int $secondsSinceMidnight = 0;

    public function __construct(mixed $arg = null)
    {
        $this->init($arg);
    }

    /**
     * hydrate the object from parameter (lenient — used for DB/framework hydration)
     * @param mixed $arg
     * @return void
     */
    private function init(mixed $arg): void
    {
        if ($arg === null) {
            return;
        }

        if (is_int($arg)) {
            $this->secondsSinceMidnight = self::wrap($arg);
            return;
        }

        if (is_object($arg) && isset($arg->secondsSinceMidnight)) {
            $this->secondsSinceMidnight = self::wrap((int) $arg->secondsSinceMidnight);
            return;
        }

        if (is_string($arg)) {
            $parsed = self::parseComponents($arg);

            if ($parsed !== null) {
                [$hour, $minute, $second] = $parsed;
                $this->secondsSinceMidnight = $hour * 3600 + $minute * 60 + $second;
            }
            // else: malformed input — leave default (00:00:00) rather than throwing,
            // consistent with this library's "never throw on hydration" convention.
        }
    }

    // -----------------------------------------------------------------
    // Strict construction (validates, for use with fresh application input)
    // -----------------------------------------------------------------

    /**
     * Parse a time string. Accepts 24-hour ("09:00", "09:00:00", "17:30")
     * and 12-hour with AM/PM ("9:00 AM", "5:30:15 PM").
     * @throws InvalidTimeOfDayException if $value can't be parsed.
     */
    public static function fromString(string $value): self
    {
        $parsed = self::parseComponents($value);

        if ($parsed === null) {
            throw new InvalidTimeOfDayException(sprintf('"%s" is not a valid time of day.', $value));
        }

        [$hour, $minute, $second] = $parsed;

        return self::fromHms($hour, $minute, $second);
    }

    /** Same as fromString(), but returns null instead of throwing on invalid input. */
    public static function tryFrom(string $value): ?self
    {
        $parsed = self::parseComponents($value);

        return $parsed === null ? null : self::fromHms(...$parsed);
    }

    public static function fromHms(int $hour, int $minute, int $second = 0): self
    {
        if ($hour < 0 || $hour > 23) {
            throw new InvalidTimeOfDayException(sprintf('Hour must be between 0 and 23, got %d.', $hour));
        }

        if ($minute < 0 || $minute > 59) {
            throw new InvalidTimeOfDayException(sprintf('Minute must be between 0 and 59, got %d.', $minute));
        }

        if ($second < 0 || $second > 59) {
            throw new InvalidTimeOfDayException(sprintf('Second must be between 0 and 59, got %d.', $second));
        }

        $instance = new self();
        $instance->secondsSinceMidnight = $hour * 3600 + $minute * 60 + $second;

        return $instance;
    }

    public static function fromSecondsSinceMidnight(int $seconds): self
    {
        if ($seconds < 0 || $seconds >= self::SECONDS_PER_DAY) {
            throw new InvalidTimeOfDayException(sprintf(
                'Seconds since midnight must be between 0 and %d, got %d.',
                self::SECONDS_PER_DAY - 1,
                $seconds
            ));
        }

        $instance = new self();
        $instance->secondsSinceMidnight = $seconds;

        return $instance;
    }

    public static function midnight(): self
    {
        return self::fromSecondsSinceMidnight(0);
    }

    public static function noon(): self
    {
        return self::fromHms(12, 0, 0);
    }

    /** The current wall-clock time. Pass a DateTimeZone to get it in a specific timezone. */
    public static function now(?DateTimeZone $timezone = null): self
    {
        $now = $timezone !== null ? new DateTime('now', $timezone) : new DateTime('now');

        return self::fromHms((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
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

    // -----------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------

    public function getHour(): int
    {
        return intdiv($this->secondsSinceMidnight, 3600);
    }

    public function getMinute(): int
    {
        return intdiv($this->secondsSinceMidnight % 3600, 60);
    }

    public function getSecond(): int
    {
        return $this->secondsSinceMidnight % 60;
    }

    public function toSecondsSinceMidnight(): int
    {
        return $this->secondsSinceMidnight;
    }

    // -----------------------------------------------------------------
    // Arithmetic (immutable, wraps around midnight)
    // -----------------------------------------------------------------

    public function addSeconds(int $seconds): self
    {
        return self::fromSecondsSinceMidnight(self::wrap($this->secondsSinceMidnight + $seconds));
    }

    public function addMinutes(int $minutes): self
    {
        return $this->addSeconds($minutes * 60);
    }

    public function addHours(int $hours): self
    {
        return $this->addSeconds($hours * 3600);
    }

    /** Seconds from this time to $other, wrapping forward (always 0-86399), e.g. 23:00 -> 01:00 is 7200 seconds, not negative. */
    public function secondsUntil(Time $other): int
    {
        return self::wrap($other->secondsSinceMidnight - $this->secondsSinceMidnight);
    }

    private static function wrap(int $seconds): int
    {
        return (($seconds % self::SECONDS_PER_DAY) + self::SECONDS_PER_DAY) % self::SECONDS_PER_DAY;
    }

    // -----------------------------------------------------------------
    // Comparison
    // -----------------------------------------------------------------

    public function equals(Time $other): bool
    {
        return $this->secondsSinceMidnight === $other->secondsSinceMidnight;
    }

    public function compareTo(Time $other): int
    {
        return $this->secondsSinceMidnight <=> $other->secondsSinceMidnight;
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
     * Is this time within [$start, $end]? Handles overnight ranges where
     * $start > $end (e.g. isBetween(22:00, 06:00) matches 23:30 and 02:00).
     */
    public function isBetween(Time $start, Time $end): bool
    {
        if ($start->compareTo($end) <= 0) {
            return $this->compareTo($start) >= 0 && $this->compareTo($end) <= 0;
        }

        return $this->compareTo($start) >= 0 || $this->compareTo($end) <= 0;
    }

    // -----------------------------------------------------------------
    // Formatting
    // -----------------------------------------------------------------

    /** "09:05:00", zero-padded 24-hour with seconds. */
    public function __toString(): string
    {
        return sprintf('%02d:%02d:%02d', $this->getHour(), $this->getMinute(), $this->getSecond());
    }

    /** "9:05 AM" — a friendlier display form. */
    public function toDisplayString(): string
    {
        $hour12 = $this->getHour() % 12;
        $hour12 = $hour12 === 0 ? 12 : $hour12;
        $suffix = $this->getHour() < 12 ? 'AM' : 'PM';

        return sprintf('%d:%02d %s', $hour12, $this->getMinute(), $suffix);
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    // -----------------------------------------------------------------
    // ISerializable
    // -----------------------------------------------------------------

    /**
     * get db field type for creating the appropriate db field type for saving the class to db
     * @return DBFieldType
     *
     * Uses VARCHAR to stay consistent with the rest of this library.
     * Swap to DBFieldType::TIME here if your enum defines a native
     * TIME case and you'd rather use that column type.
     */
    public function _dbType(): DBFieldType
    {
        return DBFieldType::VARCHAR;
    }

    /**
     * prepare the object for saving to db
     * @return string
     */
    public function _serialize(): string
    {
        return (string) $this;
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
}
