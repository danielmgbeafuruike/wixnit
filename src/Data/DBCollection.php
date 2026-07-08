<?php

    namespace Wixnit\Data;

    use Wixnit\Utilities\Collection;
    use Wixnit\Utilities\Span;

    class DBCollection extends Collection
    {
        public array $list = [];
        public int $totalRowCount = 0;
        public Span $collectionSpan;

        //meta data to lessen work for paginator
        public DBCollectionMeta $meta;


        function __construct()
        {
            $this->collectionSpan = new Span();
            $this->meta = new DBCollectionMeta();
        }
    }