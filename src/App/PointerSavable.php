<?php

    namespace wixnit\App;

    use wixnit\Data\DBTable;
    use mysqli;

    abstract class PointerSavable extends BaseModel
    {
        protected bool $ForceAutoGenId = false;
        private bool $Initialization = true;

        function __construct(mysqli $dbConnection, bool $Initialize=true)
        {
            $this->Initialization = $Initialize;

            if($this->Initialization)
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

            if(($this->Id == "") && ($this->Initialization))
            {
                $this->Id = "single_safe_record";
                $this->Save();
            }
        }
    }