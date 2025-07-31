<?php

    namespace Wixnit\Enum;

    enum FilterOperation: string
    {
        case AND = 'AND';
        case OR = 'OR';
        case NOT = 'NOT';
        case EQUAL = '=';
        case NOT_EQUAL = '!=';
        case GREATER_THAN = '>';
        case LESS_THAN = '<';
        case GREATER_THAN_OR_EQUAL = '>=';
        case LESS_THAN_OR_EQUAL = '<=';
        case LIKE = 'LIKE';
    }