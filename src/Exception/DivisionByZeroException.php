<?php

    declare(strict_types=1);

    namespace Wixnit\Exception;

    use RuntimeException;

    /** Thrown when attempting to divide Money by zero or allocate by an all-zero ratio set. */
    final class DivisionByZeroException extends RuntimeException {}
