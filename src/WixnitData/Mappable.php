<?php

    namespace Wixnit\Data;

    use ReflectionClass;
use ReflectionObject;
use stdClass;

    abstract class Mappable
    {
        protected array $Excludes = [];
        protected array $Includes = [];
        protected array $PropertyTypes = [];
        protected array $MappingIndex = [];
        protected array $Hidden = [];
        protected array $HiddenProperties = [];


        function __construct()
        {
            //first order of business create hidden fields
            for($i = 0; $i < count($this->Hidden); $i++)
            {
                $this->HiddenProperties[$this->Hidden[$i]] = null;
            }
        }

        public function GetMap(): ObjectMap
        {
            $ret = new ObjectMap();
            $ret->Name = get_called_class();

            //get everything using reflection
            $reflection = new ReflectionClass($this);
            $props = $reflection->getProperties();

            for($i = 0; $i < count($props); $i++)
            {
                if(($props[$i]->getModifiers() == 1) && (!$this->isExcluded($props[$i]->getName())))
                {
                    $prop = new ObjectProperty();
                    $prop->Name = $props[$i]->getName();
                    $prop->baseName = strtolower($this->mappedName($prop->Name));

                    if($props[$i]->getType() != null)
                    {
                        $prop->Type = $props[$i]->getType()->getName();

                        if($prop->Type == "array")
                        {
                            $prop->IsArray = true;
                            $pType = $this->getProposedType($prop->Name);

                            if($pType != null)
                            {
                                $prop->Type = $pType;
                            }
                        }
                    }
                    else
                    {
                        $prop->Type = strtolower(gettype($props[$i]->getValue($this)));

                        if(($prop->Type == "array") || ($prop->Type == "null"))
                        {
                            $prop->IsArray = true;
                            $pType = $this->getProposedType($prop->Name);

                            if($pType != null)
                            {
                                $prop->Type = $pType;
                            }
                        }
                    }
                    $ret->PublicProperties[] = $prop;
                }
            }

            //add hidden fields
            $hiddenKeys = array_keys($this->HiddenProperties);

            for($i = 0; $i < count($hiddenKeys); $i++)
            {
                if (!$this->isExcluded($props[$i]->getName()))
                {
                    $prop = new ObjectProperty();
                    $prop->Name = $hiddenKeys[$i];
                    $prop->baseName = strtolower($this->mappedName($prop->Name));

                    $pType = $this->getProposedType($hiddenKeys[$i]);

                    if($pType != null)
                    {
                        $prop->Type = $pType;
                    }

                    //check if array was chosen as hidden type
                    if($prop->Type == "array")
                    {
                        $prop->IsArray = true;
                    }
                    $ret->HiddenProperties[] = $prop;
                }
            }
            return $ret;
        }

        protected static function MapObject($object): ObjectMap
        {
            $reflection = is_object($object) ? new ReflectionObject($object) : new ReflectionClass($object);

            $ret = new ObjectMap();
            $ret->Name = $reflection->getShortName();

            $props = $reflection->getProperties();

            for($i = 0; $i < count($props); $i++)
            {
                if($props[$i]->getModifiers() == 1)
                {
                    $prop = new ObjectProperty();
                    $prop->Name = $props[$i]->getName();

                    if($props[$i]->getType() != null)
                    {
                        $prop->Type = $props[$i]->getType()->getName();
                    }
                    else if(is_object($object))
                    {
                        $prop->Type = strtolower(gettype($props[$i]->getValue($object)));
                    }
                    else
                    {
                        $prop->Type = null;
                    }
                    $ret->PublicProperties[] = $prop;
                }
            }
            return $ret;
        }

        public function mapFrom($array_object, bool $map_hidden=true)
        {
            $properties = $this->GetMap();


            if(is_array($array_object))
            {
                for($i = 0; $i < count($properties->PublicProperties); $i++)
                {
                    $fieldName = $properties->PublicProperties[$i]->Name;
                    $fieldName_ = strtolower($properties->PublicProperties[$i]->Name);

                    if(array_key_exists($fieldName, $array_object))
                    {
                        $this->$fieldName = $array_object[$fieldName];
                    }
                    else if(array_key_exists($fieldName_, $array_object))
                    {
                        $this->$fieldName = $array_object[$fieldName_];
                    }
                }
            }
            else
            {
                for($i = 0; $i < count($properties->PublicProperties); $i++)
                {
                    $fieldName = $properties->PublicProperties[$i]->Name;
                    $fieldName_ = strtolower($properties->PublicProperties[$i]->Name);

                    if(isset($array_object->$fieldName))
                    {
                        $this->$fieldName = $array_object->$fieldName;
                    }
                    else if(isset($array_object->$fieldName_))
                    {
                        $this->$fieldName = $array_object->$fieldName_;
                    }
                }
            }

            if($map_hidden)
            {
                $hiddenKeys = array_keys($this->HiddenProperties);

                if(is_array($array_object))
                {
                    for($i = 0; $i < count($hiddenKeys); $i++)
                    {
                        $fieldName = $hiddenKeys[$i];
                        $fieldName_ = strtolower($hiddenKeys[$i]);

                        if(array_key_exists($fieldName, $array_object))
                        {
                            $this->HiddenProperties[$fieldName] = $array_object[$fieldName];
                        }
                        else if(array_key_exists($fieldName_, $array_object))
                        {
                            $this->HiddenProperties[$fieldName] = $array_object[$fieldName_];
                        }
                    }
                }
                else
                {
                    for($i = 0; $i < count($properties->PublicProperties); $i++)
                    {
                        $fieldName = $properties->PublicProperties[$i]->Name;
                        $fieldName_ = strtolower($properties->PublicProperties[$i]->Name);

                        if(isset($array_object->$fieldName))
                        {
                            $this->HiddenProperties[$fieldName] = $array_object->$fieldName;
                        }
                        else if(isset($array_object->$fieldName_))
                        {
                            $this->HiddenProperties[$fieldName] = $array_object->$fieldName_;
                        }
                    }
                }
            }
        }

        public function Clone($object)
        {
            $properties = $this->GetMap();

            for($i = 0; $i < count($properties->PublicProperties); $i++)
            {
                $fieldName = $properties->PublicProperties[$i]->Name;

                if(isset($object->$fieldName))
                {
                    $this->$fieldName = $object->$fieldName;
                }
            }

            if($object instanceof Mappable)
            {
                $this->HiddenProperties = $object->HiddenProperties;

                $this->Excludes = $object->Excludes;
                $this->Includes = $object->Includes;
                $this->PropertyTypes = $object->PropertyTypes;
                $this->MappingIndex = $object->MappingIndex;
                $this->Hidden = $object->Hidden;
            }
        }

        public function toSTDClass(bool $includeHiddenFields=false): stdClass
        {
            $obj = json_decode(json_encode($this));

            if($includeHiddenFields)
            {
                $hidden = array_keys($this->HiddenProperties);

                for($i = 0; $i < count($hidden); $i++)
                {
                    $fName = $hidden[$i];
                    $obj->$fName = $this->HiddenProperties[$hidden[$i]];
                }
            }
            return $obj;
        }

        /**
         * @param $propertyName
         * @param $value
         * @return void
         * @comment set the value of any property on the entity including hidden properties
         */
        protected function setProperty($propertyName, $value)
        {
            if(array_key_exists($propertyName, $this->HiddenProperties))
            {
                $this->HiddenProperties[$propertyName] = $value;
            }
            else if(property_exists($this, $this->$propertyName))
            {
                $this->$propertyName = $value;
            }
        }

        /**
         * @param $propertyName
         * @return mixed
         * @comment get the value of any property on the entity including hidden properties
         */
        protected function getProperty($propertyName)
        {
            if(array_key_exists($propertyName, $this->HiddenProperties))
            {
                return $this->HiddenProperties[$propertyName];
            }
            return $this->$propertyName;
        }

        //All the private methods that do the background work
        private function getProposedType($property_name): ?string
        {
            return $this->PropertyTypes[$property_name] ?? ($this->PropertyTypes[strtolower($property_name)] ??  null);
        }

        protected function getMethods(): array
        {
            $reflection = new ReflectionClass($this);
            return $reflection->getMethods();
        }

        protected function isExcluded(string $propertyName) : bool
        {
            if(in_array(strtolower($propertyName), $this->Excludes) || (in_array($propertyName, $this->Excludes)))
            {
                return true;
            }
            return false;
        }

        protected function mappedName(string $originalName)
        {
            if((isset($this->MappingIndex[$originalName])) || (isset($this->MappingIndex[strtolower($originalName)])))
            {
                return $this->MappingIndex[$originalName] ?? $this->MappingIndex[strtolower($originalName)];
            }
            return $originalName;
        }

        protected function mappedType(string $propertyName, $defaultType)
        {
            if((isset($this->PropertyTypes[$propertyName])) || (isset($this->PropertyTypes[strtolower($propertyName)])))
            {
                return $this->PropertyTypes[$propertyName] ?? $this->PropertyTypes[strtolower($propertyName)];
            }
            return $defaultType;
        }
    }