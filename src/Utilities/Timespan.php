<?php

    namespace Wixnit\Utilities;

    use stdClass;

    class Timespan extends Span
    {
        function __construct($start=null, $stop=null, $spanLastDay=false)
        {
            $sta = ($start == null) ? new WixDate(0) : new WixDate($start);
            $sto = ($stop == null) ? new WixDate(time()) : new WixDate($stop);

            $this->Start = $sta->getValue();

            if($spanLastDay === false)
            {
                $this->Stop = $sto->getValue();
            }
            else if(($start === true) || ($stop === true) || ($spanLastDay === true))
            {
                $this->Stop = strtotime( date("m/d/Y", $sto->getValue())) + ((60 * 60) * 24);
            }
        }

        public static function Thismonth()
        {

        }

        public static function Lastmonth(): Timespan
        {
            $m = Convert::ToInt(date("m"));
            if($m == 1)
            {
                $m = 12;
            }
            else
            {
                $m--;
            }
            $f = strtotime($m."/1/".date("y"));
            $sp = new Timespan($f, ($f + ((60 * 60) * 24) * 30));
            return $sp;
        }

        public static function Monthspan($from=null): Timespan
        {
            $f = ($from == null) ? new WixDate(time()) : new WixDate($from);
            $sp = new Timespan(($f->getValue() - ((60 * 60) * 24) * 30), $f);
            //$sp = new Timespan();
            return $sp;
        }

        public static function Thisyear($from=null): Timespan
        {
            $f = ($from === null) ? new WixDate(time()) : new WixDate($from);
            $sp = new Timespan((strtotime("1/1/".date("d"))), $f);
            return $sp;
        }

        public static function Lastyear(): Timespan
        {
            $f = strtotime("1/1/".(Convert::ToInt(date("y")) - 1));
            $sp = new Timespan($f, (strtotime("31/12/".date("d"))));
            return $sp;
        }

        public static function Thisweek()
        {

        }

        public static function Lastweek()
        {

        }


        public function Serialize()
        {
            $ret = new stdClass();
            $ret->Type = "Timespan";
            $ret->Start = $this->Start;
            $ret->Stop = $this->Stop;

            return json_encode($ret);
        }

        public static function Deserialize($serialized_timespan) : Timespan
        {
            $ret = new Timespan();

            if(is_string($serialized_timespan))
            {
                $r = json_decode($serialized_timespan);

                if($r->Type == "Timespan")
                {
                    $ret->Start = isset($r->Start) ? Convert::ToInt($r->Start) : 0;
                    $ret->Stop = isset($r->Stop) ? Convert::ToInt($r->Stop) : 0;
                }
            }
            return $ret;
        }
    }