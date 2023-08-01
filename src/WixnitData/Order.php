<?php

    namespace wixnit\Data;

    use stdClass;

    class Order
    {
        public $Direction = Order::ASCENDING;
        public $Field = "";

        const ASCENDING = "ASC";
        const DESCENDING = "DESC";

        function __construct($field="id", $direction=Order::ASCENDING)
        {
            if(($direction == Order::ASCENDING) || ($direction == Order::DESCENDING))
            {
                $this->Direction = $direction;
            }
            $this->Field = $field;
        }

        function getQuery(): string
        {
            return " ORDER BY ".$this->Field." ".$this->Direction." ";
        }

        public function Serialize()
        {
            $ret = new stdClass();
            $ret->Type = "Orders";
            $ret->Direction = $this->Direction;
            $ret->Field = $this->Field;

            return json_encode($ret);
        }

        public static function Deserialize($serialized_order) : Order
        {
            $ret = new Order();

            if(is_string($serialized_order))
            {
                $r = json_decode($serialized_order);

                if($r->Type == "Orders")
                {
                    $ret->Field = $r->Field ?? "";
                    $ret->Direction = $r->Direction ?? Order::ASCENDING;
                }
            }
            return $ret;
        }
    }