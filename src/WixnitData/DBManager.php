<?php

    namespace Wixnit\Data;

    use mysqli;

    class DBManager
    {
        //Extreme Methods
        public static function CreateDB()
        {

        }

        public static function BackupDB(mysqli $db, string $dbName, string $backupPath)
        {
            //$db->query("BACKUP DATABASE ".$tableName." TO DISK = 'D:\backups\testDB.bak';")
            $db->query("BACKUP DATABASE ".$dbName." TO DISK = '".$backupPath."'");
        }
    }