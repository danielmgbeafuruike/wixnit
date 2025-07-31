<?php

    namespace Wixnit\Access;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;

    class Access implements ISerializable
    {
        public bool $read = false;
        public bool $write = false;

        function __construct(int $access=0)
        {
            $this->read = $access === 1 || $access === 2;
            $this->write = $access === 1 || $access === 3;
        }


        /**
         * convert access to integer - 0 no access, 1 read and wrte, 2 read only & 3 write only
         * @return int
         */
        function toInt(): int
        {
            if($this->read && $this->write)
            {
                return 1;
            }
            else if($this->read && !$this->write)
            {
                return 2;
            }
            else if(!$this->read && $this->write)
            {
                return 3;
            }
            else
            {
                return 0;
            }
        }



        #region implementnig ISerializable functions

        /**
         * get db field type for creating the appropriate db field type for saving the class to db
         * @return DBFieldType
         */
        public function _dbType(): DBFieldType
        {
            return DBFieldType::INT;
        }

        /**
         * prepare the object for saving to db
         * @return int
         */
        public function _serialize(): int
        {
            return $this->toInt();
        }

        /**
         * re-populate object from data rceived from db
         * @param mixed $data
         * @return void
         */
        public function _deserialize($data): void
        {
            $this->read = intval($data) === 1 || intval($data) === 2;
            $this->write = intval($data) === 1 || intval($data) === 3;
        }
        #endregion
    }