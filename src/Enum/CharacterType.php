<?php

    namespace Wixnit\Enum;

    enum CharacterType : string
    {
        case NUMERIC = "numeric";
        case ALPHANUMERIC = "alphanumeric";
        case ALPHABETIC = "alphabetic";
    }