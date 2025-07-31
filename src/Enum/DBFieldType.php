<?php

    namespace Wixnit\Enum;

    enum DBFieldType : string
    {
        // Numeric Types
        case INT = 'int';
        case TINY_INT = 'tinyint';
        case SMALL_INT = 'smallint';
        case MEDIUM_INT = 'mediumint';
        case BIG_INT = 'bigint';
        case DECIMAL = 'decimal';
        case FLOAT = 'float';
        case DOUBLE = 'double';
        case BIT = 'bit';

        // String Types
        case CHAR = 'char';
        case VARCHAR = 'varchar';
        case TEXT = 'text';
        case TINY_TEXT = 'tinytext';
        case MEDIUM_TEXT = 'mediumtext';
        case LONG_TEXT = 'longtext';

        // Binary Types
        case BLOB = 'blob';
        case TINY_BLOB = 'tinyblob';
        case MEDIUM_BLOB = 'mediumblob';
        case LONG_BLOB = 'longblob';

        // Date and Time Types
        case DATE = 'date';
        case TIME = 'time';
        case YEAR = 'year';
        case TIME_STAMP = 'timestamp'; // Fixed value
        case DATETIME = 'datetime';    // Added missing type

        // JSON & Spatial Types
        case JSON = 'json';
        case GEOMETRY = 'geometry';

        // Set & Enum
        case ENUM = 'enum';
        case SET = 'set';
    }