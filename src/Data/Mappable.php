<?php

    namespace Wixnit\Data;

    use Exception;
    use ReflectionClass;
    use ReflectionObject;
    use stdClass;

    abstract class Mappable
    {
        protected array $excludes = [];
        protected array $includes = [];
        protected array $propertyTypes = [];
        protected array $mappingIndex = [];
        protected array $hidden = [];
        protected array $hiddenProperties = [];


        function __construct()
        {
            //first order of business create hidden fields
            for($i = 0; $i < count($this->hidden); $i++)
            {
                $this->hiddenProperties[$this->hidden[$i]] = null;
            }
        }

        /**
         * Get the map of the object
         * @return ObjectMap
         */
        public function getMap(): ObjectMap
        {
            $ret = new ObjectMap();
            $ret->name = get_called_class();

            //get everything using reflection
            $reflection = new ReflectionClass($this);
            $props = $reflection->getProperties();

            for($i = 0; $i < count($props); $i++)
            {
                if(($props[$i]->getModifiers() == 1) && (!$this->isExcluded($props[$i]->getName())))
                {
                    $prop = new ObjectProperty();
                    $prop->name = $props[$i]->getName();
                    $prop->baseName = strtolower($this->mappedName($prop->name));

                    if($props[$i]->getType() != null)
                    {
                        $prop->type = $props[$i]->getType()->getName();

                        if($prop->type == "array")
                        {
                            $prop->isArray = true;
                            $pType = $this->getProposedType($prop->name);

                            if($pType != null)
                            {
                                $prop->type = $pType;
                            }
                        }
                    }
                    else
                    {
                        $prop->type = strtolower(gettype($props[$i]->getValue($this)));

                        if(($prop->type == "array") || ($prop->type == "null"))
                        {
                            $prop->isArray = true;
                            $pType = $this->getProposedType($prop->name);

                            if($pType != null)
                            {
                                $prop->type = $pType;
                            }
                        }
                    }
                    $ret->publicProperties[] = $prop;
                }
            }

            //add hidden fields
            $hiddenKeys = array_keys($this->hiddenProperties);

            for($i = 0; $i < count($hiddenKeys); $i++)
            {
                if (!$this->isExcluded($props[$i]->getName()))
                {
                    $prop = new ObjectProperty();
                    $prop->name = $hiddenKeys[$i];
                    $prop->baseName = strtolower($this->mappedName($prop->name));

                    $pType = $this->getProposedType($hiddenKeys[$i]);

                    if($pType != null)
                    {
                        $prop->type = $pType;
                    }

                    //check if array was chosen as hidden type
                    if($prop->type == "array")
                    {
                        $prop->isArray = true;
                    }
                    $ret->hiddenProperties[] = $prop;
                }
            }
            return $ret;
        }

        /**
         * Map the object to an ObjectMap
         * @param $object
         * @return ObjectMap
         */
        protected static function MapObject($object): ObjectMap
        {
            $reflection = is_object($object) ? new ReflectionObject($object) : new ReflectionClass($object);

            $ret = new ObjectMap();
            $ret->name = $reflection->getShortName();

            $props = $reflection->getProperties();

            for($i = 0; $i < count($props); $i++)
            {
                if($props[$i]->getModifiers() == 1)
                {
                    $prop = new ObjectProperty();
                    $prop->name = $props[$i]->getName();

                    if($props[$i]->getType() != null)
                    {
                        $prop->type = $props[$i]->getType()->getName();
                    }
                    else if(is_object($object))
                    {
                        $prop->type = strtolower(gettype($props[$i]->getValue($object)));
                    }
                    else
                    {
                        $prop->type = null;
                    }
                    $ret->publicProperties[] = $prop;
                }
            }
            return $ret;
        }

        /*
        public static function MapObject($object): ObjectMap
        {
            $reflection = is_object($object) ? new ReflectionObject($object) : new ReflectionClass($object);
            $ret = new ObjectMap();
            $ret->name = $reflection->getShortName();
            
            $props = $reflection->getProperties();

            for($i = 0; $i < count($props); $i++)
            {
                if($props[$i]->getModifiers() == 1)
                {
                    $prop = new ObjectProperty();
                    $prop->name = $props[$i]->getName();
                    $prop->baseName = strtolower($this->mappedName($prop->name));

                    if($props[$i]->getType() != null)
                    {
                        $prop->type = $props[$i]->getType()->getName();

                        if($prop->type == "array")
                        {
                            $prop->isArray = true;
                            $pType = $this->getProposedType($prop->name);

                            if($pType != null)
                            {
                                $prop->type = $pType;
                            }
                        }
                    }
                    else
                    {
                        $prop->type = strtolower(gettype($props[$i]->getValue($object)));
                        if(($prop->type == "array") || ($prop->type == "null"))
                        {
                            $prop->isArray = true;
                            $pType = $this->getProposedType($prop->name);

                            if($pType != null)
                            {
                                $prop->type = $pType;
                            }
                        }
                    }
                    $ret->publicProperties[] = $prop;
                }
            }
            //add hidden fields
            $hiddenKeys = array_keys($this->hiddenProperties);
            for($i = 0; $i < count($hiddenKeys); $i++)
            {
                if (!$this->isExcluded($props[$i]->getName()))
                {
                    $prop = new ObjectProperty();
                    $prop->name = $hiddenKeys[$i];
                    $prop->baseName = strtolower($this->mappedName($prop->name));

                    $pType = $this->getProposedType($hiddenKeys[$i]);

                    if($pType != null)
                    {
                        $prop->type = $pType;
                    }

                    //check if array was chosen as hidden type
                    if($prop->type == "array")
                    {
                        $prop->isArray = true;
                    }
                    $ret->hiddenProperties[] = $prop;
                }
            }
            return $ret;
        }
        */

        /**
         * Populate the object from an associative array or stdClass
         * @param mixed $array_object
         * @param bool $map_hidden
         * @return void
         */
        public function mapFrom($array_object, bool $map_hidden=true): void
        {
            $properties = $this->GetMap();


            if(is_array($array_object))
            {
                for($i = 0; $i < count($properties->publicProperties); $i++)
                {
                    $fieldName = $properties->publicProperties[$i]->name;
                    $fieldName_ = strtolower($properties->publicProperties[$i]->name);

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
                for($i = 0; $i < count($properties->publicProperties); $i++)
                {
                    $fieldName = $properties->publicProperties[$i]->name;
                    $fieldName_ = strtolower($properties->publicProperties[$i]->name);

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
                $hiddenKeys = array_keys($properties->hiddenProperties);

                if(is_array($array_object))
                {
                    for($i = 0; $i < count($hiddenKeys); $i++)
                    {
                        $fieldName = $hiddenKeys[$i];
                        $fieldName_ = strtolower($hiddenKeys[$i]);

                        if(array_key_exists($fieldName, $array_object))
                        {
                            $this->hiddenProperties[$fieldName] = $array_object[$fieldName];
                        }
                        else if(array_key_exists($fieldName_, $array_object))
                        {
                            $this->hiddenProperties[$fieldName] = $array_object[$fieldName_];
                        }
                    }
                }
                else
                {
                    for($i = 0; $i < count($properties->hiddenProperties); $i++)
                    {
                        $fieldName = $properties->hiddenProperties[$i]->name;
                        $fieldName_ = strtolower($properties->hiddenProperties[$i]->name);

                        if($array_object->getProperty($fieldName) != null)
                        {
                            $this->hiddenProperties[$fieldName] = $array_object->getProperty($fieldName);
                        }
                        else if($array_object->getProperty($fieldName_) != null)
                        {
                            $this->hiddenProperties[$fieldName] = $array_object->getProperty($fieldName_);
                        }
                    }
                }
            }
        }

        public function clone($object): void
        {
            $properties = $this->getMap();

            for($i = 0; $i < count($properties->publicProperties); $i++)
            {
                $fieldName = $properties->publicProperties[$i]->name;

                if(isset($object->$fieldName))
                {
                    $this->$fieldName = $object->$fieldName;
                }
            }

            if($object instanceof Mappable)
            {
                $this->hiddenProperties = $object->hiddenProperties;

                $this->excludes = $object->excludes;
                $this->includes = $object->includes;
                $this->propertyTypes = $object->propertyTypes;
                $this->mappingIndex = $object->mappingIndex;
                $this->hidden = $object->hidden;
            }
        }

        public function toSTDClass(bool $includehiddenFields=false): stdClass
        {
            $obj = json_decode(json_encode($this));

            if($includehiddenFields)
            {
                $hidden = array_keys($this->hiddenProperties);

                for($i = 0; $i < count($hidden); $i++)
                {
                    $fName = $hidden[$i];
                    $obj->$fName = $this->hiddenProperties[$hidden[$i]];
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
            if(array_key_exists($propertyName, $this->hiddenProperties))
            {
                $this->hiddenProperties[$propertyName] = $value;
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
            if(array_key_exists($propertyName, $this->hiddenProperties))
            {
                return $this->hiddenProperties[$propertyName];
            }
            if(!isset($this->$propertyName))
            {
                $trace = debug_backtrace();
                throw(new Exception("getProperty() cannot find the propety $propertyName at ".json_encode($trace)));
            }
            return $this->$propertyName;
        }

        //All the private methods that do the background work
        private function getProposedType($property_name): ?string
        {
            return $this->propertyTypes[$property_name] ?? ($this->propertyTypes[strtolower($property_name)] ??  null);
        }

        /**
         * Get all the methods of the class
         * @return array
         */
        protected function getMethods(): array
        {
            $reflection = new ReflectionClass($this);
            return $reflection->getMethods();
        }

        /**
         * Check if a property is excluded from the mapping
         * @param string $propertyName
         * @return bool
         */
        protected function isExcluded(string $propertyName) : bool
        {
            if(in_array(strtolower($propertyName), $this->excludes) || (in_array($propertyName, $this->excludes)))
            {
                return true;
            }
            return false;
        }

        /**
         * @param string $originalName
         * @return string
         * @comment get the mapped name of a property, if not set return the original name
         */
        protected function mappedName(string $originalName)
        {
            if((isset($this->mappingIndex[$originalName])) || (isset($this->mappingIndex[strtolower($originalName)])))
            {
                return $this->mappingIndex[$originalName] ?? $this->mappingIndex[strtolower($originalName)];
            }
            return $originalName;
        }

        /**
         * @param string $propertyName
         * @param mixed $defaultType
         * @return mixed
         * @comment get the type of a property, if not set return the default type
         */
        protected function mappedType(string $propertyName, $defaultType): mixed
        {
            if((isset($this->propertyTypes[$propertyName])) || (isset($this->propertyTypes[strtolower($propertyName)])))
            {
                return $this->propertyTypes[$propertyName] ?? $this->propertyTypes[strtolower($propertyName)];
            }
            return $defaultType;
        }
    }