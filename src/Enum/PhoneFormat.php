<?php

declare(strict_types=1);

namespace Wixnit\Enum;

/** Display formats a PhoneNumber can be rendered in. */
enum PhoneFormat
{
    case E164;          // +2348012345678
    case INTERNATIONAL; // +234 801 234 5678
    case NATIONAL;      // 0801 234 5678
}
