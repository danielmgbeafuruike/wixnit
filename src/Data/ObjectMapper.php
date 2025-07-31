<?php

    namespace Wixnit\Data;

    use ReflectionClass;
    use ReflectionEnum;

    class ObjectMapper extends Mappable
    {
        public ?ObjectMap $Map = null;
        private $object = null;

        function __construct($template_object)
        {
            parent::__construct();

            if($template_object instanceof Mappable)
            {
                $this->Map = $template_object->GetMap();
            }
            else
            {
                $this->Map = ObjectMapper::MapObject($template_object);
            }
            $this->object = $template_object;
        }

        /**
         * Map othe object to another a similar object
         * @param mixed $receiving_object
         * @param mixed $db
         */
        public function mapTo($receiving_object, $db=null)
        {
            if($this->object != null)
            {
                $objMap = ($receiving_object instanceof Mappable) ? $receiving_object->GetMap() : ObjectMapper::MapObject($receiving_object);

                for($i = 0; $i < count($this->Map->PublicProperties); $i++)
                {
                    $prop = $objMap->GetProperty($this->Map->PublicProperties[$i], false);

                    if($prop != null)
                    {
                        $toName = $prop->Name;
                        $fromName = $this->Map->PublicProperties[$i]->Name;

                        
                        if(class_exists($prop->Type))
                        {
                            if((new ReflectionClass($prop->Type))->isEnum())
                            {
                                $enumRef = new ReflectionEnum($prop->Type);
                                $cases = $enumRef->getCases();

                                for($en = 0; $en < count($cases); $en++)
                                {
                                    if($cases[$en]->getValue()->value == trim($this->object->$fromName, "\""))
                                    {
                                        $receiving_object->$toName = $cases[$en]->getValue();
                                        break;
                                    }
                                }
                            }
                            else
                            {
                                if((new ReflectionClass($prop->Type))->isSubclassOf(Transactable::class))
                                {
                                    $innerInstance = (new ReflectionClass($prop->Type))->newInstance($db);
                                }
                                else
                                {
                                    $innerInstance = (new ReflectionClass($prop->Type))->newInstance();
                                }
                                $innerMapper = new ObjectMapper($this->object->$fromName);
                                $innerMapper->mapTo($innerInstance, $db);

                                $receiving_object->$toName = $innerInstance;
                            }
                        }
                        else
                        {
                            $receiving_object->$toName = $this->object->$fromName;
                        }
                    }
                }
                return $receiving_object;
            }
        }

        #region static methods

        /**
         * Initialize the object from another object of the same type
         * @param mixed $object
         * @param mixed $db
         */
        public static function InitializeObject($object, $db=null): mixed
        {
            $map = $object instanceof Mappable ? $object->getMap() : ObjectMapper::MapObject($object);

            for($i = 0; $i < count($map->publicProperties); $i++)
            {
                $name = $map->publicProperties[$i]->name;

                if(class_exists($map->publicProperties[$i]->type))
                {
                    $objRef = new ReflectionClass($map->publicProperties[$i]->type);

                    if($objRef->isSubclassOf(Transactable::class))
                    {
                        $object->$name = $objRef->newInstance($db);
                    }
                    else if(!$objRef->isEnum())
                    {
                        $object->$name = ObjectMapper::InitializeObject($objRef->newInstance(), $db);
                    }
                }
                else
                {
                    if(($map->publicProperties[$i]->type == "string") || ($map->publicProperties[$i]->type == "null"))
                    {
                        $object->$name = "";
                    }
                    else if($map->publicProperties[$i]->type == "int")
                    {
                        $object->$name = 0;
                    }
                    else if(($map->publicProperties[$i]->type == "float") || ($map->publicProperties[$i]->type == "double"))
                    {
                        $object->$name = 0.00;
                    }
                    else if($map->publicProperties[$i]->type == "int")
                    {
                        $object->$name = 0;
                    }
                    else if($map->publicProperties[$i]->type == "bool")
                    {
                        $object->$name = false;
                    }
                }
            }

            for($i = 0; $i < count($map->hiddenProperties); $i++)
            {
                $name = $map->hiddenProperties[$i]->name;

                if(class_exists($map->hiddenProperties[$i]->type))
                {
                    $objRef = new ReflectionClass($map->hiddenProperties[$i]->type);

                    if($objRef->isSubclassOf(Transactable::class))
                    {
                        $object->setPrperty($name, $objRef->newInstance($db));
                    }
                    else
                    {
                        $object->setPrperty($name, ObjectMapper::InitializeObject($objRef->newInstance(), $db));
                    }
                }
                else
                {
                    if(($map->hiddenProperties[$i]->type == "string") || ($map->hiddenProperties[$i]->type == "null"))
                    {
                        $object->setPropert($name, "");
                    }
                    else if($map->hiddenProperties[$i]->type == "int")
                    {
                        $object->setPropert($name, 0);
                    }
                    else if(($map->hiddenProperties[$i]->type == "float") || ($map->hiddenProperties[$i]->type == "double"))
                    {
                        $object->setPropert($name, 0.00);
                    }
                    else if($map->hiddenProperties[$i]->type == "int")
                    {
                        $object->setPropert($name, 0);
                    }
                    else if($map->hiddenProperties[$i]->type == "bool")
                    {
                        $object->setPropert($name, false);
                    }
                }
            }
            return $object;
        }

        /**
         * Map object to a similar object
         * @param mixed $object
         * @return ObjectMap
         */
        public static function MapObject($object): ObjectMap
        {
            return parent::MapObject($object);
        }
        #endregion
    }