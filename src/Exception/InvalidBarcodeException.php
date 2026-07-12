<?php

declare(strict_types=1);

namespace Wixnit\Exception;

use InvalidArgumentException;

/** Thrown when a string cannot be interpreted as a valid barcode. */
final class InvalidBarcodeException extends InvalidArgumentException
{
}
