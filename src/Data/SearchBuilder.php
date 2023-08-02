<?php

    namespace Wixnit\Data;

    class SearchBuilder
    {
        /**
         * @var Search
         */
        public $searches = [];
        protected int $operation = Filter::AND;

        public function getQuery($fields=[]): DBSQLPrep
        {
            $ret = "(";
            for($i = 0; $i < count($this->searches); $i++)
            {
                $ret.= ((trim($ret) != "(") ? (($this->operation == Filter::OR) ? " OR " : " AND ") : ""). $this->searches[$i]->getQuery($fields);
            }
            //return $ret.")";
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