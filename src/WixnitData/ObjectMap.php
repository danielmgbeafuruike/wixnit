<?php

    namespace Wixnit\Data;

    class ObjectMap
    {
        public string $Name = "";
        public array $PublicProperties = [];
        public array $HiddenProperties = [];

        public function DBPrep(): ObjectMap
        {
            $r = json_decode(json_encode($this));

            for($i = 0; $i < count($r->PublicProperties); $i++)
            {
                if(strtolower($r->PublicProperties[$i]->Name) == "id")
                {
                    $r->PublicProperties[$i]->Name = ucwords(strtolower(array_reverse(explode("\\", $this->Name))[0]."id"));
                    $r->PublicProperties[$i]->baseName = strtolower(array_reverse(explode("\\", $this->Name))[0]."id");
                }
            }

            $ret = new ObjectMap();
            $ret->Name = $r->Name;
            $ret->PublicProperties = $r->PublicProperties;
            $ret->HiddenProperties = $r->HiddenProperties;
            return  $ret;
        }

        public function HasProperty($prop, $searchHidden=null): bool
        {
            $name = (($prop instanceof ObjectProperty) ? $prop->Name : $prop);

            if(($searchHidden === null) || ($searchHidden === false))
            {
                for($i = 0; $i < count($this->PublicProperties); $i++)
                {
                    if($this->PublicProperties[$i]->Name == $name)
                    {
                        return true;
                    }
                }
            }
            if(($searchHidden === null) || ($searchHidden === true))
            {
                for($i = 0; $i < count($this->HiddenProperties); $i++)
                {
                    if($this->HiddenProperties[$i]->Name == $name)
                    {
                        return true;
                    }
                }
            }
            return false;
        }

        public function GetProperty($prop, $searchHidden=null): ?ObjectProperty
        {
            $name = (($prop instanceof ObjectProperty) ? $prop->Name : $prop);

            if(($searchHidden === null) || ($searchHidden === false))
            {
                for($i = 0; $i < count($this->PublicProperties); $i++)
                {
                    if($this->PublicProperties[$i]->Name == $name)
                    {
                        return $this->PublicProperties[$i];
                    }
                }
            }
            if(($searchHidden === null) || ($searchHidden === true))
            {
                for($i = 0; $i < count($this->HiddenProperties); $i++)
                {
                    if($this->HiddenProperties[$i]->Name == $name)
                    {
                        return $this->HiddenProperties[$i];
                    }
                }
            }
            return null;
        }
    }