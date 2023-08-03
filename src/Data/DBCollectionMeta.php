<?php

    namespace Wixnit\Data;
    
    class DBCollectionMeta
    {
        public $PageSize = 0;
        public $PerPage = 0;
        public $CurrentPage = 0;

        function __construct($pageSize=0, $currentPage=0, $perPage=0)
        {
            $this->PageSize = $pageSize;
            $this->PerPage = $perPage;
            $this->CurrentPage = $currentPage;
        }

        public static function ByPagination($total, Pagination $pagination): DBCollectionMeta
        {
            $ret = new DBCollectionMeta();
            $ret->PageSize = ceil($total / $pagination->Limit);
            $ret->PerPage = $pagination->Limit;
            $ret->CurrentPage = ($pagination->Offset / $pagination->Limit) + 1;
            return $ret;
        }
    }