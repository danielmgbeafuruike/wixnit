<?php

    namespace Wixnit\Routing;

    use Wixnit\Interfaces\IResponse;
    use Wixnit\Utilities\Convert;

    class Response implements IResponse
    {
        public $Content = "";

        public $Type = "";
        private $Status = 0;
        private $Format = "";

        function __construct($content="", $type="Unknown", $status = 0, $format="UNKNOWN")
        {
            $this->Content = $content;
            $this->Status = $status;
            $this->Type = $type;
            $this->Format = $format;
        }

        public function ToString()
        {
            // TODO: Implement ToString() method.
            return $this->Content;
        }

        public function ToInt()
        {
            // TODO: Implement ToInt() method.
            return $this->Status;
        }

        public function ToBool()
        {
            // TODO: Implement ToBool() method.
            return Convert::ToBool($this->Status);
        }

        public function GetStatus()
        {
            // TODO: Implement GetStatus() method.
            return $this->Status;
        }

        public function GetType()
        {
            // TODO: Implement GetType() method.
            return $this->Type;
        }

        public function GetFormat()
        {
            // TODO: Implement GetType() method.
            return $this->Format;
        }

    }