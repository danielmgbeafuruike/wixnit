<?php

declare(strict_types=1);

namespace Wixnit\Exception;

use InvalidArgumentException;

/** Thrown when a string cannot be interpreted as a valid username. */
final class InvalidUsernameException extends InvalidArgumentException
{
}
