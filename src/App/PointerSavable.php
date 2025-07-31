<?php

    namespace Wixnit\App;

    use mysqli;

    abstract class PointerSavable extends BaseModel
    {
        protected bool $forceAutoGenId = false;
        private bool $initialization = true;

        function __construct(mysqli $dbConnection, bool $initialize=true)
        {
            $this->initialization = $initialize;

            if($this->initialization)
            {
                parent::__construct($dbConnection, 'single_safe_record');
            }
            else
            {
                parent::__construct($dbConnection);
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