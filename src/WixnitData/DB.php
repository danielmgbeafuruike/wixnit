<?php

    namespace Wixnit\Data;
    use \mysqli;

    class DB
    {
        public mysqli $db;
        public string $tableName = "";
        public array $fields = [];

        public static function Connect(DBConfig $config, $table_name): DB
        {
            $ret = new DB();
            $ret->tableName = (($table_name instanceof ObjectMap) ? strtolower(array_reverse(explode("\\", $table_name->Name))[0]) : $table_name);

            if($table_name instanceof ObjectMap)
            {
                for($i = 0; $i < count($table_name->PublicProperties); $i++)
                {
                    $ret->fields[] = $table_name->PublicProperties[$i]->baseName;
                }
                for($i = 0; $i < count($table_name->HiddenProperties); $i++)
                {
                    $ret->fields[] = $table_name->HiddenProperties[$i]->baseName;
                }
            }


            try{
                $ret->db = $config->GetConnection();
            }
            catch (\Exception $e)
            {

            }
            return $ret;
        }

        public static function On($table_name): DB
        {
            return DB::Connect(new DBConfig(), $table_name);
        }
    }