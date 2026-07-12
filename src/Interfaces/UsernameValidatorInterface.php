<?php

declare(strict_types=1);

namespace Wixnit\Interfaces;

/**
 * The validation "rule" Username delegates to — same pluggable-strategy
 * idea as PhoneNumberValidatorInterface. Swap the active rule set with
 * Username::setValidator(), e.g. to tighten/loosen length limits, change
 * the allowed character pattern, or use an entirely different policy
 * (a profanity filter, an external uniqueness check, etc.).
 */
interface UsernameValidatorInterface
{
    public function isValid(string $value): bool;

    /** A human-readable reason $value is invalid, or null if it's valid. */
    public function explainInvalid(string $value): ?string;
}
