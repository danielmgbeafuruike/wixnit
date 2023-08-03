<?php

    namespace Wixnit\App;

    use Wixnit\Data\DBConfig;
    use mysqli;

    abstract class Savable extends BaseModel
    {
        private bool $Initialization = true;
        protected bool $ForceAutoGenId = false;

        function __construct(bool $Initialize=true)
        {
            $this->Initialization = $Initialize;
            $db = null;

            $args = func_get_args();

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof mysqli)
                {
                    $db = $args[$i];
                }
            }

            if($this->Initialization)
            {
                parent::__construct(($db != null ? $db : new DBConfig()), 'single_safe_record');
            }
            else
            {
                parent::__construct(($db != null ? $db : new DBConfig()));
            }
        }


        protected function onCreated()
        {
            parent::onCreated();

            if(($this->Id == "") && ($this->Initialization))
            {
                $this->Id = "single_safe_record";
                $this->Save();
            }
        }
    }