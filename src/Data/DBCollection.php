<?php

    namespace Wixnit\Data;

    use Wixnit\Utilities\Span;

    class DBCollection implements \Countable, \ArrayAccess
    {
        public array $List = [];
        public int $TotalRowCount = 0;
        public Span $Collectionspan;

        //meta data to lessen work for paginator
        public DBCollectionMeta $Meta;


        function __construct()
        {
            $this->Collectionspan = new Span();
            $this->Meta = new DBCollectionMeta();
        }

        
        public function Count() : int
        {
            return Count($this->List);
        }

        public function Get() : DBCollection
        {
            $ret = new DBCollection();

            return $ret;
        }

        public function Order() : DBCollection
        {
            return $this;
        }

        public function Reverse() : DBCollection
        {
            $this->List = array_reverse($this->List);
            return $this;
        }

        public function offsetExists($offset): bool
        {
            return isset($this->List[$offset]);
        }

        public function offsetGet($offset)
        {
            if(!isset($this->List[$offset]))
            {
                return null;
            }
            return  $this->List[$offset];
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            if(is_null($offset))
            {
                $this->List[] = $value;
            }
            else
            {
                $this->List[$offset] = $value;
            }
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->List[$offset]);
        }
    }