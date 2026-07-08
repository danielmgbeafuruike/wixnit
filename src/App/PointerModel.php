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
         * Get data from the db in a DBCollection object filtered and restricted by Filters, Searches, Pagination and other DB result restriciting objects
         * @return DBCollection
         */
        public static function Get(mysqli $dbConnection): DBCollection
        {
            return parent::BuildCollection($dbConnection, ...func_get_args());
        }

        /**
         * Delete items from the db virtually without loosing the actual data. All fetch operations will ignore this records
         * @return DBCollection
         */
        public static function SoftDeleted(mysqli $dbConnection): DBCollection
        {
            return parent::FromDeleted($dbConnection, ...func_get_args());
        }

        /**
         * Get the number of rows retrieved by processing Filters, Searches etc.
         * @return int
         */
        public static function Count(mysqli $dbConnection): int
        {
            return parent::CountCollection($dbConnection, ...func_get_args());
        }

        /**
         * Count the items that have been virtually deleted
         * @return int
         */
        public static function CountDeleted(mysqli $dbConnection): int
        {
            return parent::DeletedCount($dbConnection, ...func_get_args());
        }

        /**
         * Delete all the virtually deleted items from the db
         * @return void
         */
        public static function Purge(mysqli $dbConnection): DBResult
        {
            return parent::PurgeDeleted($dbConnection);
        }

        /**
         * Delete items by passing their ids or an instance of the item
         * @return void
         */
        public static function DeleteList(mysqli $dbConnection): DBResult
        {
            return parent::QuickDelete($dbConnection, ...func_get_args());
        }

        /**
         * Delete items by passing their ids or an instance of the item
         * @return void
         */
        public static function SaveList(mysqli $dbConnection): void
        {
            parent::QuickSave($dbConnection, ...func_get_args());
        }

        /**
         * Get the sum of a numeric field across all matching rows.
         * @return int|float|string|null
         */
        public static function Sum(mysqli $dbConnection, string $field): int|float|string|null
        {
            return parent::SumValue($dbConnection, $field, ...func_get_args());
        }

        /**
         * Get the average of a numeric field across all matching rows.
         * @return int|float|string|null
         */
        public static function Average(mysqli $dbConnection, string $field): int|float|string|null
        {
            return parent::AverageValue($dbConnection, $field, ...func_get_args());
        }

        /**
         * Get the minimum value of a field across all matching rows.
         * @return int|float|string|null
         */
        public static function Min(mysqli $dbConnection, string $field): int|float|string|null
        {
            return parent::MinValue($dbConnection, $field, ...func_get_args());
        }

        /**
         * Get the maximum value of a field across all matching rows.
         * @return int|float|string|null
         */
        public static function Max(mysqli $dbConnection, string $field): int|float|string|null
        {
            return parent::MaxValue($dbConnection, $field, ...func_get_args());
        }

        /**
         * Cheaply check whether any row matches, without counting the whole matching set.
         * @return bool
         */
        public static function Exists(mysqli $dbConnection): bool
        {
            return parent::ExistsCollection($dbConnection, ...func_get_args());
        }

        /**
         * Get the first matching row hydrated as an object, or null if none match.
         * @return static|null
         */
        public static function First(mysqli $dbConnection)
        {
            return parent::FirstOf($dbConnection, ...func_get_args());
        }

        /**
         * Get the most recently created matching row.
         * @return static|null
         */
        public static function Latest(mysqli $dbConnection)
        {
            return parent::LatestOf($dbConnection, ...func_get_args());
        }

        /**
         * Get the oldest matching row.
         * @return static|null
         */
        public static function Oldest(mysqli $dbConnection)
        {
            return parent::OldestOf($dbConnection, ...func_get_args());
        }

        /**
         * Get a single column across all matching rows, without hydrating full objects.
         * @return array
         */
        public static function Pluck(mysqli $dbConnection, string $field): array
        {
            return parent::PluckValue($dbConnection, $field, ...func_get_args());
        }

        /**
         * Count matching rows grouped by a field.
         * @return array
         */
        public static function GroupCount(mysqli $dbConnection, string $field): array
        {
            return parent::GroupCountValue($dbConnection, $field, ...func_get_args());
        }

        /**
         * Atomically increment a numeric field on every matching row.
         * @return DBResult
         */
        public static function Increment(mysqli $dbConnection, string $field, int|float $by = 1): DBResult
        {
            return parent::IncrementValue($dbConnection, $field, $by, ...func_get_args());
        }

        /**
         * Atomically decrement a numeric field on every matching row.
         * @return DBResult
         */
        public static function Decrement(mysqli $dbConnection, string $field, int|float $by = 1): DBResult
        {
            return parent::DecrementValue($dbConnection, $field, $by, ...func_get_args());
        }

        /**
         * Bulk-update every matching row directly in SQL, without a fetch-then-save round trip per row.
         * @return DBResult
         */
        public static function UpdateWhere(mysqli $dbConnection, array $data): DBResult
        {
            return parent::UpdateMatching($dbConnection, $data, ...func_get_args());
        }

        /**
         * Undo a soft delete on every matching row.
         * @return DBResult
         */
        public static function Restore(mysqli $dbConnection): DBResult
        {
            return parent::RestoreValue($dbConnection, ...func_get_args());
        }

        /**
         * Iterate over matching rows in fixed-size pages, without loading every row into memory at once.
         * @return void
         */
        public static function Chunk(mysqli $dbConnection, int $size, callable $callback): void
        {
            parent::ChunkCollection($dbConnection, $size, $callback, ...func_get_args());
        }
    }