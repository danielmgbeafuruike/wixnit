<?php

    namespace Wixnit\Exception;

    use Exception;

    class DatabaseException extends Exception
    {
        public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }

        public static function NotFound(string $table, string $field, string $value): self
        {
            return new self("No record found in table '$table' with field '$field' having value '$value'.");
        }

        public static function InvalidQuery(string $query): self
        {
            return new self("The provided query is invalid: '$query'.");
        }

        public static function ConnectionFailed(string $host, int $port): self
        {
            return new self("Failed to connect to the database at '$host:$port'.");
        }

        public static function QueryExecutionFailed(string $query, string $error): self
        {
            return new self("Query execution failed for query '$query'. Error: '$error'.");
        }

        public static function TransactionFailed(string $error): self
        {
            return new self("Transaction failed with error: '$error'.");
        }

        public static function MissingCredentials(): self
        {
            return new self("Database connection credentials are missing.");
        }

        public static function UnsupportedDatabaseType(string $type): self
        {
            return new self("Unsupported database type: '$type'.");
        }

        public static function InvalidDatabaseConfig(string $config): self
        {
            return new self("Invalid database configuration: '$config'.");
        }

        public static function DuplicateEntry(string $table, string $field, string $value): self
        {
            return new self("Duplicate entry found in table '$table' for field '$field' with value '$value'.");
        }

        public static function TransactionAlreadyStarted(): self
        {
            return new self("A transaction is already in progress.");
        }

        public static function TransactionNotStarted(): self
        {
            return new self("No transaction has been started.");
        }

        public static function InvalidDataType(string $type): self
        {
            return new self("Invalid data type provided: '$type'.");
        }

        public static function QueryTimeout(string $query, int $timeout): self
        {
            return new self("Query '$query' timed out after $timeout seconds.");
        }

        public static function MissingRequiredField(string $table, string $field): self
        {
            return new self("Missing required field '$field' in table '$table'.");
        }

        public static function InvalidFieldType(string $field, string $expectedType, string $actualType): self
        {
            return new self("Invalid type for field '$field'. Expected '$expectedType', got '$actualType'.");
        }

        public static function UnsupportedQueryOperation(string $operation): self
        {
            return new self("Unsupported query operation: '$operation'.");
        }

        public static function InvalidDatabaseName(string $name): self
        {
            return new self("Invalid database name: '$name'.");
        }

        public static function InvalidTableName(string $name): self
        {
            return new self("Invalid table name: '$name'.");
        }

        public static function InvalidFieldName(string $name): self
        {
            return new self("Invalid field name: '$name'.");
        }

        public static function InvalidIndexName(string $name): self
        {
            return new self("Invalid index name: '$name'.");
        }

        public static function InvalidForeignKey(string $table, string $field): self
        {
            return new self("Invalid foreign key reference in table '$table' for field '$field'.");
        }

        public static function InvalidPrimaryKey(string $table, string $field): self
        {
            return new self("Invalid primary key reference in table '$table' for field '$field'.");
        }

        public static function InvalidSchema(string $schema): self
        {
            return new self("Invalid database schema: '$schema'.");
        }

        public static function InvalidConnectionString(string $connectionString): self
        {
            return new self("Invalid database connection string: '$connectionString'.");
        }

        public static function UnsupportedDatabaseVersion(string $version): self
        {
            return new self("Unsupported database version: '$version'.");
        }

        public static function InvalidQueryParameter(string $parameter): self
        {
            return new self("Invalid query parameter: '$parameter'.");
        }
        public static function QueryNotPrepared(string $query): self
        {
            return new self("Query not prepared: '$query'. Ensure you prepare the query before execution.");
        }
        public static function InvalidQueryResult(string $query): self
        {
            return new self("Invalid result set for query: '$query'. Ensure the query returns a valid result.");
        }
        public static function MissingDatabaseDriver(string $driver): self
        {
            return new self("Missing database driver: '$driver'. Ensure the driver is installed and configured.");
        }

        public static function InvalidTransactionState(string $state): self
        {
            return new self("Invalid transaction state: '$state'. Ensure the transaction is in a valid state before proceeding.");
        }

        public static function UnsupportedQueryType(string $type): self
        {
            return new self("Unsupported query type: '$type'. Ensure the query type is supported by the database.");
        }

        public static function InvalidQuerySyntax(string $query): self
        {
            return new self("Invalid query syntax: '$query'. Ensure the query is correctly formatted.");
        }

        public static function QueryExecutionInterrupted(string $query): self
        {
            return new self("Query execution interrupted: '$query'. Ensure the query is not being blocked or interrupted.");
        }

        public static function InvalidConnectionState(string $state): self
        {
            return new self("Invalid connection state: '$state'. Ensure the database connection is properly established.");
        }

        public static function UnsupportedDataType(string $type): self
        {
            return new self("Unsupported data type: '$type'. Ensure the data type is supported by the database.");
        }

        public static function InvalidQueryParameterType(string $parameter, string $expectedType, string $actualType): self
        {
            return new self("Invalid type for query parameter '$parameter'. Expected '$expectedType', got '$actualType'.");
        }

        public static function QueryExecutionFailedWithError(string $query, string $error): self
        {
            return new self("Query execution failed for query '$query'. Error: '$error'.");
        }

        public static function InvalidQueryResultSet(string $query): self
        {
            return new self("Invalid result set for query: '$query'. Ensure the query returns a valid result set.");
        }

        public static function UnsupportedQueryFeature(string $feature): self
        {
            return new self("Unsupported query feature: '$feature'. Ensure the feature is supported by the database.");
        }

        public static function InvalidQueryExecution(string $query): self
        {
            return new self("Invalid query execution for query: '$query'. Ensure the query is correctly formatted and executable.");
        }

        public static function MissingDatabaseConfiguration(): self
        {
            return new self("Missing database configuration. Ensure the database configuration is properly set.");
        }

        public static function InvalidDatabaseConnection(string $host, int $port): self
        {
            return new self("Invalid database connection to '$host:$port'. Ensure the connection parameters are correct.");
        }

        public static function UnsupportedQueryOperationType(string $operation): self
        {
            return new self("Unsupported query operation type: '$operation'. Ensure the operation is supported by the database.");
        }

        public static function InvalidQueryExecutionState(string $state): self
        {
            return new self("Invalid query execution state: '$state'. Ensure the query is in a valid state before execution.");
        }

        public static function QueryExecutionTimeout(string $query, int $timeout): self
        {
            return new self("Query '$query' execution timed out after $timeout seconds. Consider optimizing the query or increasing the timeout limit.");
        }

        public static function InvalidQueryParameterValue(string $parameter, string $value): self
        {
            return new self("Invalid value for query parameter '$parameter': '$value'. Ensure the value is correctly formatted and valid.");
        }

        public static function UnsupportedDatabaseFeature(string $feature): self
        {
            return new self("Unsupported database feature: '$feature'. Ensure the feature is supported by the database version.");
        }

        public static function InvalidQueryExecutionParameters(string $query, array $parameters): self
        {
            return new self("Invalid parameters for query '$query': ".json_encode($parameters).". Ensure the parameters are correctly formatted and valid.");
        }

        public static function QueryExecutionFailedWithException(string $query, \Throwable $exception): self
        {
            return new self("Query execution failed for query '$query'. Exception: ".$exception->getMessage());
        }

        public static function InvalidDatabaseSchema(string $schema): self
        {
            return new self("Invalid database schema: '$schema'. Ensure the schema is correctly defined and valid.");
        }

        public static function UnsupportedDatabaseOperation(string $operation): self
        {
            return new self("Unsupported database operation: '$operation'. Ensure the operation is supported by the database.");
        }

        public static function InvalidDatabaseQuery(string $query): self
        {
            return new self("Invalid database query: '$query'. Ensure the query is correctly formatted and valid.");
        }

        public static function QueryExecutionFailedWithCode(string $query, int $code): self
        {
            return new self("Query execution failed for query '$query'. Error code: $code.");
        }

        public static function InvalidDatabaseTransaction(string $transaction): self
        {
            return new self("Invalid database transaction: '$transaction'. Ensure the transaction is correctly defined and valid.");
        }

        public static function InvalidDatabaseConnectionParameters(array $params): self
        {
            return new self("Invalid database connection parameters: ".json_encode($params).". Ensure the parameters are correctly defined and valid.");
        }

        public static function QueryExecutionFailedWithMessage(string $query, string $message): self
        {
            return new self("Query execution failed for query '$query'. Message: '$message'.");
        }

        public static function InvalidDatabaseQueryResult(string $query): self
        {
            return new self("Invalid result for query: '$query'. Ensure the query returns a valid result set.");
        }

        public static function UnsupportedDatabaseQueryFeature(string $feature): self
        {
            return new self("Unsupported database query feature: '$feature'. Ensure the feature is supported by the database.");
        }

        public static function InvalidDatabaseQueryExecution(string $query): self
        {
            return new self("Invalid execution for query: '$query'. Ensure the query is correctly formatted and executable.");
        }

        public static function MissingDatabaseCredentials(): self
        {
            return new self("Missing database credentials. Ensure the database credentials are properly set.");
        }

        public static function InvalidDatabaseConnectionString(string $connectionString): self
        {
            return new self("Invalid database connection string: '$connectionString'. Ensure the connection string is correctly formatted.");
        }

        public static function UnsupportedDatabaseQueryType(string $type): self
        {
            return new self("Unsupported database query type: '$type'. Ensure the query type is supported by the database.");
        }

        public static function InvalidDatabaseQueryParameter(string $parameter): self
        {
            return new self("Invalid database query parameter: '$parameter'. Ensure the parameter is correctly defined and valid.");
        }

        public static function QueryExecutionFailedWithDatabaseError(string $query, string $error): self
        {
            return new self("Query execution failed for query '$query'. Database error: '$error'.");
        }

        public static function InvalidDatabaseQuerySyntax(string $query): self
        {
            return new self("Invalid database query syntax: '$query'. Ensure the query is correctly formatted and valid.");
        }

        public static function QueryExecutionInterruptedByUser(string $query): self
        {
            return new self("Query execution for '$query' was interrupted by the user. Ensure the query is not being blocked or interrupted.");
        }

        public static function InvalidDatabaseConnectionState(string $state): self
        {
            return new self("Invalid database connection state: '$state'. Ensure the database connection is properly established and valid.");
        }

        public static function UnsupportedDatabaseDataType(string $type): self
        {
            return new self("Unsupported database data type: '$type'. Ensure the data type is supported by the database.");
        }

        public static function InvalidDatabaseQueryParameterType(string $parameter, string $expectedType, string $actualType): self
        {
            return new self("Invalid type for database query parameter '$parameter'. Expected '$expectedType', got '$actualType'.");
        }

        public static function QueryExecutionFailedWithDatabaseException(string $query, \Throwable $exception): self
        {
            return new self("Query execution failed for query '$query'. Database exception: ".$exception->getMessage());
        }

        public static function InvalidDatabaseQueryResultSet(string $query): self
        {
            return new self("Invalid result set for database query: '$query'. Ensure the query returns a valid result set.");
        }
    }