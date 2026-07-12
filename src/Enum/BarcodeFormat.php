<?php

declare(strict_types=1);

namespace Wixnit\Enum;

/** Supported barcode symbologies, identified by their total digit count. */
enum BarcodeFormat
{
    case UPC_A;
    case EAN_8;
    case EAN_13;

    /** Total digits including the check digit. */
    public function totalLength(): int
    {
        return match ($this) {
            self::UPC_A => 12,
            self::EAN_8 => 8,
            self::EAN_13 => 13,
        };
    }

    /** Data digits only, excluding the check digit. */
    public function dataLength(): int
    {
        return $this->totalLength() - 1;
    }

    public static function fromTotalLength(int $length): ?self
    {
        return match ($length) {
            8 => self::EAN_8,
            12 => self::UPC_A,
            13 => self::EAN_13,
            default => null,
        };
    }
}
