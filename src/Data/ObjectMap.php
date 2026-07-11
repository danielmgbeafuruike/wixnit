<?php

    namespace Wixnit\Data;

    class ObjectMap
    {
        public string $name = "";
        public array $publicProperties = [];
        public array $hiddenProperties = [];

        /**
         * deep-copy the property lists on clone, so a cloned ObjectMap doesn't share its
         * ObjectProperty instances with the original (used by Mappable's map cache, so a
         * caller mutating a property it got back can never corrupt the cached copy)
         * @return void
         */
        public function __clone(): void
        {
            for($i = 0; $i < count($this->publicProperties); $i++)
            {
                $this->publicProperties[$i] = clone $this->publicProperties[$i];
            }
            for($i = 0; $i < count($this->hiddenProperties); $i++)
            {
                $this->hiddenProperties[$i] = clone $this->hiddenProperties[$i];
            }
        }

        /**
         * per-class-name cache of already-"db prepped" maps, so the id-renaming and cloning
         * below only happens once per class rather than on every join/query built with it.
         * Safe to key purely by name: every dbPrep() call in this codebase is made on a map
         * that came from Mappable::getMap(), whose property list is fixed per class (see the
         * caching note there) - so a given $name always produces the same prepped result.
         * @var array<string, ObjectMap>
         */
        private static array $dbPrepCache = [];

        /**
         * Convert the ObjectMap to a format suitable for database preparation.
         * This will convert the properties to a format that can be used in a database.
         * Renames the "id" property to "{ClassName}id" so it doesn't collide with the
         * owning table's own "id" column once this map is used as a join.
         * @return ObjectMap
         */
        public function dbPrep(): ObjectMap
        {
            if(isset(ObjectMap::$dbPrepCache[$this->name]))
            {
                //hand back a clone, never the cached instance itself - same reasoning as
                //Mappable::getMap()'s cache: callers must never be able to mutate what's cached
                return clone ObjectMap::$dbPrepCache[$this->name];
            }

            $ret = clone $this; // deep copy (via __clone() above) - no JSON round-trip needed
            $idName = strtolower(array_reverse(explode("\\", $this->name))[0])."id";

            for($i = 0; $i < count($ret->publicProperties); $i++)
            {
                if(strtolower($ret->publicProperties[$i]->name) == "id")
                {
                    $ret->publicProperties[$i]->name = ucwords($idName);
                    $ret->publicProperties[$i]->baseName = $idName;
                }
            }

            ObjectMap::$dbPrepCache[$this->name] = $ret;
            return clone $ret;
        }

        /**
         * forget cached dbPrep() result(s), forcing the next call to recompute.
         * @param string|null $name clear a single class name's cached result; clears everything if omitted
         * @return void
         */
        public static function ClearDBPrepCache(?string $name = null): void
        {
            if($name === null)
            {
                ObjectMap::$dbPrepCache = [];
            }
            else
            {
                unset(ObjectMap::$dbPrepCache[$name]);
            }
        }

        /**
         * Check if the ObjectMap has a specific property.
         * This will check both public and hidden properties.
         * @param \Wixnit\Data\ObjectProperty|string $prop
         * @param bool $searchHidden
         * @return bool
         */
        public function hasProperty(ObjectProperty | string $prop, bool $searchHidden=false): bool
        {
            $name = (($prop instanceof ObjectProperty) ? $prop->name : $prop);

            for($i = 0; $i < count($this->publicProperties); $i++)
            {
                if($this->publicProperties[$i]->name == $name)
                {
                    return true;
                }
            }
            if($searchHidden)
            {
                for($i = 0; $i < count($this->hiddenProperties); $i++)
                {
                    if($this->hiddenProperties[$i]->name == $name)
                    {
                        return true;
                    }
                }
            }
            return false;
        }

        /**
         * Get a property from the ObjectMap.
         * This will return the first matching property found in either public or hidden properties.
         * @param \Wixnit\Data\ObjectProperty|string $prop
         * @param bool $searchHidden
         * @return ObjectProperty|null
         */
        public function getProperty(ObjectProperty | string $prop, bool $searchHidden=false): ?ObjectProperty
        {
            $name = (($prop instanceof ObjectProperty) ? $prop->name : $prop);

            for($i = 0; $i < count($this->publicProperties); $i++)
            {
                if($this->publicProperties[$i]->name == $name)
                {
                    return $this->publicProperties[$i];
                }
            }
            if($searchHidden)
            {
                for($i = 0; $i < count($this->hiddenProperties); $i++)
                {
                    if($this->hiddenProperties[$i]->name == $name)
                    {
                        return $this->hiddenProperties[$i];
                    }
                }
            }
            return null;
        }
    }