<?php

    namespace Wixnit\Data;

    class SearchBuilder
    {
        public $searches = [];
        protected int $operation = Filter::AND;

        public function getQuery($fields=[]): DBSQLPrep
        {
            $ret = new DBSQLPrep();

            $ret->Query = "(";
            for($i = 0; $i < count($this->searches); $i++)
            {
                $q = $this->searches[$i]->getQuery($fields);

                $ret->Query.= ((trim($ret->Query) != "(") ? (($this->operation == Filter::OR) ? " OR " : " AND ") : "").$q->Query;

                for($x = 0; $x < count($q->Types); $x++)
                {
                    $ret->Values[] = $q->Values[$x];
                    $ret->Types[] = $q->Types[$x];
                }
            }

            $ret->Query .= ")";
            return $ret;
        }

        public function add($search)
        {
            if(($search instanceof Search) || ($search instanceof SearchBuilder))
            {
                $this->searches[] = $search;
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

        public static function Deserialize($searches): SearchBuilder
        {
            $ret = new SearchBuilder();

            return $ret;
        }
    }