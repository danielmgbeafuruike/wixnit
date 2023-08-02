<?php

    namespace Wixnit\Data;
    
    class DBCollectionMeta
    {
        public $Page = 0;
        public $Perpage = 0;
        public $Currentpage = 0;

        function __construct($page=0, $currentPage=0, $perPage=0)
        {
            $this->Page = $page;
            $this->Perpage = $perPage;
            $this->Currentpage = $currentPage;
        }

        public static function ByPagination($total, Pagination $pagination): DBCollectionMeta
        {
            $ret = new DBCollectionMeta();

            return $ret;
        }
    }