<?php

namespace Wixnit\Utilities;

use InvalidArgumentException;

/**
 * A generic one-dimensional numeric interval [start, stop]. Works with
 * any float-comparable range — coordinates, scores, prices, or (via
 * the Timespan subclass) epoch-second time ranges.
 *
 * Not assumed to be ordered: start can be greater than stop (a
 * "reversed" span). Most methods handle this transparently by
 * comparing the actual lower/upper bounds rather than assuming
 * start <= stop; normalize()/reverse() let you fix or use the
 * direction intentionally.
 */
class Span
{
    public float $start = 0;
    public float $stop = 0;

    public function __construct($start = null, $stop = null)
    {
        if ($start !== null) {
            $this->start = (float) $start;
        }
        if ($stop !== null) {
            $this->stop = (float) $stop;
        }
    }

    public function getStart(): float
    {
        return $this->start;
    }

    public function getStop(): float
    {
        return $this->stop;
    }

    // -----------------------------------------------------------------
    // Measurement
    // -----------------------------------------------------------------

    /** The absolute size of the span, regardless of start/stop order. */
    public function length(): float
    {
        return abs($this->stop - $this->start);
    }

    /** @deprecated Alias for length(), kept for backward compatibility. */
    public function difference(): float
    {
        return $this->length();
    }

    protected function lowerBound(): float
    {
        return min($this->start, $this->stop);
    }

    protected function upperBound(): float
    {
        return max($this->start, $this->stop);
    }

    // -----------------------------------------------------------------
    // Predicates
    // -----------------------------------------------------------------

    /** Does this span contain the given value, or fully contain the given span? */
    public function contains(float|int|Span $value): bool
    {
        if ($value instanceof Span) {
            return $this->lowerBound() <= $value->lowerBound() && $this->upperBound() >= $value->upperBound();
        }

        return $value >= $this->lowerBound() && $value <= $this->upperBound();
    }

    /** Do this span and $other share at least one point? */
    public function intersects(Span $other): bool
    {
        return $this->lowerBound() <= $other->upperBound() && $other->lowerBound() <= $this->upperBound();
    }

    /** Are this span and $other adjacent — meeting exactly at a shared boundary, touching or overlapping? */
    public function touches(Span $other): bool
    {
        return $this->upperBound() === $other->lowerBound()
            || $other->upperBound() === $this->lowerBound()
            || $this->intersects($other);
    }

    // -----------------------------------------------------------------
    // Combining
    // -----------------------------------------------------------------

    /**
     * The smallest span containing both this and $other. This is only
     * a true set union when the two spans overlap or touch — for
     * disjoint spans, the gap between them gets included too, since a
     * single Span can't represent two disconnected ranges.
     */
    public function union(Span $other): static
    {
        return new static(
            min($this->lowerBound(), $other->lowerBound()),
            max($this->upperBound(), $other->upperBound())
        );
    }

    /** The overlapping region between this and $other, or null if they don't intersect. */
    public function intersection(Span $other): ?static
    {
        if (!$this->intersects($other)) {
            return null;
        }

        return new static(
            max($this->lowerBound(), $other->lowerBound()),
            min($this->upperBound(), $other->upperBound())
        );
    }

    // -----------------------------------------------------------------
    // Transformation (returns a new Span — this class is immutable)
    // -----------------------------------------------------------------

    /** Get a new Span with start <= stop, swapped if this span is reversed. Returns $this unchanged if already normalized (safe — nothing mutates it). */
    public function normalize(): static
    {
        return $this->start > $this->stop ? $this->reverse() : $this;
    }

    /** Get a new Span with start and stop swapped. */
    public function reverse(): static
    {
        return new static($this->stop, $this->start);
    }

    /** Get a new Span stretched outward by $amount at both ends (or independently, $amount at the start and $stopAmount at the stop). */
    public function expand(float $amount, ?float $stopAmount = null): static
    {
        $stopAmount ??= $amount;
        $direction = $this->stop >= $this->start ? 1 : -1;

        return new static(
            $this->start - $amount * $direction,
            $this->stop + $stopAmount * $direction
        );
    }

    /** Get a new Span pulled inward by $amount at both ends, clamped so it never inverts — it collapses to its midpoint instead of flipping direction. */
    public function shrink(float $amount, ?float $stopAmount = null): static
    {
        $stopAmount ??= $amount;
        $direction = $this->stop >= $this->start ? 1 : -1;
        $mid = $this->start + (($this->stop - $this->start) / 2);

        $newStart = $this->start + $amount * $direction;
        $newStop = $this->stop - $stopAmount * $direction;

        if (($newStop - $newStart) * $direction < 0) {
            $newStart = $newStop = $mid;
        }

        return new static($newStart, $newStop);
    }

    // -----------------------------------------------------------------
    // Splitting
    // -----------------------------------------------------------------

    /** Divide this span into $parts equal-sized, contiguous Spans covering the same range (in the same direction as this span). @return static[] */
    public function split(int $parts): array
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('Cannot split a Span into fewer than 1 part.');
        }

        $step = ($this->stop - $this->start) / $parts;
        $result = [];
        $cursor = $this->start;

        for ($i = 0; $i < $parts; $i++) {
            $next = ($i === $parts - 1) ? $this->stop : $cursor + $step;
            $result[] = new static($cursor, $next);
            $cursor = $next;
        }

        return $result;
    }

    /** @deprecated Alias for split(); the old $intersected parameter was unused and has been dropped. @return static[] */
    public function splitSpan(int $places, bool $intersected = false): array
    {
        return $this->split($places);
    }
}
