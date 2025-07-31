<?php

    namespace Wixnit\Enum;

    enum DBJoin
    {
        case INNER;
        case RIGHT;
        case LEFT;
        case SELF;
        case CROSS;
        case OUTER;
        case NATURAL;
    }