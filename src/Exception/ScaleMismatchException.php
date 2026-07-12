<?php

    declare(strict_types=1);

    namespace Wixnit\Exception;

    use InvalidArgumentException;

    /** Thrown when an operation combines two Money instances of different scales. */
    final class ScaleMismatchException extends InvalidArgumentException
    {
        public static function forOperation(string $operation, int $expected, int $given): self
        {
            return new self(sprintf(
                'Cannot %s: scale mismatch (expected %d decimal place%s, got %d).',
                $operation,
                $expected,
                $expected === 1 ? '' : 's',
                $given
            ));
        }
    }