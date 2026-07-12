<?php

declare(strict_types=1);

namespace Wixnit\Data;

use JsonSerializable;
use Stringable;
use Wixnit\Enum\DBFieldType;
use Wixnit\Enum\PhoneFormat;
use Wixnit\Interfaces\ISerializable;
use Wixnit\Interfaces\PhoneNumberValidatorInterface;
use Wixnit\Utilities\InvalidPhoneNumberException;

/**
 * A phone number value object.
 *
 * Canonical storage is E.164 ("+2348012345678"), split into a country
 * code and national number — the same "one unambiguous canonical form"
 * idea as Money's minor-unit integer.
 *
 * Validation is pluggable (a "rule" you choose): by default it uses
 * DefaultPhoneNumberValidator, a lightweight, dependency-free shape
 * check. If you need real per-country accuracy, opt into
 * LibPhoneNumberValidator (backed by Google's libphonenumber):
 *
 *   PhoneNumber::useLibPhoneNumber();
 *
 * ...or provide your own by implementing PhoneNumberValidatorInterface
 * and calling PhoneNumber::setValidator($yourValidator). This choice is
 * global/app-wide (set once at bootstrap), matching how you'd configure
 * any other cross-cutting validation rule.
 */
final class PhoneNumber implements ISerializable, Stringable, JsonSerializable
{
    private static ?PhoneNumberValidatorInterface $validator = null;
    private static string $defaultCountryCode = '234';

    private string $countryCode = '';
    private string $nationalNumber = '';
    private ?string $extension = null;

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
        if ($arg === null || $arg === '') {
            return;
        }

        if (is_string($arg)) {
            [$number, $extension] = self::splitExtension($arg);
            $e164 = self::normalizeToE164($number, self::$defaultCountryCode);

            if (self::getValidator()->isValid($e164)) {
                [$this->countryCode, $this->nationalNumber] = self::getValidator()->split($e164);
                $this->extension = $extension;
            }

            return;
        }

        if (is_object($arg)) {
            $this->countryCode = isset($arg->countryCode) ? (string) $arg->countryCode : '';
            $this->nationalNumber = isset($arg->nationalNumber) ? (string) $arg->nationalNumber : '';
            $this->extension = isset($arg->extension) ? (string) $arg->extension : null;
        }
    }

    // -----------------------------------------------------------------
    // Pluggable validation strategy
    // -----------------------------------------------------------------

    /** Provide your own validation/formatting rule (e.g. a custom implementation, or LibPhoneNumberValidator). */
    public static function setValidator(PhoneNumberValidatorInterface $validator): void
    {
        self::$validator = $validator;
    }

    /** Revert to the built-in, dependency-free validator. */
    public static function useDefaultValidator(): void
    {
        self::$validator = new DefaultPhoneNumberValidator();
    }

    /**
     * Switch to real per-country validation via Google's libphonenumber
     * (through giggsey/libphonenumber-for-php). Throws if that package
     * isn't installed — see LibPhoneNumberValidator for install steps.
     */
    public static function useLibPhoneNumber(): void
    {
        self::$validator = new LibPhoneNumberValidator();
    }

    public static function getValidator(): PhoneNumberValidatorInterface
    {
        return self::$validator ??= new DefaultPhoneNumberValidator();
    }

    /** The country code (without "+") assumed when a number is given without one. Defaults to "234" (Nigeria). */
    public static function setDefaultCountryCode(string $code): void
    {
        self::$defaultCountryCode = ltrim($code, '+');
    }

    public static function getDefaultCountryCode(): string
    {
        return self::$defaultCountryCode;
    }

    // -----------------------------------------------------------------
    // Strict construction (validates, for use with fresh application input)
    // -----------------------------------------------------------------

    /**
     * @param string $value A phone number in any reasonable format ("+234...", "0801...", "234 801 ...").
     * @param string|null $defaultCountry Country code to assume if $value has no "+" prefix. Defaults to self::$defaultCountryCode.
     * @throws InvalidPhoneNumberException if $value isn't a valid phone number.
     */
    public static function fromE164(string $value, ?string $defaultCountry = null): self
    {
        $countryCode = ltrim($defaultCountry ?? self::$defaultCountryCode, '+');
        $e164 = self::normalizeToE164($value, $countryCode);

        if (!self::getValidator()->isValid($e164)) {
            throw new InvalidPhoneNumberException(sprintf('"%s" is not a valid phone number.', $value));
        }

        return new self($e164);
    }

    /** Build from a national number (no country code) plus an explicit country code, e.g. fromNational("08012345678", "234"). */
    public static function fromNational(string $number, ?string $countryCode = null): self
    {
        $countryCode = ltrim($countryCode ?? self::$defaultCountryCode, '+');
        $digits = preg_replace('/\D/', '', $number) ?? '';
        $digits = ltrim($digits, '0'); // drop the national trunk prefix, e.g. leading 0 in "0801..."

        return self::fromE164('+' . $countryCode . $digits);
    }

    /** Same as fromE164(), but returns null instead of throwing on invalid input. */
    public static function tryFrom(string $value, ?string $defaultCountry = null): ?self
    {
        $countryCode = ltrim($defaultCountry ?? self::$defaultCountryCode, '+');
        $e164 = self::normalizeToE164($value, $countryCode);

        return self::getValidator()->isValid($e164) ? new self($e164) : null;
    }

    private static function normalizeToE164(string $value, string $defaultCountryCode): string
    {
        $value = trim($value);
        $hasPlus = str_starts_with($value, '+');
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (!$hasPlus) {
            $digits = $defaultCountryCode . ltrim($digits, '0');
        }

        return '+' . $digits;
    }

    /** @return array{0:string,1:?string} [number, extension] */
    private static function splitExtension(string $value): array
    {
        if (str_contains($value, ';ext=')) {
            [$number, $ext] = explode(';ext=', $value, 2);

            return [$number, $ext];
        }

        return [$value, null];
    }

    // -----------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getNationalNumber(): string
    {
        return $this->nationalNumber;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    /** Get a cloned PhoneNumber with the extension set. */
    public function withExtension(string $extension): self
    {
        $clone = clone $this;
        $clone->extension = $extension;

        return $clone;
    }

    // -----------------------------------------------------------------
    // Formatting
    // -----------------------------------------------------------------

    public function toE164(): string
    {
        return '+' . $this->countryCode . $this->nationalNumber;
    }

    public function format(PhoneFormat $format = PhoneFormat::E164): string
    {
        $base = self::getValidator()->format($this->countryCode, $this->nationalNumber, $format);

        return $this->extension !== null ? $base . ' ext. ' . $this->extension : $base;
    }

    public function __toString(): string
    {
        return $this->toE164();
    }

    public function jsonSerialize(): string
    {
        return $this->toE164();
    }

    // -----------------------------------------------------------------
    // Comparison
    // -----------------------------------------------------------------

    public function equals(PhoneNumber $other): bool
    {
        return $this->countryCode === $other->countryCode
            && $this->nationalNumber === $other->nationalNumber;
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
        $value = $this->toE164();

        return $this->extension !== null ? $value . ';ext=' . $this->extension : $value;
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
