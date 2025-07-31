<?php

    namespace Wixnit\Utilities;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;

    class Time implements ISerializable
    {
        public int $minutes = 0;
        public int $hours = 0;
        public int $seconds = 0;

        function __construct($arg=null)
        {
            $this->init($arg);
        }

        /**
         * hydrate the time object
         * @param mixed $arg
         * @return void
         */
        private function init($arg=null)
        {
            if($arg instanceof Date)
            {
                $rem = $arg->toEpochSeconds() % ((60 * 60) * 24);

                $this->hours = $rem / (60 * 60);
                $this->minutes = ($rem % (60 * 60)) / 60;
                $this->seconds = ($rem % 60);
            }
            else if($arg instanceof Time)
            {
                $this->hours = Convert::ToInt($arg->hours);
                $this->minutes = Convert::ToInt($arg->minutes);
                $this->seconds = Convert::ToInt($arg->seconds);
            }
            else if(is_int($arg))
            {
                $rem = Convert::ToInt($arg);

                $this->hours = $rem / (60 * 60);
                $this->minutes = ($rem % (60 * 60)) / 60;
                $this->seconds = ($rem % 60);
            }
            else if(is_string($arg))
            {
                $v = explode(":", $arg);

                for($i = 0; $i < count($v); $i++)
                {
                    if($i == 0)
                    {
                        $this->hours = Convert::ToInt($v[0]);
                    }
                    else if($i == 1)
                    {
                        $this->minutes = Convert::ToInt($v[1]);
                    }
                    else if($i == 2)
                    {
                        $this->seconds = Convert::ToInt($v[2]);
                        break;
                    }
                }
            }
        }

        /**
         * convert the time to epoch seconds
         * @return int
         */
        public function toSeconds(): int
        {
            return ((Convert::ToInt($this->hours) * (60 * 60)) + (Convert::ToInt($this->minutes) * 60) + ($this->seconds));
        }


        #region implemening ISerializable

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
            return $this->toSeconds();
        }

        /**
         * re-populate object from data rceived from db
         * @param mixed $data
         * @return void
         */
        public function _deserialize($data): void
        {
            $this->init($data);
        }
        #region
    }