<?php

    namespace Wixnit\Data;
    
    use Wixnit\App\PointerSavable;
    use Wixnit\App\Savable;
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
            if($reflect->isSubclassOf(PointerSavable::class))
            {
                $instance = $reflect->newInstance(new mysqli(), false);
            }
            else if($reflect->isSubclassOf(Savable::class))
            {
                $instance = $reflect->newInstance(false);
            }
            else
            {
                $instance = $reflect->newInstance(new mysqli());
            }

            $mapper = new DBMapper($this->db);
            $mapper->toTable($instance->getDBImage());
        }
    }