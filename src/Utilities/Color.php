<?php

    namespace wixnit\Utilities;

    use stdClass;

    class Color
    {
        public int $Red = 0;
        public int $Green = 0;
        public int $Blue = 0;
        public int $Opacity = 0;
        public int $Alpha = 0;

        public string $Hex = "";

        function __construct($arg=null)
        {
            if(is_object($arg))
            {
                $this->Red = isset($arg->Red) ? Convert::ToInt($arg->Red) : 0;
                $this->Green = isset($arg->Green) ? Convert::ToInt($arg->Green) : 0;
                $this->Blue = isset($arg->Blue) ? Convert::ToInt($arg->Blue) : 0;
                $this->Opacity = isset($arg->Opacity) ? Convert::ToInt($arg->Opacity) : 0;
                $this->Alpha = isset($arg->Alpha) ? Convert::ToInt($arg->Alpha) : 0;
            }
            else if(is_string($arg))
            {
                if(in_array("#", str_split($arg)))
                {
                    $this->Hex = $arg;
                }
                else
                {
                    try{
                        $obj = json_decode($arg);

                        $this->Red = isset($obj->Red) ? Convert::ToInt($obj->Red) : 0;
                        $this->Green = isset($obj->Green) ? Convert::ToInt($obj->Green) : 0;
                        $this->Blue = isset($obj->Blue) ? Convert::ToInt($obj->Blue) : 0;
                        $this->Opacity = isset($obj->Opacity) ? Convert::ToInt($obj->Opacity) : 0;
                        $this->Alpha = isset($obj->Alpha) ? Convert::ToInt($obj->Alpha) : 0;
                    }
                    catch (\Exception $exception) {}
                }
            }
        }

        function toString()
        {
            $ret = new stdClass();
            $ret->Red = $this->Red;
            $ret->Green = $this->Green;
            $ret->Blue = $this->Blue;
            $ret->Opacity = $this->Opacity;
            $ret->Alpha = $this->Alpha;
            $ret->Hex = $this->Hex;

            return json_encode($ret);
        }
    }