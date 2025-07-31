<?php

    namespace Wixnit\Interfaces;

    use Wixnit\Enum\DBFieldType;

    /**
     * Objects implementing ISerializable can customize the way they are saved and retrieved from the database
     */
    interface ISerializable
    {
        /**
         * tell DB Mapper what kind of column should be created
         * store the returned data
         * @return DBFieldType
         */
        public function _dbType(): DBFieldType;

        /**
         * Get the data to how it should be saved to the db
         * @return mixed
         */
        public function _serialize();


        /**
         * repopulate the object with data fromdb
         * @param mixed $data
         * @return void
         */
        public  function _deserialize($data): void;
    }