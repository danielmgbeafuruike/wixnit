<?php

declare(strict_types=1);

namespace Wixnit\Data;

use Wixnit\Enum\PhoneFormat;
use Wixnit\Interfaces\PhoneNumberValidatorInterface;

/**
 * Lightweight, dependency-free phone validation.
 *
 * This checks *shape* — a plausible E.164 number (a leading non-zero
 * digit, 8–15 digits total) and a best-effort country-code split using
 * a small static lookup table. It does NOT validate against real
 * per-country numbering plans, so a shape-valid-but-nonexistent number
 * can pass. If you need that level of accuracy, switch to
 * LibPhoneNumberValidator via PhoneNumber::useLibPhoneNumber() instead —
 * this class is meant as a sensible, zero-dependency default, not a
 * full numbering-plan authority.
 */
final class DefaultPhoneNumberValidator implements PhoneNumberValidatorInterface
{
    /**
     * Country calling codes this validator recognizes, keyed by the
     * code itself. Not exhaustive — extend at runtime with
     * self::registerCountryCode() if you need a code that's missing.
     *
     * @var array<string, int>
     */
    private static array $countryCodeLengths = [
        // 1-digit
        '1' => 1, '7' => 1,
        // 2-digit (Europe, much of Asia/Oceania/Latin America)
        '20' => 2, '27' => 2, '30' => 2, '31' => 2, '32' => 2, '33' => 2, '34' => 2, '36' => 2,
        '39' => 2, '40' => 2, '41' => 2, '43' => 2, '44' => 2, '45' => 2, '46' => 2, '47' => 2,
        '48' => 2, '49' => 2, '51' => 2, '52' => 2, '53' => 2, '54' => 2, '55' => 2, '56' => 2,
        '57' => 2, '58' => 2, '60' => 2, '61' => 2, '62' => 2, '63' => 2, '64' => 2, '65' => 2,
        '66' => 2, '81' => 2, '82' => 2, '84' => 2, '86' => 2, '90' => 2, '91' => 2, '92' => 2,
        '93' => 2, '94' => 2, '95' => 2, '98' => 2,
        // 3-digit (much of Africa, plus small European/Caribbean states)
        '211' => 3, '212' => 3, '213' => 3, '216' => 3, '218' => 3, '220' => 3, '221' => 3,
        '222' => 3, '223' => 3, '224' => 3, '225' => 3, '226' => 3, '227' => 3, '228' => 3,
        '229' => 3, '230' => 3, '231' => 3, '232' => 3, '233' => 3, '234' => 3, '235' => 3,
        '236' => 3, '237' => 3, '238' => 3, '239' => 3, '240' => 3, '241' => 3, '242' => 3,
        '243' => 3, '244' => 3, '245' => 3, '246' => 3, '248' => 3, '249' => 3, '250' => 3,
        '251' => 3, '252' => 3, '253' => 3, '254' => 3, '255' => 3, '256' => 3, '257' => 3,
        '258' => 3, '260' => 3, '261' => 3, '262' => 3, '263' => 3, '264' => 3, '265' => 3,
        '266' => 3, '267' => 3, '268' => 3, '269' => 3, '350' => 3, '351' => 3, '352' => 3,
        '353' => 3, '354' => 3, '355' => 3, '356' => 3, '357' => 3, '358' => 3, '359' => 3,
        '370' => 3, '371' => 3, '372' => 3, '373' => 3, '374' => 3, '375' => 3, '376' => 3,
        '377' => 3, '378' => 3, '380' => 3, '381' => 3, '382' => 3, '385' => 3, '386' => 3,
        '387' => 3, '389' => 3, '420' => 3, '421' => 3, '423' => 3, '971' => 3, '972' => 3,
        '973' => 3, '974' => 3, '975' => 3, '976' => 3, '977' => 3, '992' => 3, '993' => 3,
        '994' => 3, '995' => 3, '996' => 3, '998' => 3,
    ];

    /** Register (or override) a country calling code at runtime. */
    public static function registerCountryCode(string $code): void
    {
        self::$countryCodeLengths[$code] = strlen($code);
    }

    public function isValid(string $e164): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $e164);
    }

    public function split(string $e164): array
    {
        $digits = ltrim($e164, '+');

        // Longest match first (3, then 2, then 1 digit) to avoid ambiguity
        // between e.g. "1" (NANP) and a 3-digit code starting with 1xx.
        foreach ([3, 2, 1] as $length) {
            $candidate = substr($digits, 0, $length);

            if ((self::$countryCodeLengths[$candidate] ?? null) === $length) {
                return [$candidate, substr($digits, $length)];
            }
        }

        // Unknown code: best-effort fallback assuming a 1-digit code.
        return [substr($digits, 0, 1), substr($digits, 1)];
    }

    public function format(string $countryCode, string $nationalNumber, PhoneFormat $format): string
    {
        return match ($format) {
            PhoneFormat::E164 => '+' . $countryCode . $nationalNumber,
            PhoneFormat::INTERNATIONAL => '+' . $countryCode . ' ' . $this->groupDigits($nationalNumber),
            // Heuristic only: many (not all) numbering plans use a leading
            // trunk "0" for national dialing. Not locale-correct for every
            // country — swap in LibPhoneNumberValidator if that matters.
            PhoneFormat::NATIONAL => '0' . $this->groupDigits($nationalNumber),
        };
    }

    private function groupDigits(string $digits): string
    {
        return trim((string) chunk_split($digits, 3, ' '));
    }
}
