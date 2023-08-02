<?php

    namespace Wixnit\Data;

    class FilterBuilder
    {
        /**
         * @var Filter
         */
        public $filter = [];
        protected int $operation = Filter::AND;

        public function getQuery(): DBSQLPrep
        {
            $ret = new DBSQLPrep();

            $ret->Query = "(";
            for($i = 0; $i < count($this->filter); $i++)
            {
                $p = $this->filter[$i]->getQuery();
                $ret->Query .= ((trim($ret->Query) != "(") ? (($this->operation == Filter::OR) ? " OR " : " AND ") : "").$p->Query;

                $ret->Types = array_merge($ret->Types, $p->Types);
                $ret->Values = array_merge($ret->Values, $p->Values);
            }
            $ret->Query .= ")";
            return  $ret;
        }

        public function add($filter)
        {
            if((($filter instanceof Filter)) || ($filter instanceof FilterBuilder))
            {
                $this->filter[] = $filter;
            }
        }

        public function setOperation($operation)
        {
            if(($operation == Filter::OR) || ($operation == Filter::AND))
            {
                $this->operation = $operation;
            }
        }

        public function Serialize()
        {

        }

        public static function Deserialize($filters): FilterBuilder
        {
            $ret = new FilterBuilder();

            return $ret;
        }
    }