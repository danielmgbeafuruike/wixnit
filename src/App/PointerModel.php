<?php

    namespace wixnit\App;

    use wixnit\Data\DBCollection;
    use wixnit\Data\DBConfig;
    use mysqli;

    abstract class PointerModel extends BaseModel
    {
        function __construct(mysqli $dbConnection, $arg=null)
        {
            parent::__construct($dbConnection, $arg);
        }

        public static function Get(mysqli $dbConnection): DBCollection
        {
            return parent::buildCollection($dbConnection, ...func_get_args());
        }

        public static function SoftDeleted(mysqli $dbConnection): DBCollection
        {
            return parent::fromDeleted($dbConnection, ...func_get_args());
        }

        public static function Count(mysqli $dbConnection): int
        {
            return parent::countCollection($dbConnection, ...func_get_args());
        }

        public static function CountDeleted(mysqli $dbConnection): int
        {
            return parent::deletedCount($dbConnection, ...func_get_args());
        }

        public static function Purge(mysqli $dbConnection)
        {
            parent::purgeDeleted($dbConnection);
        }

        public static function QuickDelete(mysqli $dbConnection)
        {
            parent::ListDelete($dbConnection, ...func_get_args());
        }
    }