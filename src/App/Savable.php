<?php

    namespace Wixnit\App;

    use Wixnit\Data\DBConfig;
    use mysqli;

    abstract class Savable extends BaseModel
    {
        private bool $initialization = true;
        protected bool $forceAutoGenId = false;

        function __construct(bool $initialize=true)
        {
            $this->initialization = $initialize;
            $db = null;

            $args = func_get_args();

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof mysqli)
                {
                    $db = $args[$i];
                }
            }

            if($this->initialization)
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

            if(($this->id == "") && ($this->initialization))
            {
                $this->id = "single_safe_record";
                $this->save();
            }
        }
    }