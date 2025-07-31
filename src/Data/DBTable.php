<?php

    namespace Wixnit\Data;

    class DBTable
    {
        public string $name = "";

        /**
         * List of the table fields
         * @var DBTableField[]
         */
        public array $fields = [];
        public array $columnSwitches = [];

        /**
         * Set the primary key for the table.
         * This will set the specified field as the primary key and unset all others.
         * @param string $fieldName
         * @return void
         */
        public function setPrimaryKey(string $fieldName): void
        {
            for($i = 0; $i < count($this->fields); $i++)
            {
                if($this->fields[$i]->name == $fieldName)
                {
                    $this->fields[$i]->isKey = true;
                }
                else
                {
                    $this->fields[$i]->isKey = false;
                }
            }
        }

        /**
         * Add a field to the table
         * @param DBTableField $field
         * @return void
         */
        public function addField(DBTableField $field): void
        {
            $this->fields[] = $field;
        }

        /**
         * Set the auto-increment field for the table
         * @param string $fieldName
         * @return void
         */
        public function setAutoIncrement(string $fieldName): void
        {
            for($i = 0; $i < count($this->fields); $i++)
            {
                if($this->fields[$i]->name == $fieldName)
                {
                    $this->fields[$i]->isKey = true;
                    $this->fields[$i]->autoIncrement = true;
                }
            }
        }

        /**
         * Check if the table has a field with the specified name
         * @param string $fieldName
         * @return bool
         */
        public function hasField(string $fieldName): bool
        {
            for($i = 0; $i < count($this->fields); $i++)
            {
                if($this->fields[$i]->name == $fieldName)
                {
                    return true;
                }
            }
            return false;
        }

        /**
         * Rename a column in the table
         * @param string $oldName
         * @param DBTableField $newColumn
         */
        public function renameColumn(string $oldName, DBTableField $newColumn)
        {
            $this->columnSwitches[] = ["old"=>$oldName, "new"=>$newColumn];
        }

        /**
         * Check if the table has a primary key
         * @return bool
         */
        public function hasPrimaryKey(): bool
        {
            for($i = 0; $i < count($this->fields); $i++)
            {
                if($this->fields[$i]->isPrimary)
                {
                    return true;
                }
            }
            return false;
        }

        /**
         * Get the primary key field of the table
         * @return DBTableField|null
         */
        public function getPrimaryKey(): ?DBTableField
        {
            for($i = 0; $i < count($this->fields); $i++)
            {
                if($this->fields[$i]->isPrimary)
                {
                    return $this->fields[$i];
                }
            }
            return null;
        }

        /**
         * Get a field by its name
         * @param string $name
         * @return DBTableField|null
         */
        public function getField(string $name): ?DBTableField
        {
            for($i = 0; $i < count($this->fields); $i++)
            {
                if($this->fields[$i]->name == $name)
                {
                    return $this->fields[$i];
                }
            }
            return null;
        }
    }