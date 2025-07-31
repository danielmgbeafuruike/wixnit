<?php

    namespace Wixnit\Data;
    
    class DBCollectionMeta
    {
        public $pageSize = 0;
        public $perPage = 0;
        public $currentPage = 0;

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