<?php

declare(strict_types=1);

namespace Wixnit\Data;

use RuntimeException;
use Wixnit\Enum\PhoneFormat;
use Wixnit\Interfaces\PhoneNumberValidatorInterface;

/**
 * Real, per-country phone validation and formatting, backed by
 * Google's libphonenumber via the giggsey/libphonenumber-for-php port.
 *
 * Not a dependency of this library by default — install it yourself
 * when you want stricter validation than DefaultPhoneNumberValidator
 * provides:
 *
 *   composer require giggsey/libphonenumber-for-php
 *
 * Then activate it once, e.g. in your app's bootstrap:
 *
 *   PhoneNumber::useLibPhoneNumber();
 *
 * Everything else about PhoneNumber's public API is unchanged — this
 * only swaps out how validation/splitting/formatting is done under
 * the hood.
 */
final class LibPhoneNumberValidator implements PhoneNumberValidatorInterface
{
    private object $util;

    public function __construct()
    {
        $utilClass = 'libphonenumber\\PhoneNumberUtil';

        if (!class_exists($utilClass)) {
            throw new RuntimeException(
                'LibPhoneNumberValidator requires the "giggsey/libphonenumber-for-php" package. ' .
                'Install it with: composer require giggsey/libphonenumber-for-php'
            );
        }

        $this->util = $utilClass::getInstance();
    }

    public function isValid(string $e164): bool
    {
        try {
            $parsed = $this->util->parse($e164, null);

            return $this->util->isValidNumber($parsed);
        } catch (\Throwable) {
            return false;
        }
    }

    public function split(string $e164): array
    {
        $parsed = $this->util->parse($e164, null);

        return [(string) $parsed->getCountryCode(), (string) $parsed->getNationalNumber()];
    }

    public function format(string $countryCode, string $nationalNumber, PhoneFormat $format): string
    {
        $formatClass = 'libphonenumber\\PhoneNumberFormat';
        $parsed = $this->util->parse('+' . $countryCode . $nationalNumber, null);

        $mapped = match ($format) {
            PhoneFormat::E164 => $formatClass::E164,
            PhoneFormat::INTERNATIONAL => $formatClass::INTERNATIONAL,
            PhoneFormat::NATIONAL => $formatClass::NATIONAL,
        };

        return $this->util->format($parsed, $mapped);
    }
}
