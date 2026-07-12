<?php

declare(strict_types=1);

namespace Wixnit\Data;

use JsonSerializable;
use Stringable;
use Wixnit\Enum\DBFieldType;
use Wixnit\Exception\InvalidUsernameException;
use Wixnit\Interfaces\ISerializable;
use Wixnit\Interfaces\UsernameValidatorInterface;

/**
 * A validated username/handle.
 *
 * Display casing is preserved as typed (e.g. "JohnDoe" stays
 * "JohnDoe"), but equals()/getNormalized() compare case-insensitively —
 * so uniqueness checks treat "JohnDoe" and "johndoe" as the same user,
 * while the UI can still show what the person actually typed. If your
 * database enforces uniqueness, index on getNormalized()'s value (a
 * generated lowercase column), not the raw one.
 *
 * Validation is pluggable — see UsernameValidatorInterface and
 * DefaultUsernameValidator — the same strategy pattern PhoneNumber uses.
 */
final class Username implements ISerializable, Stringable, JsonSerializable
{
    private static ?UsernameValidatorInterface $validator = null;

    private string $value = '';

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

        $value = trim($value);

        if (self::getValidator()->isValid($value)) {
            $this->value = $value;
        }
        // else: malformed/reserved input — leave default ('') rather than
        // throwing, consistent with this library's "never throw on
        // hydration" convention (see fromString() for the strict path).
    }

    // -----------------------------------------------------------------
    // Pluggable validation strategy
    // -----------------------------------------------------------------

    public static function setValidator(UsernameValidatorInterface $validator): void
    {
        self::$validator = $validator;
    }

    public static function useDefaultValidator(): void
    {
        self::$validator = new DefaultUsernameValidator();
    }

    public static function getValidator(): UsernameValidatorInterface
    {
        return self::$validator ??= new DefaultUsernameValidator();
    }

    // -----------------------------------------------------------------
    // Strict construction (validates, for use with fresh application input)
    // -----------------------------------------------------------------

    /** @throws InvalidUsernameException if $value fails the active validator's rules. */
    public static function fromString(string $value): self
    {
        $value = trim($value);
        $reason = self::getValidator()->explainInvalid($value);

        if ($reason !== null) {
            throw new InvalidUsernameException(sprintf('"%s" %s.', $value, $reason));
        }

        return new self($value);
    }

    /** Same as fromString(), but returns null instead of throwing on invalid input. */
    public static function tryFrom(string $value): ?self
    {
        $value = trim($value);

        return self::getValidator()->isValid($value) ? new self($value) : null;
    }

    public static function isValid(string $value): bool
    {
        return self::getValidator()->isValid(trim($value));
    }

    // -----------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------

    /** The username as originally typed/stored, casing preserved. */
    public function getValue(): string
    {
        return $this->value;
    }

    /** Lowercased form — use this for uniqueness checks/lookups. */
    public function getNormalized(): string
    {
        return strtolower($this->value);
    }

    // -----------------------------------------------------------------
    // Comparison
    // -----------------------------------------------------------------

    public function equals(Username $other): bool
    {
        return $this->getNormalized() === $other->getNormalized();
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
