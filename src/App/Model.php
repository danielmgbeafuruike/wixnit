<?php

    namespace Wixnit\App;

    use Wixnit\Data\DBCollection;
    use Wixnit\Data\DBConfig;
    use mysqli;
    use Wixnit\Data\DBResult;

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

        /**
         * Get data from the db in a DBCollection object filtered and estricted by Filters, Searches, Pagination and other DB result restriciting objects
         * @param array $
         * @return DBCollection
         */
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
            return parent::BuildCollection(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        /**
         * Delete items from the db virtually without loosing the actual data. All fetch operations will ignore this records
         * @param array $
         * @return DBCollection
         */
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
            return parent::FromDeleted(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        /**
         * Get the number of rows retrieved by processing Filters, Searches etc.
         * @param array $
         * @return int
         */
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
            return parent::CountCollection(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        /**
         * Count the items that have been virtually deleted
         * @param array $
         * @return int
         */
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
            return parent::DeletedCount(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        /**
         * Delete all the virtually deleted items from the db
         * @param array $
         * @return void
         */
        public static function Purge(): DBResult
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
            return parent::PurgeDeleted(($dbConnection != null ? $dbConnection : new DBConfig()));
        }

        /**
         * Delete items by passing their ids or an instance of the item
         * @param array $
         * @return void
         */
        public static function DeleteList(): DBResult
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
            return parent::QuickDelete(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        /**
         * Delete items by passing their ids or an instance of the item
         * @param array $
         * @return void
         */
        public static function SaveList(): void
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
            parent::QuickSave(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }
    }