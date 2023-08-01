<?php

    namespace wixnit\App;

    use wixnit\Data\FilterBuilder;
    use wixnit\Data\Order;
    use wixnit\Data\Pagination;
    use wixnit\Data\SearchBuilder;

    /**
     * @comment initialize requests, call appropriate methods, suppose to contain business logic
     */
    abstract class Controller
    {
        protected ?Pagination $Pagination = null;
        protected ?FilterBuilder $Filters = null;
        protected ?SearchBuilder $Searches = null;
        protected ?Order $Order = null;

        public function Get($arg=null) {}

        public function Delete($arg=null) {}

        public function Create($arg=null) {}

        public function Update($arg=null) {}

        public function Patch($arg=null) {}
        public function Head($arg=null) {}

        public function Option($arg=null) {}
    }