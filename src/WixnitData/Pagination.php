<?php

    namespace wixnit\Data;

    use wixnit\Utilities\Convert;
    use wixnit\Utilities\Range;
    use wixnit\Utilities\Span;
    use stdClass;

    class Pagination extends Span
    {
        public $Offset = null;
        public $Limit = null;

        function __construct($page = 1, $perpage = 25)
        {
            $start = (($page - 1) * $perpage);
            $stop = (($start + $perpage) - 1);

            $this->Limit = $perpage;
            $this->Offset = $start;

            parent::__construct($start, $stop);
        }

        public static function FromSpan(Span $span): Pagination
        {
            $range = new Range($span);

            $pgn = new Pagination();
            $pgn->Start = $range->Start;
            $pgn->Stop = $range->Stop;
            $pgn->Limit = (($pgn->Stop - $pgn->Start) + 1);
            $pgn->Offset = $pgn->Start;

            return $pgn;
        }

        public function Serialize()
        {
            $ret = new stdClass();
            $ret->Type = "Pagination";
            $ret->Start = $this->Start;
            $ret->Stop = $this->Stop;
            $ret->Offset = $this->Offset;
            $ret->Limit = $this->Limit;

            return json_encode($ret);
        }

        public static function Deserialize($serialized_span) : Pagination
        {
            $ret = new Pagination();

            if(is_string($serialized_span))
            {
                $r = json_decode($serialized_span);

                if($r->Type == "Pagination")
                {
                    $ret->Limit = isset($r->Limit) ? Convert::ToInt($r->Limit) : 0;
                    $ret->Offset = isset($r->Offset) ? Convert::ToInt($r->Offset) : 0;
                    $ret->Start = isset($r->Start) ? Convert::ToInt($r->Start) : 0;
                    $ret->Stop = isset($r->Stop) ? Convert::ToInt($r->Stop) : 0;
                }
            }
            return $ret;
        }
    }