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
         * Get data from the db in a DBCollection object filtered and restricted by Filters, Searches, Pagination and other DB result restriciting objects
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

        /**
         * Get the sum of a numeric field across all matching rows.
         * @param string $field
         * @return int|float|string|null
         */
        public static function Sum(string $field): int|float|string|null
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
            return parent::SumValue(($dbConnection != null ? $dbConnection : new DBConfig()), $field, ...func_get_args());
        }

        /**
         * Get the average of a numeric field across all matching rows.
         * @param string $field
         * @return int|float|string|null
         */
        public static function Average(string $field): int|float|string|null
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
            return parent::AverageValue(($dbConnection != null ? $dbConnection : new DBConfig()), $field, ...func_get_args());
        }

        /**
         * Get the minimum value of a field across all matching rows.
         * @param string $field
         * @return int|float|string|null
         */
        public static function Min(string $field): int|float|string|null
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
            return parent::MinValue(($dbConnection != null ? $dbConnection : new DBConfig()), $field, ...func_get_args());
        }

        /**
         * Get the maximum value of a field across all matching rows.
         * @param string $field
         * @return int|float|string|null
         */
        public static function Max(string $field): int|float|string|null
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
            return parent::MaxValue(($dbConnection != null ? $dbConnection : new DBConfig()), $field, ...func_get_args());
        }

        /**
         * Cheaply check whether any row matches, without counting the whole matching set.
         * @return bool
         */
        public static function Exists(): bool
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
            return parent::ExistsCollection(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        /**
         * Get the first matching row hydrated as an object, or null if none match.
         * @return static|null
         */
        public static function First()
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
            return parent::FirstOf(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        /**
         * Get the most recently created matching row.
         * @return static|null
         */
        public static function Latest()
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
            return parent::LatestOf(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        /**
         * Get the oldest matching row.
         * @return static|null
         */
        public static function Oldest()
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
            return parent::OldestOf(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        /**
         * Get a single column across all matching rows, without hydrating full objects.
         * @param string $field
         * @return array
         */
        public static function Pluck(string $field): array
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
            return parent::PluckValue(($dbConnection != null ? $dbConnection : new DBConfig()), $field, ...func_get_args());
        }

        /**
         * Count matching rows grouped by a field.
         * @param string $field
         * @return array
         */
        public static function GroupCount(string $field): array
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
            return parent::GroupCountValue(($dbConnection != null ? $dbConnection : new DBConfig()), $field, ...func_get_args());
        }

        /**
         * Atomically increment a numeric field on every matching row.
         * @param string $field
         * @param int|float $by
         * @return DBResult
         */
        public static function Increment(string $field, int|float $by = 1): DBResult
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
            return parent::IncrementValue(($dbConnection != null ? $dbConnection : new DBConfig()), $field, $by, ...func_get_args());
        }

        /**
         * Atomically decrement a numeric field on every matching row.
         * @param string $field
         * @param int|float $by
         * @return DBResult
         */
        public static function Decrement(string $field, int|float $by = 1): DBResult
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
            return parent::DecrementValue(($dbConnection != null ? $dbConnection : new DBConfig()), $field, $by, ...func_get_args());
        }

        /**
         * Bulk-update every matching row directly in SQL, without a fetch-then-save round trip per row.
         * @param array $data
         * @return DBResult
         */
        public static function UpdateWhere(array $data): DBResult
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
            return parent::UpdateMatching(($dbConnection != null ? $dbConnection : new DBConfig()), $data, ...func_get_args());
        }

        /**
         * Undo a soft delete on every matching row.
         * @return DBResult
         */
        public static function Restore(): DBResult
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
            return parent::RestoreValue(($dbConnection != null ? $dbConnection : new DBConfig()), ...func_get_args());
        }

        /**
         * Iterate over matching rows in fixed-size pages, without loading every row into memory at once.
         * @param int $size
         * @param callable $callback
         * @return void
         */
        public static function Chunk(int $size, callable $callback): void
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
            parent::ChunkCollection(($dbConnection != null ? $dbConnection : new DBConfig()), $size, $callback, ...func_get_args());
        }
    }