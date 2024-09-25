<?php

    namespace Wixnit\Routing;

    use ArrayAccess;
    use stdClass;
    use Wixnit\Utilities\Convert;

    class FormData implements ArrayAccess
    {
        public array $Args = [];

        function __construct() {}


        public function Has(string $arg): bool
        {
            return isset($this->Args[$arg]);
        }

        public function ToJson(): stdClass
        {
            return Convert::ArrayToStdClass($this->Args);
        }

        public function ToXML(): string
        {
            return Convert::StdClassToXML(Convert::ArrayToStdClass($this->Args));
        }



        /*
        // ArrayAccess Interface Implimentation 
        */

        public function offsetExists(mixed $offset): bool
        {
            return isset($this->Args[$offset]);
        }
        public function offsetGet(mixed $offset): mixed
        {
            if(!isset($this->Args[$offset]))
            {
                return null;
            }
            return  $this->Args[$offset];
        }
        public function offsetSet(mixed $offset, mixed $value): void
        {
            if(is_null($offset))
            {
                $this->Args[] = $value;
            }
            else
            {
                $this->Args[$offset] = $value;
            }
        }
        public function offsetUnset(mixed $offset): void
        {
            unset($this->Args[$offset]);
        }
    }