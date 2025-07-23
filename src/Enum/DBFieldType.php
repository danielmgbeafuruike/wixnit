<?php

    namespace Wixnit\Enum;

    enum DBFieldType : string
    {
        case INT = "int";
        case VARCHAR = "varchar";
        case TEXT = "text";
        case LONG_TEXT = "longtext";
        case DOUBLE = "double";
        case FLOAT = "float";
        case DATE = "date";
        case DECIMAL = "decimal";
        case ENUM = "enum";
        case CHAR = "char";
        case TIME_STAMP = "current_timestamp";
        case TINY_INT = "tinyint";
        case BLOB = "blob";
        case BIG_INT = "bigint";
        case BIT = "bit";
        case LONG_BLOB = "longblob";
        case YEAR = "year";
        case TIME = "time";
        case SET = "set";
        case GEOMETRY = "geometry";
    }