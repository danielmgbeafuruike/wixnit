<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\FilterOperation;

    class FilterBuilder
    {
        /**
         * @var Filter[] | FilterBuilder[]
         */
        public array $filters = [];
        protected FilterOperation $operation = FilterOperation::AND;

        /**
         * Get the super query for all the built filters
         * @return DBSQLPrep
         */
        public function getQuery(): DBSQLPrep
        {
            $ret = new DBSQLPrep();

            $ret->query = "(";
            for($i = 0; $i < count($this->filters); $i++)
            {
                $p = $this->filters[$i]->getQuery();
                $ret->query .= ((trim($ret->query) != "(") ? (($this->operation == FilterOperation::OR) ? " OR " : " AND ") : "")."(".$p->query.")";

                $ret->types = array_merge($ret->types, $p->types);
                $ret->values = array_merge($ret->values, $p->values);
            }
            $ret->query .= ")";
            return  $ret;
        }

        /**
         * Add filter to the filter builder
         * @param mixed $filter
         * @return void
         */
        public function add(Filter | FilterBuilder $filter): void
        {
            if(($filter instanceof Filter) || ($filter instanceof FilterBuilder))
            {
                $this->filters[] = $filter;
            }
        }

        /**
         * Set the general operation for the builder
         * @param mixed $operation
         * @return void
         */
        public function setOperation($operation): void
        {
            if(($operation == FilterOperation::OR) || ($operation == FilterOperation::AND))
            {
                $this->operation = $operation;
            }
        }
    }