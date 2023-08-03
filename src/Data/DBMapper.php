<?php

    namespace Wixnit\Data;

    use mysqli;

    class DBMapper
    {
        private mysqli $DB;

        function __construct(mysqli $db)
        {
            $this->DB = $db;
        }

        /**
         * @param string $tablename
         * @return DBTable
         * @comment get the current image of the database table
         */
        public function fromTable(string $tablename) : DBTable
        {
            $ret = new DBTable();
            $ret->Name = $tablename;

            $row = $this->DB->query("show columns from ".$tablename.";");

            while(($t = $row->fetch_object()) != null)
            {
                $field = new DBTableField();
                $field->Name = $t->Field;
                $field->Default = $t->Default;
                $field->AutoIncrement = $t->Extra == "auto_increment";
                $field->IsPrimary = strtolower($t->Key) == "pri";
                $field->Type = $this->ripType($t->Type);
                $field->IsUnique = strtolower($t->Key) == "uni";
                $field->IsNull = !(strtolower($t->Null) == "no");
                $field->IsIndex = strtolower($t->Key == "mul");

                $ret->AddField($field);
            }
            return  $ret;
        }

        /**
         * @param DBTable $tableImage
         * @return void
         * @comment force a table to take the supplied image. The table will be created if it does not exist
         */
        public function toTable(DBTable $tableImage)
        {
            if($this->tableExists($tableImage->Name))
            {
                $currentImage = $this->fromTable($tableImage->Name);

                //first do the renaming
                for($i = 0; $i < count($currentImage->Fields); $i++)
                {
                    for($j = 0; $j < count($tableImage->ColumnSwitches); $j++)
                    {
                        if($currentImage->Fields[$i]->Name == $tableImage->ColumnSwitches[$j]['old'])
                        {
                            $this->renameColumn($currentImage->Name, $currentImage->Fields[$i], $tableImage->ColumnSwitches[$j]['new']);
                            break;
                        }
                    }
                }

                //compare field types and adjust em
                $testImage = $this->fromTable($tableImage->Name);
                for($i = 0; $i < count($tableImage->Fields); $i++)
                {
                    if($testImage->HasField($tableImage->Fields[$i]->Name))
                    {
                        $f = $testImage->getField($tableImage->Fields[$i]->Name);

                        //compare their types
                        if($f->Type != $tableImage->Fields[$i]->Type)
                        {
                            $this->changeColumnType($tableImage->Name, $tableImage->Fields[$i]->Name, $tableImage->Fields[$i]->getDBType());
                        }

                        //check for unique keys
                        if($f->IsUnique != $tableImage->Fields[$i]->IsUnique)
                        {
                            if($tableImage->Fields[$i]->IsUnique)
                            {
                                $this->addUniqueIndex($tableImage->Name, $tableImage->Fields[$i]->Name);
                            }
                            else
                            {
                                $this->removeUniqueIndex($tableImage->Name, $tableImage->Fields[$i]->Name);
                            }
                        }

                        //check auto incrementing field
                        if($f->AutoIncrement != $tableImage->Fields[$i]->AutoIncrement)
                        {
                            if($tableImage->Fields[$i]->AutoIncrement)
                            {
                                $this->setAutoIncrement($tableImage->Name, $tableImage->Fields[$i]->Name);
                            }
                            else
                            {
                                $this->removeAutoIncrement($tableImage->Name, $tableImage->Fields[$i]->Name);
                            }
                        }


                        //check primary key field
                        if($f->IsPrimary != $tableImage->Fields[$i]->IsPrimary)
                        {
                            $this->removePrimaryKey($tableImage->Name);

                            if($tableImage->Fields[$i]->IsPrimary)
                            {
                                $this->setPrimaryKey($tableImage->Name, $tableImage->Fields[$i]->Name);
                            }
                        }
                    }
                }

                //check and add the new fields
                for($i = 0; $i < count($tableImage->Fields); $i++)
                {
                    if(!$currentImage->HasField($tableImage->Fields[$i]->Name))
                    {
                        $this->createColumn($tableImage->Name, $tableImage->Fields[$i]);
                    }
                }

                //remove deprecated fields from the table
                for($i = 0; $i < count($currentImage->Fields); $i++)
                {
                    if(!$tableImage->HasField($currentImage->Fields[$i]->Name))
                    {
                        $this->dropColumn($currentImage->Name, $currentImage->Fields[$i]->Name);
                    }
                }
            }
            else
            {
                $this->createTable($tableImage);
            }
        }

        /**
         * @param string $tableName
         * @return bool
         * @comment check if table exists on the connected DB
         */
        public  function tableExists(string $tableName) : bool
        {
            //$v =  $this->DB->query("SELECT 1 FROM ".$tableName." LIMIT 1");
            //return ($v != false);

            //$v = $this->DB->query("SELECT count((1)) as `ct` FROM INFORMATION_SCHEMA.TABLES WHERE table_schema='$tableName'");
            //return $v->fetch_array()[0] > 0;

            $v = $this->DB->query("SHOW TABLES LIKE '$tableName'");
            return $v->num_rows > 0;
        }

        /**
         * @param string $tableName
         * @return void
         * @comment drop a table on the connected DB
         */
        public function dropTable(string $tableName)
        {
            $this->DB->query("DROP TABLE IF EXISTS ".$tableName);
        }

        /**
         * @param string $tableName
         * @return void
         * @comment remove a column from being the primary key on the database
         */
        public function removePrimaryKey(string $tableName)
        {
            $this->DB->query("ALTER TABLE ".$tableName." DROP PRIMARY KEY");
        }

        /**
         * @param string $tableName
         * @param string $columnName
         * @param string $type
         * @return void
         * @comment remove auto increment directive from a column on the table
         */
        public function removeAutoIncrement(string $tableName, string $columnName)
        {
            $this->DB->query("ALTER TABLE ".$tableName." MODIFY ".$columnName." NOT NULL");
        }

        /**
         * @param string $tableName
         * @param string $columnName
         * @return void
         * @comment remove unique index from a column on the table
         */
        public function removeUniqueIndex(string $tableName, string $columnName)
        {
            $this->DB->query("ALTER TABLE ".$tableName." DROP INDEX ".$columnName);
        }

        /**
         * @param string $tableName
         * @param string $columnName
         * @return void
         * @comment set a column as primary key on a table
         */
        public function setPrimaryKey(string $tableName, string $columnName)
        {
            $this->DB->query("ALTER TABLE ".$tableName." ADD PRIMARY KEY(".$columnName.")");
        }

        /**
         * @param string $tableName
         * @param string $columnName
         * @return void
         *
         */
        public  function setAutoIncrement(string $tableName, string $columnName)
        {
            $this->DB->query("ALTER TABLE ".$tableName." MODIFY ".$columnName." INT NOT NULL AUTO_INCREMENT");
        }

        /**
         * @param string $tableName
         * @param string $columnName
         * @return void
         * @comment set a column on the table as unique
         */
        public function addUniqueIndex(string $tableName, string $columnName)
        {
            $this->DB->query("ALTER TABLE ".$tableName." ADD UNIQUE(".$columnName.")");
        }

        /**
         * @param string $tableName
         * @return void
         * @comment empty selected table and set it's increment pointer to zero
         */
        public  function emptyTable(string $tableName)
        {
            $this->DB->query("TRUNCATE TABLE ".$tableName);
        }

        /**
         * @param string $tableName
         * @param DBTableField $field
         * @return void
         * @comment create table from image if it does not exist
         */
        public function createColumn(string $tableName, DBTableField $field)
        {
            $this->DB->query("ALTER TABLE ".$tableName." ADD ".$field->Name." ".$field->getDBType()." NOT NULL");
        }

        /**
         * @param string $tableName
         * @param string $fieldName
         * @return void
         * @comment drop a single field on a database
         */
        public function dropColumn(string $tableName, string $fieldName)
        {
            $this->DB->query("ALTER TABLE ".$tableName." DROP COLUMN ".$fieldName);
        }

        /**
         * @param string $tableName
         * @param string $oldName
         * @param string $newName
         * @return void
         * @comment change the name of a column on the table
         */
        public function renameColumn(string $tableName, string $oldName, string $newName)
        {
            $this->DB->query("ALTER TABLE ".$tableName." RENAME COLUMN ".$oldName." TO ".$newName);
        }

        /**
         * @param string $tableName
         * @param string $columnName
         * @param string $newType
         * @return void
         * @comment change the type the column keeps to another
         */
        public function changeColumnType(string $tableName, string $columnName, string $newType)
        {
            $this->DB->query("ALTER TABLE ".$tableName." ALTER COLUMN ".$columnName." ".$newType);
        }

        /**
         * @param DBTable $tableImage
         * @return void
         * @comment add a field to a table from image
         */
        public function createTable(DBTable $tableImage)
        {
            $query = "CREATE TABLE IF NOT EXISTS ".$tableImage->Name." (";

            //add fields
            for($i = 0; $i < count($tableImage->Fields); $i++)
            {
                $query .= (($i > 0) ? ", " : ""). $tableImage->Fields[$i]->Name." ".$tableImage->Fields[$i]->getDBType()." NOT NULL ".($tableImage->Fields[$i]->AutoIncrement ? "AUTO_INCREMENT" : "");
            }
            if($tableImage->hasPrimaryKey())
            {
                $query .= ", primary key ( ".$tableImage->getPrimaryKey()->Name." )";
            }
            $query .= ")";

            $this->DB->query($query);
        }



        //private methods for doing database operations

        private function ripType(string  $def)
        {
            $t = explode("(", $def)[0];

            if(strtolower($t) == "int")
            {
                return DBFieldType::Int;
            }
            if(strtolower($t) == "varchar")
            {
                return DBFieldType::Varchar;
            }
            if(strtolower($t) == "text")
            {
                return DBFieldType::Text;
            }
            if(strtolower($t) == "date")
            {
                return DBFieldType::Date;
            }
            if(strtolower($t) == "float")
            {
                return DBFieldType::Float;
            }
            if(strtolower($t) == "double")
            {
                return DBFieldType::Double;
            }
            if(strtolower($t) == "decimal")
            {
                return DBFieldType::Decimal;
            }
            if(strtolower($t) == "enum")
            {
                return DBFieldType::Enum;
            }
            if(strtolower($t) == "char")
            {
                return DBFieldType::Char;
            }
            if(strtolower($t) == "current_timestamp")
            {
                return DBFieldType::TimeStamp;
            }
            if(strtolower($t) == "longtext")
            {
                return DBFieldType::LongText;
            }
            if(strtolower($t) == "tinyint")
            {
                return DBFieldType::TinyInt;
            }
            if(strtolower($t) == "blob")
            {
                return DBFieldType::Blob;
            }
            if(strtolower($t) == "bigint")
            {
                return DBFieldType::BigInt;
            }
            if(strtolower($t) == "bit")
            {
                return DBFieldType::Bit;
            }
            if(strtolower($t) == "longblob")
            {
                return DBFieldType::LongBlob;
            }
            if(strtolower($t) == "year")
            {
                return DBFieldType::Year;
            }
            if(strtolower($t) == "time")
            {
                return DBFieldType::Time;
            }
            if(strtolower($t) == "set")
            {
                return DBFieldType::Set;
            }
            if(strtolower($t) == "geometry")
            {
                return DBFieldType::Geometry;
            }
        }

        private function ripLength(string $def): ?int
        {
            $ret = null;
            $type = $this->ripType($def);

            if(($type != DBFieldType::Char) || ($type != DBFieldType::Varchar))
            {
                $t = explode("(", $def);

                if(count($t) == 2)
                {
                    $g = explode(")", $t[1]);

                    if(count($g) == 2)
                    {
                        $ret = intval($g[0]);
                    }
                }
            }
            return $ret;
        }
    }