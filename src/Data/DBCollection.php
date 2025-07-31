<?php

    namespace Wixnit\Data;

    use Wixnit\Utilities\Span;

    class DBCollection implements \Countable, \ArrayAccess
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

        
        /**
         * Get the total lenght of the collection list
         * @return int
         */
        public function count() : int
        {
            return Count($this->list);
        }

        /**
         * Reverse the order of the collection
         * @return DBCollection
         */
        public function reverse() : DBCollection
        {
            $this->list = array_reverse($this->list);
            return $this;
        }


        #region ArrayAccess methods
        
        /**
         * Check if an offset exists
         * @param mixed $offset
         * @return bool
         */
        public function offsetExists($offset): bool
        {
            return isset($this->list[$offset]);
        }

        /**
         * Get the value at the specified offset
         * @param mixed $offset
         * @return mixed
         */
        public function offsetGet(mixed $offset): mixed
        {
            if(!isset($this->list[$offset]))
            {
                return null;
            }
            return  $this->list[$offset];
        }

        /**
         * Set the value at the specified offset
         * @param mixed $offset
         * @param mixed $value
         */
        public function offsetSet(mixed $offset, mixed $value): void
        {
            if(is_null($offset))
            {
                $this->list[] = $value;
            }
            else
            {
                $this->list[$offset] = $value;
            }
        }

        /**
         * Unset the value at the specified offset
         * @param mixed $offset
         */
        public function offsetUnset(mixed $offset): void
        {
            unset($this->list[$offset]);
        }
        #endregion
    }