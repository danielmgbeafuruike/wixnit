<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\FilterOperation;

    class SearchBuilder
    {
        public array $searches = [];
        protected FilterOperation $operation = FilterOperation::AND;

        /**
         * Process and retrieve the query for the search builder
         * @param array $fields
         * @return DBSQLPrep
         */
        public function getQuery(array $fields=[]): DBSQLPrep
        {
            $ret = new DBSQLPrep();

            $ret->query = "(";
            for($i = 0; $i < count($this->searches); $i++)
            {
                $q = $this->searches[$i]->getQuery($fields);

                $ret->query.= ((trim($ret->query) != "(") ? (($this->operation == FilterOperation::OR) ? " OR " : " AND ") : "").$q->query;

                for($x = 0; $x < count($q->types); $x++)
                {
                    $ret->values[] = $q->values[$x];
                    $ret->types[] = $q->types[$x];
                }
            }

            $ret->query .= ")";
            return $ret;
        }

        /**
         * Add new search term and all
         * @param array | Search | SearchBuilder | null $search
         * @return void
         */
        public function add(array | Search | SearchBuilder | null $search)
        {
            if(is_array($search))
            {
                for($i = 0; $i < count($search); $i++)
                {
                    if(($search[$i] instanceof Search) || ($search[$i] instanceof SearchBuilder))
                    {
                        $this->searches[] = $search[$i];
                    }
                }
            }
            else if(($search instanceof Search) || ($search instanceof SearchBuilder))
            {
                $this->searches[] = $search;
            }
        }

        /**
         * Set the joining operation of the search builder
         * @param mixed $operation
         * @return void
         */
        public function setOperation($operation)
        {
            if(($operation == FilterOperation::OR) || ($operation == FilterOperation::AND))
            {
                $this->operation = $operation;
            }
        }
    }