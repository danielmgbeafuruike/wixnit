<?php

    namespace wixnit\App;

    use wixnit\Data\DBCollection;
    use wixnit\Data\DBConfig;
    use mysqli;

    abstract class Model extends BaseModel
    {
        function __construct()
        {
            $dbConnection = null;
            $arg = null;

            $args = func_get_args();

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof mysqli)
                {
                    $dbConnection = $args[$i];
                }
                else
                {
                    $arg = $args[$i];
                }
            }
            parent::__construct(($dbConnection != null ? $dbConnection : new DBConfig()), $arg);
        }

        public static function Get(): DBCollection
        {
            $dbConnection = null;
            $args = func_get_args();

            for($i = 0; $i < count($args); $i++)
            {
                if(is_a($args[$i], "mysqli"))
                {
                    $dbConnection = $args[$i];
                }
            }
            return parent::buildCollection(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        public static function SoftDeleted(): DBCollection
        {
            $dbConnection = null;
            $args = func_get_args();

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof mysqli)
                {
                    $dbConnection = $args[$i];
                }
            }
            return parent::fromDeleted(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        public static function Count(): int
        {
            $dbConnection = null;
            $args = func_get_args();

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof mysqli)
                {
                    $dbConnection = $args[$i];
                }
            }
            return parent::countCollection(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        public static function CountDeleted(): int
        {
            $dbConnection = null;
            $args = func_get_args();

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof mysqli)
                {
                    $dbConnection = $args[$i];
                }
            }
            return parent::deletedCount(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        public static function Purge()
        {
            $dbConnection = null;
            $args = func_get_args();

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof mysqli)
                {
                    $dbConnection = $args[$i];
                }
            }
            parent::purgeDeleted(($dbConnection != null ? $dbConnection : new DBConfig()));
        }

        public static function QuickDelete()
        {
            $dbConnection = null;
            $args = func_get_args();

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof mysqli)
                {
                    $dbConnection = $args[$i];
                }
            }
            parent::ListDelete(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }
    }