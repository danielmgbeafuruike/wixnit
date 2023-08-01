<?php

    namespace Wixnit\Utilities;

    class Span
    {
        public $Start = 0;
        public $Stop = 0;

        function __construct($start=null, $stop=null)
        {
            if($start != null)
            {
                $this->Start = $start;
            }
            if($stop != null)
            {
                $this->Stop = $stop;
            }
        }

        public function Difference()
        {
            return ($this->Stop > $this->Start) ? $this->Stop - $this->Start : $this->Start - $this->Stop;
        }

        public function splitSpan($places, $intersected=false)
        {
            $div = $this->Difference() / $places;
            $ret = [];

            if($this->Stop > $this->Start)
            {
                $st = $this->Start;

                for($i = 0; $i < $places; $i++)
                {
                    array_push($ret,  new Span($st, ($st + $div)));
                    $st += $div;
                }
            }
            else
            {
                $st = $this->Start;

                for($i = 0; $i < $places; $i++)
                {
                    array_push($ret, new Span($st * ($st - $div)));
                    $st -= $div;
                }
            }
            return $ret;
        }


        public function Serialize()
        {
            $ret = new stdClass();
            $ret->Type = "Span";
            $ret->Start = $this->Start;
            $ret->Stop = $this->Stop;

            return json_encode($ret);
        }

        public static function Deserialize($serialized_span) : Span
        {
            $ret = new Span();

            if(is_string($serialized_span))
            {
                $r = json_decode($serialized_span);

                if($r->Type == "Span")
                {
                    $ret->Start = isset($r->Start) ? Convert::ToInt($r->Start) : 0;
                    $ret->Stop = isset($r->Stop) ? Convert::ToInt($r->Stop) : 0;
                }
            }
            return $ret;
        }
    }