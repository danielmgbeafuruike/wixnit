<?php

    namespace Wixnit\Enum;

    enum DBJoin : string
    {
        case INNER = "INNER";
        case RIGHT = "RIGHT";
        case LEFT = "LEFT";
        case SELF = "SELF";
        case CROSS = "CROSS";
        case OUTER = "OUTER";
        case NATURAL = "NATURAL";
    }