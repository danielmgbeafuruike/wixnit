<?php

declare(strict_types=1);

namespace Wixnit\Data;

use JsonSerializable;
use Stringable;
use Wixnit\Enum\BarcodeFormat;
use Wixnit\Enum\DBFieldType;
use Wixnit\Exception\InvalidBarcodeException;
use Wixnit\Interfaces\ISerializable;

/**
 * A retail barcode (UPC-A, EAN-8, or EAN-13), with GS1 check-digit
 * validation baked into construction — so a mistyped or corrupted
 * barcode is caught immediately instead of silently stored and
 * discovered wrong later at a checkout scanner.
 *
 * All three symbologies share the same check-digit algorithm (weights
 * of 3 and 1 alternating from the rightmost data digit), so one
 * implementation covers all of them — the only difference between
 * formats is total length.
 */
final class Barcode implements ISerializable, Stringable, JsonSerializable
{
    private string $value = '';
    private ?BarcodeFormat $format = null;

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

        $value = is_object($arg) && isset($arg->value)
            ? (string) $arg->value
            : (is_string($arg) ? $arg : null);

        if ($value === null) {
            return;
        }

        $digits = self::stripFormatting($value);
        $format = self::detectFormat($digits);

        if ($format !== null) {
            $this->value = $digits;
            $this->format = $format;
        }
        // else: malformed/invalid checksum — leave default (empty) rather
        // than throwing, consistent with this library's "never throw on
        // hydration" convention (see fromString() for the strict path).
    }

    // -----------------------------------------------------------------
    // Strict construction (validates, for use with fresh application input)
    // -----------------------------------------------------------------

    /**
     * Parse a barcode string (spaces/hyphens are stripped automatically).
     * Validates length (8, 12, or 13 digits) and the GS1 check digit.
     * @throws InvalidBarcodeException if $value isn't a valid barcode.
     */
    public static function fromString(string $value): self
    {
        $digits = self::stripFormatting($value);
        $format = self::detectFormat($digits);

        if ($format === null) {
            throw new InvalidBarcodeException(sprintf('"%s" is not a valid barcode.', $value));
        }

        $instance = new self();
        $instance->value = $digits;
        $instance->format = $format;

        return $instance;
    }

    /** Same as fromString(), but returns null instead of throwing on invalid input. */
    public static function tryFrom(string $value): ?self
    {
        $digits = self::stripFormatting($value);
        $format = self::detectFormat($digits);

        if ($format === null) {
            return null;
        }

        $instance = new self();
        $instance->value = $digits;
        $instance->format = $format;

        return $instance;
    }

    public static function isValid(string $value): bool
    {
        return self::detectFormat(self::stripFormatting($value)) !== null;
    }

    /**
     * Build a barcode from data digits (i.e. without a check digit) —
     * the check digit is computed and appended automatically.
     * @throws InvalidBarcodeException if $dataDigits' length doesn't match $format.
     */
    public static function generate(string $dataDigits, BarcodeFormat $format): self
    {
        if (!ctype_digit($dataDigits) || strlen($dataDigits) !== $format->dataLength()) {
            throw new InvalidBarcodeException(sprintf(
                '%s requires exactly %d data digits, got "%s".',
                $format->name,
                $format->dataLength(),
                $dataDigits
            ));
        }

        $checkDigit = self::computeCheckDigit($dataDigits);

        $instance = new self();
        $instance->value = $dataDigits . $checkDigit;
        $instance->format = $format;

        return $instance;
    }

    private static function stripFormatting(string $value): string
    {
        return str_replace([' ', '-'], '', trim($value));
    }

    private static function detectFormat(string $digits): ?BarcodeFormat
    {
        if (!ctype_digit($digits) || $digits === '') {
            return null;
        }

        $format = BarcodeFormat::fromTotalLength(strlen($digits));

        if ($format === null) {
            return null;
        }

        $dataDigits = substr($digits, 0, $format->dataLength());
        $checkDigit = (int) substr($digits, -1);

        return self::computeCheckDigit($dataDigits) === $checkDigit ? $format : null;
    }

    /**
     * The standard GS1 check-digit algorithm, shared by UPC-A/EAN-8/EAN-13:
     * starting from the rightmost data digit, alternate weights 3 and 1;
     * the check digit brings the total to the next multiple of 10.
     */
    private static function computeCheckDigit(string $dataDigits): int
    {
        $sum = 0;
        $weight = 3;

        for ($i = strlen($dataDigits) - 1; $i >= 0; $i--) {
            $sum += ((int) $dataDigits[$i]) * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10;
    }

    // -----------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------

    /** The full digit string, including the check digit. */
    public function getValue(): string
    {
        return $this->value;
    }

    public function getFormat(): ?BarcodeFormat
    {
        return $this->format;
    }

    public function getCheckDigit(): int
    {
        return (int) substr($this->value, -1);
    }

    /** The digits excluding the check digit. */
    public function getDataDigits(): string
    {
        return substr($this->value, 0, -1);
    }

    // -----------------------------------------------------------------
    // Conversions between related formats
    // -----------------------------------------------------------------

    /**
     * Convert a UPC-A to its EAN-13 equivalent by prefixing "0" — the
     * check digit is mathematically unchanged by this prefix, since the
     * GS1 algorithm weights digits from the right. Returns $this
     * unchanged if it's already EAN-13, or null for EAN-8 (no standard
     * EAN-13 equivalent).
     */
    public function toEan13(): ?self
    {
        return match ($this->format) {
            BarcodeFormat::EAN_13 => $this,
            BarcodeFormat::UPC_A => self::fromString('0' . $this->value),
            default => null,
        };
    }

    /**
     * Convert an EAN-13 to UPC-A — only possible if it starts with "0"
     * (i.e. it's a UPC-A code embedded in EAN-13 form). Returns $this
     * unchanged if already UPC-A, or null otherwise.
     */
    public function toUpcA(): ?self
    {
        return match (true) {
            $this->format === BarcodeFormat::UPC_A => $this,
            $this->format === BarcodeFormat::EAN_13 && str_starts_with($this->value, '0')
                => self::fromString(substr($this->value, 1)),
            default => null,
        };
    }

    // -----------------------------------------------------------------
    // Comparison
    // -----------------------------------------------------------------

    public function equals(Barcode $other): bool
    {
        return $this->value === $other->value;
    }

    // -----------------------------------------------------------------
    // Display
    // -----------------------------------------------------------------

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
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
        return DBFieldType::VARCHAR;
    }

    /**
     * prepare the object for saving to db
     * @return string
     */
    public function _serialize(): string
    {
        return $this->value;
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
