<?php

    namespace Wixnit\Data;

    enum DBFieldType : string
    {
        case Int = "int";
        case Varchar = "varchar";
        case Text = "text";
        case LongText = "longtext";
        case Double = "double";
        case Float = "float";
        case Date = "date";
        case Decimal = "decimal";
        case Enum = "enum";
        case Char = "char";
        case TimeStamp = "current_timestamp";
        case TinyInt = "tinyint";
        case Blob = "blob";
        case BigInt = "bigint";
        case Bit = "bit";
        case LongBlob = "longblob";
        case Year = "year";
        case Time = "time";
        case Set = "set";
        case Geometry = "geometry";
    }