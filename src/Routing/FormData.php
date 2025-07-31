<?php

    namespace Wixnit\Routing;

    use ArrayAccess;
    use stdClass;
    use Wixnit\Utilities\Convert;

    class FormData implements ArrayAccess
    {
        public array $args = [];

        function __construct() {}

        /**
         * Check if an argument exists
         * @param string $arg
         * @return bool
         */
        public function has(string $arg): bool
        {
            return isset($this->args[$arg]);
        }

        /**
         * Convert the form data to JSON
         * @return stdClass
         */
        public function toJson(): stdClass
        {
            return Convert::ArrayToStdClass($this->args);
        }

        /**
         * Convert the form data to XML
         * @return string
         */
        public function toXML(): string
        {
            return Convert::StdClassToXML(Convert::ArrayToStdClass($this->args));
        }

        /**
         * magic get method to access args
         * @param mixed $name
         * @return mixed
         */
        public function __get($name): mixed
        {
            if (isset($this->args[$name])) {
                return $this->args[$name];
            }
            return null;
        }



        #region ArrayAccess Interface Implimentation 

        /**
         * Check if an offset exists
         * @param mixed $offset
         * @return bool
         */
        public function offsetExists(mixed $offset): bool
        {
            return isset($this->args[$offset]);
        }

        /**
         * Get the value at the specified offset
         * @param mixed $offset
         * @return mixed
         */
        public function offsetGet(mixed $offset): mixed
        {
            if(!isset($this->args[$offset]))
            {
                return null;
            }
            return  $this->args[$offset];
        }

        /**
         * Set the value at the specified offset
         * @param mixed $offset
         * @param mixed $value
         * @return void
         */
        public function offsetSet(mixed $offset, mixed $value): void
        {
            if(is_null($offset))
            {
                $this->args[] = $value;
            }
            else
            {
                $this->args[$offset] = $value;
            }
        }

        /**
         * Unset the value at the specified offset
         * @param mixed $offset
         * @return void
         */
        public function offsetUnset(mixed $offset): void
        {
            unset($this->args[$offset]);
        }
    }