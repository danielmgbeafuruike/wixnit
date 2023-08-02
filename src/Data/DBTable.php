<?php

    namespace Wixnit\Data;

    class DBTable
    {
        public string $Name = "";
        public array $Fields = [];
        public array $ColumnSwitches = [];

        public function SetPrimaryKey(string $fieldName): void
        {
            for($i = 0; $i < count($this->Fields); $i++)
            {
                if($this->Fields[$i]->Name == $fieldName)
                {
                    $this->Fields[$i]->IsKey = true;
                }
                else
                {
                    $this->Fields[$i]->IsKey = false;
                }
            }
        }

        public function AddField(DBTableField $field): void
        {
            $this->Fields[] = $field;
        }

        public function SetAutoIncrement(string $fieldName): void
        {
            for($i = 0; $i < count($this->Fields); $i++)
            {
                if($this->Fields[$i]->Name == $fieldName)
                {
                    $this->Fields[$i]->IsKey = true;
                }
            }
        }

        public function HasField(string $fieldName): bool
        {
            for($i = 0; $i < count($this->Fields); $i++)
            {
                if($this->Fields[$i]->Name == $fieldName)
                {
                    return true;
                }
            }
            return false;
        }

        public function RenameColumn(string $oldName, DBTableField $newColumn)
        {
            $this->ColumnSwitches[] = ["old"=>$oldName, "new"=>$newColumn];
        }

        public function hasPrimaryKey(): bool
        {
            for($i = 0; $i < count($this->Fields); $i++)
            {
                if($this->Fields[$i]->IsPrimary)
                {
                    return true;
                }
            }
            return false;
        }

        public function getPrimaryKey(): ?DBTableField
        {
            for($i = 0; $i < count($this->Fields); $i++)
            {
                if($this->Fields[$i]->IsPrimary)
                {
                    return $this->Fields[$i];
                }
            }
            return null;
        }

        public function getField(string $name): ?DBTableField
        {
            for($i = 0; $i < count($this->Fields); $i++)
            {
                if($this->Fields[$i]->Name == $name)
                {
                    return $this->Fields[$i];
                }
            }
            return null;
        }
    }