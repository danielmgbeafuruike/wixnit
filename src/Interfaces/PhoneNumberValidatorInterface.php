<?php

declare(strict_types=1);

namespace  Wixnit\Interfaces;

use Wixnit\Enum\PhoneFormat;

/**
 * The validation/formatting strategy PhoneNumber delegates to.
 *
 * This is the "rule" the user picks between: the built-in
 * DefaultPhoneNumberValidator (lightweight, no dependencies), or
 * LibPhoneNumberValidator (accurate, backed by Google's libphonenumber
 * via giggsey/libphonenumber-for-php), or a custom implementation of
 * your own. Swap the active one with PhoneNumber::setValidator().
 */
interface PhoneNumberValidatorInterface
{
    /** Is this E.164-formatted number ("+2348012345678") valid? */
    public function isValid(string $e164): bool;

    /**
     * Split an E.164 number into its country code and national number.
     * @return array{0:string,1:string} [countryCode, nationalNumber]
     */
    public function split(string $e164): array;

    /** Render a country code + national number pair in the requested format. */
    public function format(string $countryCode, string $nationalNumber, PhoneFormat $format): string;
}
