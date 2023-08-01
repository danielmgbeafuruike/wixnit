<?php

    namespace wixnit\Data;

    use ReflectionClass;

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
                        else
                        {
                            $receiving_object->$toName = $this->object->$fromName;
                        }
                    }
                }
                return $receiving_object;
            }
        }

        public static function InitializeObject($object, $db=null)
        {
            $map = $object instanceof Mappable ? $object->GetMap() : ObjectMapper::MapObject($object);

            for($i = 0; $i < count($map->PublicProperties); $i++)
            {
                $name = $map->PublicProperties[$i]->Name;

                if(class_exists($map->PublicProperties[$i]->Type))
                {
                    $objRef = new \ReflectionClass($map->PublicProperties[$i]->Type);

                    if($objRef->isSubclassOf(Transactable::class))
                    {
                        $object->$name = $objRef->newInstance($db);
                    }
                    else
                    {
                        $object->$name = ObjectMapper::InitializeObject($objRef->newInstance(), $db);
                    }
                }
                else
                {
                    if(($map->PublicProperties[$i]->Type == "string") || ($map->PublicProperties[$i]->Type == "null"))
                    {
                        $object->$name = "";
                    }
                    else if($map->PublicProperties[$i]->Type == "int")
                    {
                        $object->$name = 0;
                    }
                    else if(($map->PublicProperties[$i]->Type == "float") || ($map->PublicProperties[$i]->Type == "double"))
                    {
                        $object->$name = 0.00;
                    }
                    else if($map->PublicProperties[$i]->Type == "int")
                    {
                        $object->$name = 0;
                    }
                    else if($map->PublicProperties[$i]->Type == "bool")
                    {
                        $object->$name = false;
                    }
                }
            }

            for($i = 0; $i < count($map->HiddenProperties); $i++)
            {
                $name = $map->HiddenProperties[$i]->Name;

                if(class_exists($map->HiddenProperties[$i]->Type))
                {
                    $objRef = new \ReflectionClass($map->HiddenProperties[$i]->Type);

                    if($objRef->isSubclassOf(Transactable::class))
                    {
                        $object->SetPrperty($name, $objRef->newInstance($db));
                    }
                    else
                    {
                        $object->SetPrperty($name, ObjectMapper::InitializeObject($objRef->newInstance(), $db));
                    }
                }
                else
                {
                    if(($map->HiddenProperties[$i]->Type == "string") || ($map->HiddenProperties[$i]->Type == "null"))
                    {
                        $object->SetPropert($name, "");
                    }
                    else if($map->HiddenProperties[$i]->Type == "int")
                    {
                        $object->SetPropert($name, 0);
                    }
                    else if(($map->HiddenProperties[$i]->Type == "float") || ($map->HiddenProperties[$i]->Type == "double"))
                    {
                        $object->SetPropert($name, 0.00);
                    }
                    else if($map->HiddenProperties[$i]->Type == "int")
                    {
                        $object->SetPropert($name, 0);
                    }
                    else if($map->HiddenProperties[$i]->Type == "bool")
                    {
                        $object->SetPropert($name, false);
                    }
                }
            }
            return $object;
        }

        public static function MapObject($object): ObjectMap
        {
            return parent::MapObject($object);
        }
    }