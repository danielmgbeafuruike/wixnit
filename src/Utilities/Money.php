<?php

    namespace Wixnit\Utilities;

    use JsonSerializable;
    use Stringable;
    use Wixnit\Enum\DBFieldType;
    use Wixnit\Exception\DivisionByZeroException;
    use Wixnit\Exception\InvalidAmountException;
    use Wixnit\Exception\ScaleMismatchException;
    use Wixnit\Interfaces\ISerializable;

    /**
     * An immutable money value object.
     *
     * Internally the amount is always stored as an INTEGER in the currency's
     * smallest unit ("minor units" — cents for USD, fils for BHD, etc). This
     * is what you save to the database (a plain BIGINT column) and it avoids
     * all floating point rounding bugs in arithmetic.
     *
     *   - Database:      store getAmount() (int) + getCurrency()->getCode()
     *   - json_encode():  outputs the float value automatically (see jsonSerialize)
     *   - (string) cast:  outputs a human readable "amount CODE" string
     *
     * Every operation returns a NEW Money instance; nothing is ever mutated.
     */
    class Money implements ISerializable, JsonSerializable, Stringable
    {
        public const DEFAULT_SCALE = 2;

        private int $amount;
        private int $scale;

        public function __construct(float $amount = 0, int $scale = self::DEFAULT_SCALE)
        {
            if ($scale < 0) {
                throw new InvalidAmountException('Scale cannot be negative.');
            }

            $this->amount = round($amount * self::subunitFactor($scale));
            $this->scale = $scale;
        }

        // -----------------------------------------------------------------
        // Factories
        // -----------------------------------------------------------------

        /** Build from an integer already expressed in minor units (e.g. cents for scale 2). This is the canonical, safest constructor */
        public static function fromMinorUnits(int $amount, int $scale = self::DEFAULT_SCALE): self
        {
            return new self($amount, $scale);
        }

        /**
         * Build from a float major-unit amount, e.g. 19.99 at scale 2.
         * Floats are inherently imprecise, so this is convenient for user
         * input but fromString()/fromMinorUnits() are safer for exact values.
         */
        public static function fromFloat(float $amount, int $scale = self::DEFAULT_SCALE): self
        {
            $minor = (int) round($amount * self::subunitFactor($scale));

            return new self($minor, $scale);
        }

        /**
         * Build from a decimal string, e.g. "19.99" or "-4.5".
         * Uses bcmath when available for exact precision, avoiding the
         * float rounding errors that fromFloat() can introduce.
         */
        public static function fromString(string $amount, int $scale = self::DEFAULT_SCALE): self
        {
            $amount = trim($amount);

            if ($amount === '' || !preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
                throw new InvalidAmountException(sprintf('"%s" is not a valid decimal amount.', $amount));
            }

            $factor = self::subunitFactor($scale);

            if (self::hasBcmath()) {
                $scaled = bcmul($amount, (string) $factor, 0);
                return new self((int) $scaled, $scale);
            }

            return new self((int) round((float) $amount * $factor), $scale);
        }

        /** A zero-value Money at the given scale. Handy as a starting accumulator. */
        public static function zero(int $scale = self::DEFAULT_SCALE): self
        {
            return new self(0, $scale);
        }

        // -----------------------------------------------------------------
        // Accessors
        // -----------------------------------------------------------------

        /** The raw integer amount in minor units — this is what you persist to the database. */
        public function getAmount(): int
        {
            return $this->amount;
        }

        /** The number of decimal places this amount is scaled to. */
        public function getScale(): int
        {
            return $this->scale;
        }

        /** The amount as a float in major units, e.g. 1999 at scale 2 -> 19.99. */
        public function toFloat(): float
        {
            return $this->amount / self::subunitFactor($this->scale);
        }

        /** The amount as an exact decimal string in major units, e.g. "19.99". Preferred over toFloat() when exactness matters (e.g. re-hydrating). */
        public function toDecimalString(): string
        {
            if ($this->scale === 0) {
                return (string) $this->amount;
            }

            $negative = $this->amount < 0;
            $absolute = (string) abs($this->amount);
            $absolute = str_pad($absolute, $this->scale + 1, '0', STR_PAD_LEFT);

            $wholePart = substr($absolute, 0, -$this->scale);
            $fractionPart = substr($absolute, -$this->scale);

            return ($negative ? '-' : '') . $wholePart . '.' . $fractionPart;
        }

        // -----------------------------------------------------------------
        // Scale conversion
        // -----------------------------------------------------------------

        /** Return an equivalent Money re-expressed at a different scale, e.g. converting 19.99 (scale 2) to scale 3 gives 19.990. Rounds if converting to a smaller scale. */
        public function withScale(int $scale, int $roundingMode = PHP_ROUND_HALF_UP): self
        {
            if ($scale === $this->scale) {
                return $this;
            }

            if ($scale > $this->scale) {
                $factor = self::subunitFactor($scale - $this->scale);
                return new self($this->amount * $factor, $scale);
            }

            $factor = self::subunitFactor($this->scale - $scale);
            $rounded = (int) round($this->amount / $factor, 0, $roundingMode);

            return new self($rounded, $scale);
        }

        // -----------------------------------------------------------------
        // Arithmetic (all immutable — return a new Money)
        // -----------------------------------------------------------------

        public function add(Money $other): self
        {
            $this->assertSameScale($other, 'add');

            return new self($this->amount + $other->amount, $this->scale);
        }

        public function subtract(Money $other): self
        {
            $this->assertSameScale($other, 'subtract');

            return new self($this->amount - $other->amount, $this->scale);
        }

        /**
         * Multiply by a scalar factor (int, float, or numeric string).
         * @param int $roundingMode One of PHP's PHP_ROUND_* constants.
         */
        public function multiply(int|float|string $factor, int $roundingMode = PHP_ROUND_HALF_UP): self
        {
            if (self::hasBcmath()) {
                $result = bcmul((string) $this->amount, (string) $factor, 8);
                $rounded = (int) round((float) $result, 0, $roundingMode);

                return new self($rounded, $this->scale);
            }

            $rounded = (int) round($this->amount * (float) $factor, 0, $roundingMode);

            return new self($rounded, $this->scale);
        }

        /**
         * Divide by a scalar divisor (int, float, or numeric string).
         * @param int $roundingMode One of PHP's PHP_ROUND_* constants.
         */
        public function divide(int|float|string $divisor, int $roundingMode = PHP_ROUND_HALF_UP): self
        {
            if ((float) $divisor === 0.0) {
                throw new DivisionByZeroException('Cannot divide Money by zero.');
            }

            $rounded = (int) round($this->amount / (float) $divisor, 0, $roundingMode);

            return new self($rounded, $this->scale);
        }

        /** Return this amount minus/plus a percentage, e.g. percentage(10) returns 10% of the amount. */
        public function percentage(float $percent): self
        {
            return $this->multiply($percent / 100);
        }

        public function absolute(): self
        {
            return new self(abs($this->amount), $this->scale);
        }

        public function negate(): self
        {
            return new self(-$this->amount, $this->scale);
        }

        /**
         * Split this amount across the given ratios without losing or
         * gaining a single minor unit anywhere (the classic "allocate"
         * algorithm — e.g. splitting 100.00 three ways gives 33.34/33.33/33.33).
         *
         * @param  int[]|float[] $ratios e.g. [1, 1, 1] for an even 3-way split, or [50, 50] / [70, 30] for weighted splits.
         * @return self[]
         */
        public function allocate(array $ratios): array
        {
            if (empty($ratios)) {
                throw new InvalidAmountException('Ratios array cannot be empty.');
            }

            foreach ($ratios as $ratio) {
                if ($ratio < 0) {
                    throw new InvalidAmountException('Allocation ratios cannot be negative.');
                }
            }

            $total = array_sum($ratios);

            if ($total <= 0) {
                throw new DivisionByZeroException('Sum of allocation ratios must be greater than zero.');
            }

            $remainder = $this->amount;
            $shares = [];

            foreach ($ratios as $ratio) {
                $share = (int) floor(abs($this->amount) * $ratio / $total);
                $share = $this->amount < 0 ? -$share : $share;
                $shares[] = $share;
                $remainder -= $share;
            }

            $direction = $this->amount >= 0 ? 1 : -1;
            $i = 0;
            $count = count($shares);

            while ($remainder !== 0) {
                $shares[$i % $count] += $direction;
                $remainder -= $direction;
                $i++;
            }

            return array_map(fn (int $amount) => new self($amount, $this->scale), $shares);
        }

        /** Split this amount into $n equal-as-possible parts. Convenience wrapper around allocate(). */
        public function split(int $n): array
        {
            if ($n < 1) {
                throw new InvalidAmountException('Cannot split Money into fewer than 1 part.');
            }

            return $this->allocate(array_fill(0, $n, 1));
        }

        // -----------------------------------------------------------------
        // Comparisons
        // -----------------------------------------------------------------

        public function equals(Money $other): bool
        {
            return $this->scale === $other->scale && $this->amount === $other->amount;
        }

        public function compareTo(Money $other): int
        {
            $this->assertSameScale($other, 'compare');

            return $this->amount <=> $other->amount;
        }

        public function greaterThan(Money $other): bool
        {
            return $this->compareTo($other) > 0;
        }

        public function greaterThanOrEqual(Money $other): bool
        {
            return $this->compareTo($other) >= 0;
        }

        public function lessThan(Money $other): bool
        {
            return $this->compareTo($other) < 0;
        }

        public function lessThanOrEqual(Money $other): bool
        {
            return $this->compareTo($other) <= 0;
        }

        public function isZero(): bool
        {
            return $this->amount === 0;
        }

        public function isPositive(): bool
        {
            return $this->amount > 0;
        }

        public function isNegative(): bool
        {
            return $this->amount < 0;
        }

        public function isSameScale(Money $other): bool
        {
            return $this->scale === $other->scale;
        }

        // -----------------------------------------------------------------
        // Static helpers over collections of Money
        // -----------------------------------------------------------------

        /** @param self[] $monies */
        public static function sum(array $monies): self
        {
            if (empty($monies)) {
                throw new InvalidAmountException('Cannot sum an empty array of Money.');
            }

            $first = array_shift($monies);

            return array_reduce($monies, fn (self $carry, self $money) => $carry->add($money), $first);
        }

        /** @param self[] $monies */
        public static function min(array $monies): self
        {
            return array_reduce(
                $monies,
                fn (?self $carry, self $money) => $carry === null || $money->lessThan($carry) ? $money : $carry
            );
        }

        /** @param self[] $monies */
        public static function max(array $monies): self
        {
            return array_reduce(
                $monies,
                fn (?self $carry, self $money) => $carry === null || $money->greaterThan($carry) ? $money : $carry
            );
        }

        // -----------------------------------------------------------------
        // Formatting & serialization
        // -----------------------------------------------------------------

        /**
         * Locale-aware plain decimal display string, e.g. "19.99" or "19,99"
         * depending on locale. Requires the intl extension; falls back to
         * number_format() if it isn't available. Pass a $prefix/$suffix
         * yourself (e.g. a currency symbol) if you need one — this class
         * has no notion of currency.
         */
        public function format(?string $locale = null): string
        {
            if (class_exists(\NumberFormatter::class)) {
                $formatter = new \NumberFormatter($locale ?? 'en_US', \NumberFormatter::DECIMAL);
                $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $this->scale);
                $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $this->scale);

                return $formatter->format($this->toFloat());
            }

            return number_format($this->toFloat(), $this->scale);
        }

        /**
         * Full structured representation — amount as both integer (minor
         * units, for storage) and float (for display or APIs), plus the
         * scale. Use this when you need everything at once.
         */
        public function toArray(): array
        {
            return [
                'amount_minor' => $this->amount,
                'amount' => $this->toFloat(),
                'scale' => $this->scale,
            ];
        }

        /** Rehydrate a Money previously produced by toArray(). */
        public static function fromArray(array $data): self
        {
            $scale = isset($data['scale']) ? (int) $data['scale'] : self::DEFAULT_SCALE;

            if (array_key_exists('amount_minor', $data)) {
                return self::fromMinorUnits((int) $data['amount_minor'], $scale);
            }

            if (array_key_exists('amount', $data)) {
                return self::fromFloat((float) $data['amount'], $scale);
            }

            throw new InvalidAmountException('Array must contain "amount_minor" or "amount".');
        }

        /**
         * json_encode(Money) output. Per this class's design goal, this
         * serializes to the plain FLOAT value — e.g. json_encode($money)
         * === "19.99" — not to an object. If you need the scale in the
         * JSON too, encode toArray() instead: json_encode($money->toArray())
         */
        public function jsonSerialize(): float
        {
            return $this->toFloat();
        }

        /** e.g. "19.99" — used automatically whenever Money is cast to string or echoed. */
        public function __toString(): string
        {
            return $this->toDecimalString();
        }

        // -----------------------------------------------------------------
        // Internals
        // -----------------------------------------------------------------

        private function assertSameScale(Money $other, string $operation): void
        {
            if ($this->scale !== $other->scale) {
                throw ScaleMismatchException::forOperation($operation, $this->scale, $other->scale);
            }
        }

        private static function subunitFactor(int $scale): int
        {
            return (int) (10 ** $scale);
        }

        private static function hasBcmath(): bool
        {
            return function_exists('bcmul');
        }


        #region ISeralizable implementation

        public function _dbType(): DBFieldType
        {
            return DBFieldType::BIG_INT;
        }

        public function _serialize(): int
        {
            return $this->amount;
        }

        public function _deserialize($data): void
        {
            $this->amount = $data;
        }
        #endregion
    }
