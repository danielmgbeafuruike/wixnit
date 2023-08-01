<?php

    namespace Wixnit\Utilities;

    class WixTime
    {
        public int $Minute = 0;
        public int $Hours = 0;
        public int $Seconds = 0;

        function __construct($arg=null)
        {
            if($arg instanceof WixDate)
            {
                $rem = $arg->getValue() % ((60 * 60) * 24);

                $this->Hours = $rem / (60 * 60);
                $this->Minute = ($rem % (60 * 60)) / 60;
                $this->Seconds = ($rem % 60);
            }
            else if($arg instanceof WixTime)
            {
                $this->Hours = Convert::ToInt($arg->Hours);
                $this->Minute = Convert::ToInt($arg->Minute);
                $this->Seconds = Convert::ToInt($this->Seconds);
            }
            else if(is_int($arg))
            {
                $rem = Convert::ToInt($arg);

                $this->Hours = $rem / (60 * 60);
                $this->Minute = ($rem % (60 * 60)) / 60;
                $this->Seconds = ($rem % 60);
            }
            else if(is_string($arg))
            {
                $v = explode(":", $arg);

                for($i = 0; $i < count($v); $i++)
                {
                    if($i == 0)
                    {
                        $this->Hours = Convert::ToInt($v[0]);
                    }
                    else if($i == 1)
                    {
                        $this->Minute = Convert::ToInt($v[1]);
                    }
                    else if($i == 2)
                    {
                        $this->Seconds = Convert::ToInt($v[2]);
                        break;
                    }
                }
            }
        }

        public function ToInt()
        {
            return ((Convert::ToInt($this->Hours) * (60 * 60)) + (Convert::ToInt($this->Minute) * 60) + ($this->Seconds));
        }

        public function ToString(): string
        {
            return ((Convert::ToInt($this->Hours) < 10) ? "0".$this->Hours : $this->Hours).":".((Convert::ToInt($this->Minute) < 10) ? "0".$this->Minute : $this->Minute);
        }
    }