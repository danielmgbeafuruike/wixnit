<?php

    namespace Wixnit\Data;

    use mysqli;
    use Wixnit\Enum\DBFieldType;

    class dbMapper
    {
        private mysqli $db;

        function __construct(mysqli $db)
        {
            $this->db = $db;
        }

        /**
         * @param string $tablename
         * @return DBTable
         * @comment get the current image of the database table
         */
        public function fromTable(string $tablename) : DBTable
        {
            $ret = new DBTable();
            $ret->name = $tablename;

            $row = $this->db->query("show columns from ".$tablename.";");

            while(($t = $row->fetch_object()) != null)
            {
                $field = new DBTableField();
                $field->name = $t->Field;
                $field->default = $t->Default;
                $field->autoIncrement = $t->Extra == "auto_increment";
                $field->isPrimary = strtolower($t->Key) == "pri";
                $field->type = $this->ripType($t->Type);
                $field->isUnique = strtolower($t->Key) == "uni";
                $field->isNull = !(strtolower($t->Null) == "no");
                $field->isIndex = strtolower($t->Key) == "mul";

                $ret->addField($field);
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
            if($this->tableExists($tableImage->name))
            {
                $currentImage = $this->fromTable($tableImage->name);

                //first do the renaming
                for($i = 0; $i < count($currentImage->fields); $i++)
                {
                    for($j = 0; $j < count($tableImage->columnSwitches); $j++)
                    {
                        if($currentImage->fields[$i]->name == $tableImage->columnSwitches[$j]['old'])
                        {
                            $this->renameColumn($currentImage->name, $currentImage->fields[$i], $tableImage->columnSwitches[$j]['new']);
                            break;
                        }
                    }
                }

                //compare field types and adjust em
                $testImage = $this->fromTable($tableImage->name);
                for($i = 0; $i < count($tableImage->fields); $i++)
                {
                    if($testImage->HasField($tableImage->fields[$i]->name))
                    {
                        $f = $testImage->getField($tableImage->fields[$i]->name);

                        //compare their types
                        if($f->type != $tableImage->fields[$i]->type)
                        {
                            $this->changeColumnType($tableImage->name, $tableImage->fields[$i]->name, $tableImage->fields[$i]->getdbType());
                        }

                        //check for unique keys
                        if($f->isUnique != $tableImage->fields[$i]->isUnique)
                        {
                            if($tableImage->fields[$i]->isUnique)
                            {
                                $this->addUniqueIndex($tableImage->name, $tableImage->fields[$i]->name);
                            }
                            else
                            {
                                $this->removeUniqueIndex($tableImage->name, $tableImage->fields[$i]->name);
                            }
                        }

                        //check auto incrementing field
                        if($f->autoIncrement != $tableImage->fields[$i]->autoIncrement)
                        {
                            if($tableImage->fields[$i]->autoIncrement)
                            {
                                $this->setAutoIncrement($tableImage->name, $tableImage->fields[$i]->name);
                            }
                            else
                            {
                                $this->removeAutoIncrement($tableImage->name, $tableImage->fields[$i]->name, $tableImage->fields[$i]->type);
                            }
                        }

                        //check primary key field
                        if($f->isPrimary != $tableImage->fields[$i]->isPrimary)
                        {
                            if($tableImage->fields[$i]->isPrimary)
                            {
                                $this->setPrimaryKey($tableImage->name, $tableImage->fields[$i]->name);
                            }
                            else
                            {
                                $this->removeAutoIncrement($tableImage->name, $tableImage->fields[$i]->name, $tableImage->fields[$i]->type);
                                //$this->removePrimaryKey($tableImage->name);
                            }
                        }
                    }
                }

                //check and add the new fields
                for($i = 0; $i < count($tableImage->fields); $i++)
                {
                    if(!$currentImage->HasField($tableImage->fields[$i]->name))
                    {
                        $this->createColumn($tableImage->name, $tableImage->fields[$i]);
                    }
                }

                //remove deprecated fields from the table
                for($i = 0; $i < count($currentImage->fields); $i++)
                {
                    if(!$tableImage->HasField($currentImage->fields[$i]->name))
                    {
                        $this->dropColumn($currentImage->name, $currentImage->fields[$i]->name);
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
         * @comment check if table exists on the connected db
         */
        public  function tableExists(string $tableName) : bool
        {
            //$v =  $this->db->query("SELECT 1 FROM ".$tableName." LIMIT 1");
            //return ($v != false);

            //$v = $this->db->query("SELECT count((1)) as `ct` FROM INFORMATION_SCHEMA.TABLES WHERE table_schema='$tableName'");
            //return $v->fetch_array()[0] > 0;

            $v = $this->db->query("SHOW TABLES LIKE '$tableName'");
            return $v->num_rows > 0;
        }

        /**
         * @param string $tableName
         * @return void
         * @comment drop a table on the connected db
         */
        public function dropTable(string $tableName)
        {
            $this->db->query("DROP TABLE IF EXISTS ".$tableName);
        }

        /**
         * @param string $tableName
         * @return void
         * @comment remove a column from being the primary key on the database
         */
        public function removePrimaryKey(string $tableName)
        {
            if($this->hasPrimaryKey($tableName))
            {
                $this->db->query("ALTER TABLE ".$tableName." DROP PRIMARY KEY");
            }
        }

        /**
         * @param string $tableName
         * @param string $columnName
         * @param string $type
         * @return void
         * @comment remove auto increment directive from a column on the table
         */
        public function removeAutoIncrement(string $tableName, string $columnName, string $columnType)
        {
            if(strtolower($columnType) == "int")
            {
                $this->db->query("ALTER TABLE ".$tableName." MODIFY ".$columnName." ".strtoupper($columnType)." NOT NULL");
            }
        }

        /**
         * @param string $tableName
         * @param string $columnName
         * @return void
         * @comment remove unique index from a column on the table
         */
        public function removeUniqueIndex(string $tableName, string $columnName)
        {
            $this->db->query("ALTER TABLE ".$tableName." DROP INDEX ".$columnName);
        }

        /**
         * @param string $tableName
         * @param string $columnName
         * @return void
         * @comment set a column as primary key on a table
         */
        public function setPrimaryKey(string $tableName, string $columnName)
        {
            if($this->hasPrimaryKey($tableName))
            {
                $this->db->query("ALTER TABLE ".$tableName." DROP PRIMARY KEY, ADD PRIMARY KEY(".$columnName.")");
            }
            else
            {
                $this->db->query("ALTER TABLE ".$tableName." ADD PRIMARY KEY(".$columnName.")");
            }
        }

        /**
         * @param string $tableName
         * @param string $columnName
         * @return void
         *
         */
        public  function setAutoIncrement(string $tableName, string $columnName)
        {
            if($this->hasColumn($tableName, $columnName))
            {
                if($this->hasPrimaryKey($tableName))
                {
                    $this->db->query("ALTER TABLE ".$tableName." MODIFY COLUMN ".$columnName." INT NOT NULL AUTO_INCREMENT");
                }
                else
                {
                    $this->db->query("ALTER TABLE ".$tableName." MODIFY COLUMN ".$columnName." INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
                }
            }
            else
            {
                $this->db->query("ALTER TABLE ".$tableName." ADD COLUMN ".$columnName." INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
            }
        }

        /**
         * @param string $tableName
         * @param string $columnName
         * @return void
         * @comment set a column on the table as unique
         */
        public function addUniqueIndex(string $tableName, string $columnName)
        {
            $this->db->query("ALTER TABLE ".$tableName." ADD UNIQUE(".$columnName.")");
        }

        /**
         * @param string $tableName
         * @return void
         * @comment empty selected table and set it's increment pointer to zero
         */
        public  function emptyTable(string $tableName)
        {
            $this->db->query("TRUNCATE TABLE ".$tableName);
        }

        /**
         * @param string $tableName
         * @param DBTableField $field
         * @return void
         * @comment create table from image if it does not exist
         */
        public function createColumn(string $tableName, DBTableField $field)
        {
            $this->db->query("ALTER TABLE ".$tableName." ADD ".$field->name." ".$field->getdbType()." NOT NULL");
        }

        /**
         * @param string $tableName
         * @param string $fieldName
         * @return void
         * @comment drop a single field on a database
         */
        public function dropColumn(string $tableName, string $fieldName)
        {
            $this->db->query("ALTER TABLE ".$tableName." DROP COLUMN ".$fieldName);
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
            $this->db->query("ALTER TABLE ".$tableName." RENAME COLUMN ".$oldName." TO ".$newName);
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
            $this->db->query("ALTER TABLE ".$tableName." MODIFY COLUMN ".$columnName." ".$newType);
        }

        /**
         * @param DBTable $tableImage
         * @return void
         * @comment add a field to a table from image
         */
        public function createTable(DBTable $tableImage)
        {
            $query = "CREATE TABLE IF NOT EXISTS ".$tableImage->name." (";

            //add fields
            for($i = 0; $i < count($tableImage->fields); $i++)
            {
                $query .= (($i > 0) ? ", " : ""). $tableImage->fields[$i]->name." ".$tableImage->fields[$i]->getdbType()." NOT NULL ".($tableImage->fields[$i]->autoIncrement ? "AUTO_INCREMENT" : "");
            }
            if($tableImage->hasPrimaryKey())
            {
                $query .= ", primary key ( ".$tableImage->getPrimaryKey()->name." )";
            }
            $query .= ")";

            $this->db->query($query);
        }


        //check if table has a primary key
        public function hasPrimaryKey(string $tableName)
        {
            //$v = $this->db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_TYPE = 'PRIMARY KEY' AND TABLE_NAME='$tableName'");
            $v = $this->db->query("SHOW KEYS FROM $tableName WHERE Key_name = 'PRIMARY'");
            return $v->num_rows > 0;
        }

        //check if table has a column
        public function hasColumn(string $tableName, $columnName)
        {
            $v = $this->db->query("SHOW COLUMNS FROM ".$tableName." LIKE '".$columnName."'");
            return $v->num_rows > 0;
        }



        #region private methods for doing database operations

        private function ripType(string  $def): DBFieldType
        {
            $t = explode("(", $def)[0];

            if(strtolower($t) == "int")
            {
                return DBFieldType::INT;
            }
            if(strtolower($t) == "varchar")
            {
                return DBFieldType::VARCHAR;
            }
            if(strtolower($t) == "text")
            {
                return DBFieldType::TEXT;
            }
            if(strtolower($t) == "date")
            {
                return DBFieldType::DATE;
            }
            if(strtolower($t) == "float")
            {
                return DBFieldType::FLOAT;
            }
            if(strtolower($t) == "double")
            {
                return DBFieldType::DOUBLE;
            }
            if(strtolower($t) == "decimal")
            {
                return DBFieldType::DECIMAL;
            }
            if(strtolower($t) == "enum")
            {
                return DBFieldType::ENUM;
            }
            if(strtolower($t) == "char")
            {
                return DBFieldType::CHAR;
            }
            if(strtolower($t) == "current_timestamp")
            {
                return DBFieldType::TIME_STAMP;
            }
            if(strtolower($t) == "longtext")
            {
                return DBFieldType::LONG_TEXT;
            }
            if(strtolower($t) == "tinyint")
            {
                return DBFieldType::TINY_INT;
            }
            if(strtolower($t) == "blob")
            {
                return DBFieldType::BLOB;
            }
            if(strtolower($t) == "bigint")
            {
                return DBFieldType::BIG_INT;
            }
            if(strtolower($t) == "bit")
            {
                return DBFieldType::BIT;
            }
            if(strtolower($t) == "longblob")
            {
                return DBFieldType::LONG_BLOB;
            }
            if(strtolower($t) == "year")
            {
                return DBFieldType::YEAR;
            }
            if(strtolower($t) == "time")
            {
                return DBFieldType::TIME;
            }
            if(strtolower($t) == "set")
            {
                return DBFieldType::SET;
            }
            if(strtolower($t) == "geometry")
            {
                return DBFieldType::GEOMETRY;
            }
            return DBFieldType::VARCHAR;
        }

        private function ripLength(string $def): ?int
        {
            $ret = null;
            $type = $this->ripType($def);

            if(($type != DBFieldType::CHAR) || ($type != DBFieldType::VARCHAR))
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
        #endregion
    }