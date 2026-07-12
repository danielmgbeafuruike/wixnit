<?php

declare(strict_types=1);

namespace Wixnit\Utilities;

use InvalidArgumentException;

/** Thrown when a string cannot be interpreted as a valid email address. */
final class InvalidEmailException extends InvalidArgumentException
{
}
