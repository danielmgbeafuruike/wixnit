<?php

    namespace Wixnit\Data;

    use Exception;
    use \mysqli;

    class DB
    {
        public mysqli $db;
        public string $tableName = "";
        public array $fields = [];

        /**
         * Create a database connection and set the name of the table on which a transaction will be initiated
         * @param \Wixnit\Data\DBConfig $config
         * @param mixed $table_name
         * @return DB
         */
        public static function Connect(DBConfig $config, $table_name): DB
        {
            $ret = new DB();
            $ret->tableName = (($table_name instanceof ObjectMap) ? strtolower(array_reverse(explode("\\", $table_name->name))[0]) : $table_name);

            if($table_name instanceof ObjectMap)
            {
                for($i = 0; $i < count($table_name->publicProperties); $i++)
                {
                    $ret->fields[] = $table_name->publicProperties[$i]->baseName;
                }
                for($i = 0; $i < count($table_name->hiddenProperties); $i++)
                {
                    $ret->fields[] = $table_name->hiddenProperties[$i]->baseName;
                }
            }


            try{
                $ret->db = $config->getConnection();
            }
            catch (Exception $e)
            {
                throw($e);
            }
            return $ret;
        }

        /**
         * Set the table on which operations will be executed. Should be used only if the DB credentials have been globally set using the DBConfig Class
         * @param mixed $table_name
         * @return DB
         */
        public static function On($table_name): DB
        {
            return DB::Connect(new DBConfig(), $table_name);
        }
    }