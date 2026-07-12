<?php

    declare(strict_types=1);

    namespace Wixnit\Exception;

    use InvalidArgumentException;

    /** Thrown when a value cannot be interpreted as a valid monetary amount, or a scale is invalid. */
    final class InvalidAmountException extends InvalidArgumentException {}