<?php

    namespace Wixnit\App;

    use Wixnit\Data\FilterBuilder;
    use Wixnit\Data\Order;
    use Wixnit\Data\Pagination;
    use Wixnit\Data\SearchBuilder;

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