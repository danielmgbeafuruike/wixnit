<?php

    namespace wixnit\Access;

    use wixnit\Data\DBFieldType;
    use wixnit\Data\Interfaces\ISerializable;

    class Access implements ISerializable
    {
        public bool $ReadAccess = false;
        public bool $WriteAccess = false;

        function __construct(int $access=0)
        {
            $this->ReadAccess = $access === 1 || $access === 2;
            $this->WriteAccess = $access === 1 || $access === 3;
        }

        function toInt(): int
        {
            if($this->ReadAccess && $this->WriteAccess)
            {
                return 1;
            }
            else if($this->ReadAccess && !$this->WriteAccess)
            {
                return 2;
            }
            else if(!$this->ReadAccess && $this->WriteAccess)
            {
                return 3;
            }
            else
            {
                return 0;
            }
        }

        public function _DBType(): string
        {
            return DBFieldType::Int;
        }

        public function _Serialize(): int
        {
            return $this->toInt();
        }

        public function _Deserialize($data)
        {
            $this->ReadAccess = intval($data) === 1 || intval($data) === 2;
            $this->WriteAccess = intval($data) === 1 || intval($data) === 3;
        }
    }