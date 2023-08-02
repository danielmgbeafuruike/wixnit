<?php

    namespace Wixnit\Data;

    class DBResult
    {
        /**
         * @var int
         * @comment the total number of rows retrieved or affected after query is run
         */
        public int $Count = 0;

        /**
         * @var int
         * @comment the total number of row that would have been retrieved without limits and offset
         */
        public int $Total = 0;

        /**
         * @var array
         * @comment the main content of the query operation
         */
        public array $Data = [];
    }