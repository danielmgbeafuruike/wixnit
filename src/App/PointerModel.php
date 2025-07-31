<?php

    namespace Wixnit\App;

    use Wixnit\Data\DBCollection;
    use mysqli;
    use Wixnit\Data\DBResult;

    abstract class PointerModel extends BaseModel
    {
        function __construct(mysqli $dbConnection, $arg=null)
        {
            parent::__construct($dbConnection, $arg);
        }

        /**
         * Get data from the db in a DBCollection object filtered and estricted by Filters, Searches, Pagination and other DB result restriciting objects
         * @param array $
         * @return DBCollection
         */
        public static function Get(mysqli $dbConnection): DBCollection
        {
            return parent::BuildCollection($dbConnection, ...func_get_args());
        }

        /**
         * Delete items from the db virtually without loosing the actual data. All fetch operations will ignore this records
         * @param array $
         * @return DBCollection
         */
        public static function SoftDeleted(mysqli $dbConnection): DBCollection
        {
            return parent::FromDeleted($dbConnection, ...func_get_args());
        }

        /**
         * Get the number of rows retrieved by processing Filters, Searches etc.
         * @param array $
         * @return int
         */
        public static function Count(mysqli $dbConnection): int
        {
            return parent::CountCollection($dbConnection, ...func_get_args());
        }

        /**
         * Count the items that have been virtually deleted
         * @param array $
         * @return int
         */
        public static function CountDeleted(mysqli $dbConnection): int
        {
            return parent::DeletedCount($dbConnection, ...func_get_args());
        }

        /**
         * Delete all the virtually deleted items from the db
         * @param array $
         * @return void
         */
        public static function Purge(mysqli $dbConnection): DBResult
        {
            return parent::PurgeDeleted($dbConnection);
        }

        /**
         * Delete items by passing their ids or an instance of the item
         * @param array $
         * @return void
         */
        public static function DeleteList(mysqli $dbConnection): DBResult
        {
            return parent::QuickDelete($dbConnection, ...func_get_args());
        }

        /**
         * Delete items by passing their ids or an instance of the item
         * @param array $
         * @return void
         */
        public static function SaveList(mysqli $dbConnection): void
        {
            return parent::QuickSave($dbConnection, ...func_get_args());
        }
    }