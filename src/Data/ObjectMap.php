<?php

    namespace Wixnit\Data;

    class ObjectMap
    {
        public string $name = "";
        public array $publicProperties = [];
        public array $hiddenProperties = [];

        /**
         * Convert the ObjectMap to a format suitable for database preparation.
         * This will convert the properties to a format that can be used in a database.
         * @return ObjectMap
         */
        public function dbPrep(): ObjectMap
        {
            $r = json_decode(json_encode($this));

            for($i = 0; $i < count($r->publicProperties); $i++)
            {
                if(strtolower($r->publicProperties[$i]->name) == "id")
                {
                    $r->publicProperties[$i]->name = ucwords(strtolower(array_reverse(explode("\\", $this->name))[0]."id"));
                    $r->publicProperties[$i]->baseName = strtolower(array_reverse(explode("\\", $this->name))[0]."id");
                }
            }

            $ret = new ObjectMap();
            $ret->name = $r->name;
            $ret->publicProperties = $r->publicProperties;
            $ret->hiddenProperties = $r->hiddenProperties;
            return  $ret;
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