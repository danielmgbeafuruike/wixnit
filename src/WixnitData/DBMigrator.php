<?php

    namespace wixnit\Data;
    
    use wixnit\App\PointerSavable;
    use wixnit\App\Savable;
    use mysqli;
    use ReflectionClass;

    class DBMigrator
    {
        private mysqli $db;

        function __construct(mysqli $db)
        {
            $this->db = $db;
        }

        public function mapClass($object_or_class)
        {
            $reflect = new ReflectionClass($object_or_class);

            $instance = null;
            if(($reflect->isSubclassOf(Savable::class)) || ($reflect->isSubclassOf(PointerSavable::class)))
            {
                $instance = $reflect->newInstance(new mysqli(), false);
            }
            else
            {
                $instance = $reflect->newInstance(new mysqli());
            }

            $mapper = new DBMapper($this->db);
            //echo json_encode($instance->getDBImage());
            $mapper->toTable($instance->getDBImage());
        }
    }