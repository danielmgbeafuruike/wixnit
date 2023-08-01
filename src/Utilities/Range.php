<?php

    namespace Wixnit\Utilities;

    use stdClass;

    class Range extends Span
    {
        function __construct($value1, $value2=null, $constraint=null)
        {
            if($value1 instanceof Span)
            {
                $this->Start = $value1->Start <= $value1->Stop ? $value1->Start : $value1->Stop;
                $this->Stop = $value1->Start <= $value1->Stop ? $value1->Stop : $value1->Start;
            }
            else if(is_array($value1))
            {

            }
            else if(isset($value2))
            {
                $this->Start = $value1 > $value2 ? $value1 : $value2;
                $this->Stop = $value1 > $value2 ? $value2 : $value1;
            }

            if($constraint != null)
            {

            }
        }

        public function toSpan(): Span
        {
            return new Span($this->Start, $this->Stop);
        }

        public function toTimespan(): Timespan
        {
            return new Timespan($this->Start, $this->Stop);
        }

        public function InRange($value): bool
        {
            return (($this->Start >= $value) && ($this->Stop <= $value));
        }

        public function Serialize()
        {
            $ret = new stdClass();
            $ret->Type = "Range";
            $ret->Start = $this->Start;
            $ret->Stop = $this->Stop;

            return json_encode($ret);
        }

        public static function Deserialize($serialized_span) : Range
        {
            $ret = new Range(new Span());

            if(is_string($serialized_span))
            {
                $r = json_decode($serialized_span);

                if($r->Type == "Range")
                {
                    $ret->Start = isset($r->Start) ? Convert::ToInt($r->Start) : 0;
                    $ret->Stop = isset($r->Stop) ? Convert::ToInt($r->Stop) : 0;
                }
            }
            return $ret;
        }
    }
    