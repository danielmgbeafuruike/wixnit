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

            $this->mapPivotTables($reflect->getName());
        }

        /**
         * Ensures the junction table for every #[BelongsToMany] relation declared on this
         * class exists. Unlike a model's own table, a pivot table isn't diffed column by
         * column against its previous shape - it's a fixed two-column shape by
         * construction, so a single idempotent CREATE TABLE IF NOT EXISTS is enough. Both
         * sides of a relation declare the same pivot table, so this runs safely (and
         * harmlessly) whichever side gets migrated first, or both.
         * @param string $class
         * @return void
         */
        private function mapPivotTables(string $class): void
        {
            foreach(RelationMap::pivotRelations($class) as $relation)
            {
                $sql = "CREATE TABLE IF NOT EXISTS ".$relation->pivotTable." (".
                    $relation->pivotLocalKey." VARCHAR(64) NOT NULL, ".
                    $relation->pivotRelatedKey." VARCHAR(64) NOT NULL, ".
                    "PRIMARY KEY (".$relation->pivotLocalKey.", ".$relation->pivotRelatedKey."), ".
                    "INDEX (".$relation->pivotRelatedKey.")".
                    ")";

                $this->db->query($sql);
            }
        }
    }