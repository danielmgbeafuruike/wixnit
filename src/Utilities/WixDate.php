<?php

    namespace Wixnit\Utilities;

    use Wixnit\Interfaces\IBase;
    use Wixnit\Data\DBFieldType;
    use Wixnit\Data\Interfaces\ISerializable;


    class WixDate implements IBase, ISerializable
    {
        protected int $Value = 0;

        public int $Day = 0;
        public int $Month = 0;
        public int $Year = 0;
        public int $Minute = 0;
        public int $Second = 0;
        public int $Hour = 0;
        public string $WeekDay = "";
        public string $MonthName = "";

        function __construct($arg=null)
        {
            $this->Init($arg);
        }

        private function Init($arg=null)
        {
            if(is_int($arg))
            {
                $this->Value = $arg;
            }
            else if($arg instanceof WixDate)
            {
                $this->Value = $arg->getValue();
            }
            else if(is_string($arg) && (count(explode("-", $arg)) > 1))
            {
                $this->Value = strtotime($arg);
            }
            else if(is_string($arg) && (count(explode("/", $arg)) > 1))
            {
                $this->Value = strtotime($arg);
            }
            else
            {
                $this->Value = Convert::ToInt($arg);
            }

            $this->Day = date("d", $this->Value);
            $this->Month = date("m", $this->Value);
            $this->Year = date("Y", $this->Value);
            $this->Hour = date("h", $this->Value);
            $this->Minute = date("i", $this->Value);
            $this->Second = date("s", $this->Value);
            $this->WeekDay = date("D", $this->Value);
            $this->MonthName = date("M", $this->Value);
        }

        public function ToBool()
        {
            // TODO: Implement ToBool() method.
        }

        public function ToInt()
        {
            return $this->getValue();
        }

        public function ToString()
        {
            // TODO: Implement ToString() method.
        }

        public function todate()
        {
            return new WixDate(strtotime(date("m/d/Y", $this->Value)));
        }



        //implementing ISerializable

        public function _DBType(): string
        {
            return DBFieldType::Int;
        }

        public function _Serialize()
        {
            return $this->getValue();
        }

        public function _Deserialize($data)
        {
            $this->Init($data);
        }



        public function ToAgo($tm=null)
        {

        }

        public function ToFromNow($tm=null)
        {

        }

        /**
         * @return int|string
         */
        public function getValue()
        {
            return $this->Value;
        }

        /**
         * @param int|string $Value
         */
        public function setValue($Value)
        {
            $this->Value = $Value;
        }

        public static function isToday($date): bool
        {
            $start = strtotime(date("m/d/Y", time()));
            $end = ($start + ((60 * 60) * 24)) - 1;
            $tm = new WixDate($date);

            return (($tm->getValue() >= $start) && ($tm->getValue() <= $end));
        }

        public static function isTomorow($date): bool
        {
            $day = (((60 * 60) * 24) - 1);
            $start = strtotime(date("m/d/Y", time())) + $day;
            $end = $start + $day;
            $tm = new WixDate($date);

            return (($tm->getValue() >= $start) && ($tm->getValue() <= $end));
        }

        public static function isPastDays($date): bool
        {
            $today = strtotime(date("m/d/Y", time()));
            $tm = new WixDate($date);

            return ($tm->getValue() < $today);
        }

        public static function isFutureDay($date): bool
        {
            $today = strtotime(date("m/d/Y", time()));
            $endOfToday = ($today + ((60 * 60) * 24));
            $tm = new WixDate($date);

            return ($tm->getValue() >= $endOfToday);
        }

        public static function isSameDay($time_1, $time_2): bool
        {
            $tm1 = strtotime("m/d/Y", (new WixDate($time_1))->getValue());
            $tm2 = strtotime("m/d/Y", (new WixDate($time_2))->getValue());

            return ($tm1 == $tm2);
        }
    }