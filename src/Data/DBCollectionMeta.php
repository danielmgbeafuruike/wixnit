<?php

    namespace Wixnit\Data;
    
    class DBCollectionMeta
    {
        public int $pageSize = 0;
        public int $perPage = 0;
        public int $currentPage = 0;
        public $summary = [];

        function __construct($pageSize=0, $currentPage=0, $perPage=0)
        {
            $this->pageSize = $pageSize;
            $this->perPage = $perPage;
            $this->currentPage = $currentPage;
        }

        /**
         * Create a DBCollectionMeta object from a Pagination object
         * @param int $total Total number of items
         * @param Pagination $pagination Pagination object
         * @return DBCollectionMeta
         */
        public static function FromPagination($total, Pagination $pagination): DBCollectionMeta
        {
            $ret = new DBCollectionMeta();
            $ret->pageSize = ceil($total / $pagination->limit);
            $ret->perPage = $pagination->limit;
            $ret->currentPage = ($pagination->offset / $pagination->limit) + 1;
            return $ret;
        }
    }