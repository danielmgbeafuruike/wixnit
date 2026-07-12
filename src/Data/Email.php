<?php

declare(strict_types=1);

namespace Wixnit\Data;

use JsonSerializable;
use Stringable;
use Wixnit\Enum\DBFieldType;
use Wixnit\Interfaces\ISerializable;
use Wixnit\Utilities\InvalidEmailException;

/**
 * An email address value object.
 *
 * Storage is a single, fully lowercased, trimmed string — so
 * "Foo@Bar.COM" and "foo@bar.com" are the same Email and compare equal,
 * which matches how virtually every real mail provider treats addresses
 * in practice even though the local part is technically case-sensitive
 * per RFC 5321. If your application ever needs the exact original
 * casing (e.g. to echo back exactly what a user typed), keep that raw
 * string alongside the Email rather than inside it.
 *
 * Construction mirrors the existing Color class's pattern for
 * framework/DB hydration compatibility: the constructor accepts an
 * optional raw value and hydrates leniently (bad data becomes an empty
 * address rather than throwing, since DB rows are assumed pre-validated).
 * For anything created from fresh application input, use the strict
 * fromString()/tryFrom() factories instead, which do validate.
 */
final class Email implements ISerializable, Stringable, JsonSerializable
{
    private string $address = '';

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

        if (is_object($arg) && isset($arg->address)) {
            $this->address = self::normalize((string) $arg->address) ?? '';
            return;
        }

        if (is_string($arg)) {
            $this->address = self::normalize($arg) ?? '';
        }
    }

    // -----------------------------------------------------------------
    // Strict construction (validates, for use with fresh application input)
    // -----------------------------------------------------------------

    /** @throws InvalidEmailException if $value isn't a valid email address. */
    public static function fromString(string $value): self
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            throw new InvalidEmailException(sprintf('"%s" is not a valid email address.', $value));
        }

        return new self($normalized);
    }

    /** Same as fromString(), but returns null instead of throwing on invalid input. */
    public static function tryFrom(string $value): ?self
    {
        $normalized = self::normalize($value);

        return $normalized === null ? null : new self($normalized);
    }

    public static function isValid(string $value): bool
    {
        return self::normalize($value) !== null;
    }

    private static function normalize(string $value): ?string
    {
        $value = strtolower(trim($value));

        if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $value;
    }

    // -----------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getLocalPart(): string
    {
        $at = strrpos($this->address, '@');

        return $at === false ? $this->address : substr($this->address, 0, $at);
    }

    public function getDomain(): string
    {
        $at = strrpos($this->address, '@');

        return $at === false ? '' : substr($this->address, $at + 1);
    }

    // -----------------------------------------------------------------
    // Comparison
    // -----------------------------------------------------------------

    public function equals(Email $other): bool
    {
        return $this->address === $other->address;
    }

    // -----------------------------------------------------------------
    // Display
    // -----------------------------------------------------------------

    /** A privacy-safe display form for logs/UI, e.g. "j***@example.com". */
    public function mask(): string
    {
        if ($this->address === '') {
            return '';
        }

        $local = $this->getLocalPart();
        $domain = $this->getDomain();
        $visible = substr($local, 0, 1);
        $maskedLocal = $visible . str_repeat('*', max(1, strlen($local) - 1));

        return $maskedLocal . '@' . $domain;
    }

    public function __toString(): string
    {
        return $this->address;
    }

    public function jsonSerialize(): string
    {
        return $this->address;
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
        return $this->address;
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
