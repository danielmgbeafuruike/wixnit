<?php

declare(strict_types=1);

namespace Wixnit\Data;

use Wixnit\Interfaces\UsernameValidatorInterface;

/**
 * The default username rule set: length bounds, an allowed-character
 * pattern, a no-consecutive-special-characters check, and a reserved
 * word blocklist. All of these are configurable at runtime — call the
 * static setters below (typically once, at app bootstrap) to adjust
 * them without needing to implement UsernameValidatorInterface yourself.
 */
final class DefaultUsernameValidator implements UsernameValidatorInterface
{
    private static int $minLength = 3;
    private static int $maxLength = 20;

    /** Must start with a letter; letters/digits/underscore/dot afterward. */
    private static string $pattern = '/^[A-Za-z][A-Za-z0-9_.]*$/';

    /** @var string[] lowercased reserved words that can never be used as a username. */
    private static array $reservedWords = [
        'admin', 'administrator', 'root', 'superuser', 'moderator', 'mod',
        'support', 'help', 'api', 'null', 'undefined', 'system', 'staff',
        'owner', 'webmaster', 'security', 'me', 'you', 'everyone', 'here',
        'anonymous', 'guest',
    ];

    public static function setMinLength(int $length): void
    {
        self::$minLength = $length;
    }

    public static function setMaxLength(int $length): void
    {
        self::$maxLength = $length;
    }

    /** Set the allowed-character regex. Must be a full PCRE pattern including delimiters, e.g. '/^[a-z0-9_]+$/i'. */
    public static function setPattern(string $regex): void
    {
        self::$pattern = $regex;
    }

    /** @param string[] $words */
    public static function setReservedWords(array $words): void
    {
        self::$reservedWords = array_map('strtolower', $words);
    }

    public static function addReservedWord(string $word): void
    {
        self::$reservedWords[] = strtolower($word);
    }

    public static function isReserved(string $value): bool
    {
        return in_array(strtolower($value), self::$reservedWords, true);
    }

    public function isValid(string $value): bool
    {
        return $this->explainInvalid($value) === null;
    }

    public function explainInvalid(string $value): ?string
    {
        $length = strlen($value);

        if ($length < self::$minLength) {
            return sprintf('must be at least %d characters', self::$minLength);
        }

        if ($length > self::$maxLength) {
            return sprintf('must be at most %d characters', self::$maxLength);
        }

        if (!preg_match(self::$pattern, $value)) {
            return 'contains invalid characters, or does not start with a letter';
        }

        if (str_contains($value, '..') || str_contains($value, '__')) {
            return 'cannot contain consecutive special characters';
        }

        if (self::isReserved($value)) {
            return 'is a reserved word and cannot be used';
        }

        return null;
    }
}
