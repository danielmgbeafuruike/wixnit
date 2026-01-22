<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Enum\DBJoin;
    use Wixnit\Enum\FilterOperation;
    use Wixnit\Interfaces\ISerializable;
    use Wixnit\Utilities\Convert;
    use Wixnit\Utilities\Random;
    use Wixnit\Utilities\Range;
    use Wixnit\Utilities\Span;
    use Wixnit\Utilities\Timespan;
    use Wixnit\Utilities\Date;
    use ReflectionClass;
    use ReflectionEnum;

    abstract class Transactable extends Mappable
    {
        public string $id = "";
        public Date $created;
        public Date $modified;
        public Date $deleted;

        protected bool $useSoftDelete = false;
        protected bool $forceAutoGenId = true;
        protected string $lazyLoadId = "";

        protected array $serialization = [];
        protected array $deserialization = [];

        protected array $unique = [];
        protected array $longText = [];


        private array $joined_tables = [];

        /**
         * @var array
         * @comment this property is used for remapping so that fields can be properly initialized both from fields and other mapping
         */
        protected array $renamedProperties = [];

        protected DBConfig $db;

        private ObjectMap $map;
        private string $idName = "";


        //keep track of arrays initialization to avoid attempting re-initialization
        private bool $hasInitiaizedArrays = false;


        //variables to be used for internal resolutions
        private string $tableName = "";

        function __construct($conn, $arg=null)
        {
            parent::__construct();

            $this->db = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);
            $this->map = $this->getMap();
            $this->tableName = strtolower(array_reverse(explode("\\", $this->map->name))[0]);
            $this->idName = strtolower($this->tableName."id");

            $this->initializeObject($arg);

            //call on created event handler
            $this->onCreated();
        }

        /**
         * Save the transactable to the database
         * @return void
         */
        public function save(): void
        {
            //call the presave event handler
            $this->onPreSave();

            $id = $this->id;
            $this->modified = new Date(time());

            if(DBQuery::With(DB::Connect($this->db, $this->tableName))->where([$this->idName=>$id])->limit(1)->count() > 0)
            {
                //set the last modified date
                $this->modified = new Date(time());

                DBQuery::With(DB::Connect($this->db, $this->tableName))
                    ->where([$this->idName=>$id])
                    ->update($this->toDBObject());

                //call on updated event handler
                $this->onUpdated();
            }
            else
            {
                $this->created = $this->modified;

                if(($this->forceAutoGenId) || ($this->id == ""))
                {
                    $id = Random::Characters(32);
                    if(DBQuery::With(DB::Connect($this->db, $this->tableName))->where([$this->idName=>$id])->limit(1)->count() > 0)
                    {
                        $id = Random::Characters(32);
                    }
                    $this->id = $id;
                }

                DBQuery::With(DB::Connect($this->db, $this->tableName))
                    ->insert($this->toDBObject());

                //call on inserted event handler
                $this->onInserted();
            }
            //call the general saved method
            $this->onSaved();
        }

        /**
         * Delete the transactable from the database
         * @return void
         */
        public function delete(): void
        {
            if($this->useSoftDelete)
            {
                //set the objects delete time
                $this->deleted = new Date(time());

                //remove from general access
                DBQuery::With(DB::Connect($this->db, $this->tableName))->where([$this->idName=>$this->id])->update([
                    "deleted"=>time()
                ]);
            }
            else
            {
                DBQuery::With(DB::Connect($this->db, $this->tableName))->where([$this->idName=>$this->id])->delete();
            }

            //call post delete method
            $this->onDeleted();
        }

        /**
         * Get DB image of the transactable
         * @return DBTable
         */
        public function getDBImage(): DBTable
        {
            $map = $this->getMap();

            $image = new DBTable();
            $image->name = strtolower(array_reverse(explode("\\", $map->name))[0]);


            //add id to the lexer
            $idProp = new DBTableField();
            $idProp->name = "id";
            $idProp->type = DBFieldType::INT;
            $idProp->isPrimary = true;
            $idProp->autoIncrement = true;
            $image->addField($idProp);


            $midProp = new DBTableField();
            $midProp->name = strtolower(array_reverse(explode("\\", $map->name))[0])."id";
            $midProp->type = DBFieldType::VARCHAR;
            $midProp->isUnique = true;
            $midProp->length = 64;
            $image->addField($midProp);


            $createdProp = new DBTableField();
            $createdProp->name = "created";
            $createdProp->type = DBFieldType::INT;
            $image->addField($createdProp);


            $modifiedProp = new DBTableField();
            $modifiedProp->name = "modified";
            $modifiedProp->type = DBFieldType::INT;
            $image->addField($modifiedProp);


            $modifiedProp = new DBTableField();
            $modifiedProp->name = "deleted";
            $modifiedProp->type = DBFieldType::INT;
            $image->addField($modifiedProp);


            //add regular fields
            for($i = 0; $i < count($map->publicProperties); $i++)
            {
                $prop = $map->publicProperties[$i];

                if(!$this->isExcluded($prop->name) && (strtolower($prop->name) != "id") &&
                    (strtolower($prop->name) != strtolower($map->name)."id") &&
                    (strtolower($prop->name) != "created") && (strtolower($prop->name) != "modified") &&
                    (strtolower($prop->name) != "deleted"))
                {
                    $fieldProp = new DBTableField();
                    $fieldProp->name = strtolower($prop->baseName);

                    if($prop->isArray)
                    {
                        $fieldProp->type = DBFieldType::LONG_TEXT;
                        $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                    }
                    else if(enum_exists($prop->type))
                    {
                        $ref = new ReflectionEnum($prop->type);

                        if($ref->isBacked())
                        {
                            $backedType = $ref->getBackingType();

                            if($backedType != null)
                            {
                                if(strtolower($backedType->getName()) == "int")
                                {
                                    $fieldProp->type = DBFieldType::INT;
                                    $fieldProp->length = 11;
                                    $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                                }
                                else if(strtolower($backedType->getName()) == "float")
                                {
                                    $fieldProp->type = DBFieldType::DOUBLE;
                                    $fieldProp->length = 11;
                                    $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                                }
                                else if(strtolower($backedType->getName()) == "string")
                                {
                                    $fieldProp->type = DBFieldType::VARCHAR;
                                    $fieldProp->length = 200;
                                    $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                                }
                                else if(strtolower($backedType->getName()) == "bool")
                                {
                                    $fieldProp->type = DBFieldType::INT;
                                    $fieldProp->length = 11;
                                    $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                                }
                                else
                                {
                                    $fieldProp->type = DBFieldType::TEXT;
                                    $fieldProp->length = 100;
                                    $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                                }
                            }
                        }
                        else
                        {
                            $fieldProp->type = DBFieldType::TEXT;
                            $fieldProp->length = 100;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                    }
                    else if(class_exists($prop->type))
                    {
                        $ref = new ReflectionClass($prop->type);

                        if($ref->implementsInterface(ISerializable::class))
                        {
                            $obj = ($ref->isSubclassOf(Transactable::class)) ? $ref->newInstance($this->db->getConnection()) : $ref->newInstance();

                            $fieldProp->type = $obj->_dbType();
                            $fieldProp->length = 100;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else if($ref->isSubclassOf(Transactable::class))
                        {
                            $fieldProp->type = DBFieldType::VARCHAR;
                            $fieldProp->length = 64;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else
                        {
                            $fieldProp->type = DBFieldType::TEXT;
                            $fieldProp->length = 100;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                    }
                    else
                    {
                        if($prop->type == "string")
                        {
                            $fieldProp->type = ((in_array($prop->name, $this->longText) || in_array(strtolower($prop->name), $this->longText)) ? DBFieldType::LONG_TEXT : DBFieldType::VARCHAR);
                            $fieldProp->length = 100;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else if($prop->type == "int")
                        {
                            $fieldProp->type = DBFieldType::INT;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else if($prop->type == "bool")
                        {
                            $fieldProp->type = DBFieldType::INT;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else if(($prop->type == "float") || ($prop->type == "double"))
                        {
                            $fieldProp->type = DBFieldType::DOUBLE;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else
                        {
                            $fieldProp->type = DBFieldType::TEXT;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                    }
                    $image->addField($fieldProp);
                }
            }

            //add hidden fields
            for($i = 0; $i < count($map->hiddenProperties); $i++)
            {
                $prop = $map->hiddenProperties[$i];

                if(!$this->isExcluded($prop->name) && (strtolower($prop->name) != "id") &&
                    (strtolower($prop->name) != strtolower($map->name)."id") &&
                    (strtolower($prop->name) != "created") && (strtolower($prop->name) != "modified") &&
                    (strtolower($prop->name) != "deleted"))
                {
                    $fieldProp = new DBTableField();
                    $fieldProp->name = strtolower($prop->baseName);

                    if($prop->isArray)
                    {
                        $fieldProp->type = DBFieldType::LONG_TEXT;
                        $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                    }
                    else if(class_exists($prop->type))
                    {
                        $ref = new ReflectionClass($prop->type);

                        if($ref->implementsInterface(ISerializable::class))
                        {
                            $obj = ($ref->isSubclassOf(Transactable::class)) ? $ref->newInstance($this->db->getConnection()) : $ref->newInstance();

                            $fieldProp->type = $obj->_dbType();
                            $fieldProp->length = 100;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else if($ref->isSubclassOf(Transactable::class))
                        {
                            $fieldProp->type = DBFieldType::VARCHAR;
                            $fieldProp->length = 64;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else
                        {
                            $fieldProp->type = DBFieldType::TEXT;
                            $fieldProp->length = 100;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                    }
                    else
                    {
                        if($prop->type == "string")
                        {
                            $fieldProp->type = ((in_array($prop->name, $this->longText) || in_array(strtolower($prop->name), $this->longText)) ? DBFieldType::LONG_TEXT : DBFieldType::VARCHAR);
                            $fieldProp->length = 100;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else if($prop->type == "int")
                        {
                            $fieldProp->type = DBFieldType::INT;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else if($prop->type == "bool")
                        {
                            $fieldProp->type = DBFieldType::INT;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else if(($prop->type == "float") || ($prop->type == "double"))
                        {
                            $fieldProp->type = DBFieldType::DOUBLE;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                        else
                        {
                            $fieldProp->type = DBFieldType::TEXT;
                            $fieldProp->isUnique = ((in_array($prop->name, $this->unique)) || (in_array(strtolower($prop->name), $this->unique)));
                        }
                    }
                    $image->addField($fieldProp);
                }
            }

            //add included fields (includes)
            for($i = 0; $i < count($this->includes); $i++)
            {
                $fieldProp = new DBTableField();
                $fieldProp->name = $this->mappedName(strtolower($this->includes[$i]));

                if($this->mappedType($this->includes[$i], 'null') == "array")
                {
                    $fieldProp->type = DBFieldType::TEXT;
                    $fieldProp->isUnique = ((in_array($this->includes[$i], $this->unique)) || (in_array(strtolower($this->includes[$i]), $this->unique)));

                }
                else if($this->mappedType($this->includes[$i], 'null') == "string")
                {
                    $fieldProp->type = ((in_array($this->includes[$i], $this->longText) || in_array(strtolower($this->includes[$i]), $this->longText)) ? DBFieldType::LONG_TEXT : DBFieldType::VARCHAR);;
                    $fieldProp->length = 100;
                    $fieldProp->isUnique = ((in_array($this->includes[$i], $this->unique)) || (in_array(strtolower($this->includes[$i]), $this->unique)));
                }
                else if($this->mappedType($this->includes[$i], 'null') == "int")
                {
                    $fieldProp->type = DBFieldType::INT;
                    $fieldProp->isUnique = ((in_array($this->includes[$i], $this->unique)) || (in_array(strtolower($this->includes[$i]), $this->unique)));
                }
                else if($this->mappedType($this->includes[$i], 'null') == "bool")
                {
                    $fieldProp->type = DBFieldType::INT;
                    $fieldProp->isUnique = ((in_array($this->includes[$i], $this->unique)) || (in_array(strtolower($this->includes[$i]), $this->unique)));
                }
                else if($this->mappedType($this->includes[$i], 'null') == "float")
                {
                    $fieldProp->type = DBFieldType::DOUBLE;
                    $fieldProp->isUnique = ((in_array($this->includes[$i], $this->unique)) || (in_array(strtolower($this->includes[$i]), $this->unique)));
                }
                else if($this->mappedType($this->includes[$i], 'null') == "double")
                {
                    $fieldProp->type = DBFieldType::DOUBLE;
                    $fieldProp->isUnique = ((in_array($this->includes[$i], $this->unique)) || (in_array(strtolower($this->includes[$i]), $this->unique)));
                }
                else if($this->mappedType($this->includes[$i], 'null') == "null")
                {
                    $fieldProp->type = DBFieldType::VARCHAR;
                    $fieldProp->length = 100;
                    $fieldProp->isUnique = ((in_array($this->includes[$i], $this->unique)) || (in_array(strtolower($this->includes[$i]), $this->unique)));
                }
                else if(array_reverse(explode("\\", $this->mappedType($this->includes[$i], 'null')))[0] == "Date")
                {
                    $fieldProp->type = DBFieldType::INT;
                    $fieldProp->isUnique = ((in_array($this->includes[$i], $this->unique)) || (in_array(strtolower($this->includes[$i]), $this->unique)));
                }
                else
                {
                    $fieldProp->type = DBFieldType::TEXT;
                    $fieldProp->isUnique = ((in_array($this->includes[$i], $this->unique)) || (in_array(strtolower($this->includes[$i]), $this->unique)));
                }
                $image->addField($fieldProp);
            }
            return $image;
        }

        /**
         * Convert transactable data to a format that can be easily saved to the db
         * @return array
         */
        public function toDBObject(): array
        {
            $ret = [];

            $ret[$this->idName] = $this->id;

            for($i = 0; $i < count($this->map->publicProperties); $i++)
            {
                $prop = $this->map->publicProperties[$i];
                $name = $prop->name;

                if((strtolower($name) != $this->tableName."id") && (strtolower($name) != "id"))
                {
                    $val = $this->$name;

                    if(isset($this->serialization[$name]) || isset($this->serialization[strtolower($name)]))
                    {
                        $method = $this->serialization[$name];
                        $v = $this->$method($val);

                        if(is_object($v) || is_array($v))
                        {
                            if((new ReflectionClass($v))->implementsInterface(ISerializable::class))
                            {
                                $ret[strtolower($prop->baseName)] = $v->_serialize();
                            }
                            else if($val instanceof Transactable)
                            {
                                $ret[strtolower($prop->baseName)] = $v->id;
                            }
                            else
                            {
                                $ret[strtolower($prop->baseName)] = json_encode($v);
                            }
                        }
                        else
                        {
                            $ret[strtolower($prop->baseName)] = $v;
                        }
                    }
                    else
                    {
                        if((($prop->isArray) || ($prop->type == "array")) && (is_array($val)))
                        {
                            $arrData = [];

                            for($j = 0; $j < count($val); $j++)
                            {
                                if(is_object($val[$j]))
                                {
                                    $ref = new ReflectionClass($val[$j]);

                                    if($ref->implementsInterface(ISerializable::class))
                                    {
                                        $arrData[] = $val[$j]->_serialize();
                                    }
                                    else if($ref->isSubclassOf(Transactable::class))
                                    {
                                        $arrData[] = ($val[$j]->id != "") ? $val[$j]->id : (($val[$j]->getLazyLoadId() != "") ? $val[$j]->getLazyLoadId() : "");
                                    }
                                    else if($ref->isEnum())
                                    {
                                        $arrData[] = $val[$j]->value;
                                    }
                                    else
                                    {
                                        $arrData[] = json_encode($val[$j]);
                                    }
                                }
                                else
                                {
                                    $arrData[] = $val[$j];
                                }
                            }
                            $ret[strtolower($prop->baseName)] = json_encode($arrData);
                        }
                        else
                        {
                            if(enum_exists($prop->type))
                            {
                                $ref = new ReflectionEnum($prop->type);

                                if($ref->isBacked())
                                {
                                    if($ref->getBackingType() == "int")
                                    {
                                        if(isset($val->value))
                                        {
                                            $ret[strtolower($prop->baseName)] = intval($val->value);
                                        }
                                        else
                                        {
                                            $ret[strtolower($prop->baseName)] = -1;
                                        }
                                    }
                                    else if($ref->getBackingType() == "string")
                                    {
                                        if(isset($val->value))
                                        {
                                            $ret[strtolower($prop->baseName)] = strval($val->value);
                                        }
                                        else
                                        {
                                            $ret[strtolower($prop->baseName)] = "";
                                        }
                                    }
                                    else if($ref->getBackingType() == "float")
                                    {
                                        if(isset($val->value))
                                        {
                                            $ret[strtolower($prop->baseName)] = floatval($val->value);
                                        }
                                        else
                                        {
                                            $ret[strtolower($prop->baseName)] = -1;
                                        }
                                    }
                                    else if($ref->getBackingType() == "double")
                                    {
                                        if(isset($val->value))
                                        {
                                            $ret[strtolower($prop->baseName)] = doubleval($val->value);
                                        }
                                        else
                                        {
                                            $ret[strtolower($prop->baseName)] = -1;
                                        }
                                    }
                                    else
                                    {
                                        $ret[strtolower($prop->baseName)] = $val->value;
                                    }
                                }
                                else
                                {
                                    if(isset($val->value))
                                    {
                                        $ret[strtolower($prop->baseName)] = $val->value;
                                    }
                                    else
                                    {
                                        $ret[strtolower($prop->baseName)] = "";
                                    }
                                }
                            }
                            else if(is_object($val))
                            {
                                if((new ReflectionClass($val))->implementsInterface(ISerializable::class))
                                {
                                    $ret[strtolower($prop->baseName)] = $val->_serialize();
                                }
                                else if($val instanceof Transactable)
                                {
                                    $ret[strtolower($prop->baseName)] = $val->id;
                                }
                                else
                                {
                                    $ret[strtolower($prop->baseName)] = json_encode($val);
                                }
                            }
                            else
                            {
                                if(is_string($val))
                                {
                                    $ret[strtolower($prop->baseName)] = $this->$name;
                                }
                                else if(is_int($val))
                                {
                                    $ret[strtolower($prop->baseName)] = intval($this->$name);
                                }
                                else if(is_bool($val))
                                {
                                    $ret[strtolower($prop->baseName)] = Convert::ToInt($this->$name);
                                }
                                else if((is_float($val)) || (is_double($val)))
                                {
                                    $ret[strtolower($prop->baseName)] = doubleval($this->$name);
                                }
                                else
                                {
                                    $ret[strtolower($prop->baseName)] = $this->$name;
                                }
                            }
                        }
                    }
                }
            }
            for($i = 0; $i < count($this->map->hiddenProperties); $i++)
            {
                $prop = $this->map->hiddenProperties[$i];
                $name = $prop->name;

                if((strtolower($name) == $this->tableName."id") || (strtolower($name) == "id"))
                {
                    if(strtolower($name) != "id")
                    {
                        $ret[strtolower($name)] = $this->id;
                    }
                }
                else
                {
                    $val = $this->hiddenProperties[$name];

                    if(isset($this->serialization[$name]) || isset($this->serialization[strtolower($name)]))
                    {
                        $method = $this->serialization[$name];
                        $v = $this->$method($val);

                        if(is_object($v) || is_array($v))
                        {
                            if((new ReflectionClass($v))->implementsInterface(ISerializable::class))
                            {
                                $ret[strtolower($prop->baseName)] = $v->_serialize();
                            }
                            else if($val instanceof Transactable)
                            {
                                $ret[strtolower($prop->baseName)] = ($v->id != "") ? $v->id : (($v->getLazyLoadId() != "") ? $v->getLazyLoadId() : "");
                            }
                            else
                            {
                                $ret[strtolower($prop->baseName)] = json_encode($v);
                            }
                        }
                        else
                        {
                            $ret[strtolower($prop->baseName)] = $v;
                        }
                    }
                    else
                    {
                        if((($prop->isArray) || ($prop->type == "array")) && (is_array($val)))
                        {
                            $arrData = [];

                            for($j = 0; $j < count($val); $j++)
                            {
                                if(is_object($val[$j]))
                                {
                                    $ref = new ReflectionClass($val[$j]);

                                    if($ref->implementsInterface(ISerializable::class))
                                    {
                                        $arrData[] = $val[$j]->_serialize();
                                    }
                                    else if($ref->isSubclassOf(Transactable::class))
                                    {
                                        $arrData[] = $val[$j]->id;
                                    }
                                    else
                                    {
                                        $arrData[] = json_encode($val[$j]);
                                    }
                                }
                                else
                                {
                                    $arrData[] = $val[$j];
                                }
                            }
                            $ret[strtolower($prop->baseName)] = json_encode($arrData);
                        }
                        else
                        {
                            if(is_object($val))
                            {
                                if((new ReflectionClass($val))->implementsInterface(ISerializable::class))
                                {
                                    $ret[strtolower($prop->baseName)] = $val->_serialize();
                                }
                                else if($val instanceof Transactable)
                                {
                                    $ret[strtolower($prop->baseName)] = $val->id;
                                }
                                else
                                {
                                    $ret[strtolower($prop->baseName)] = json_encode($val);
                                }
                            }
                            else
                            {
                                if(is_string($val))
                                {
                                    $ret[strtolower($prop->baseName)] = $this->hiddenProperties[$name];
                                }
                                else if(is_int($val))
                                {
                                    $ret[strtolower($prop->baseName)] = intval($this->hiddenProperties[$name]);
                                }
                                else if(is_bool($val))
                                {
                                    $ret[strtolower($prop->baseName)] = Convert::ToInt($this->hiddenProperties[$name]);
                                }
                                else if((is_float($val)) || (is_double($val)))
                                {
                                    $ret[strtolower($prop->baseName)] = doubleval($this->hiddenProperties[$name]);
                                }
                                else
                                {
                                    $ret[strtolower($prop->baseName)] = $this->hiddenProperties[$name];
                                }
                            }
                        }
                    }
                }
            }
            return $ret;
        }

        /**
         * Hydrate the object from data returned from the database
         * @param mixed $data
         * @param int $level
         * @return void
         */
        public function fromDBResult($data, int $level=0): void
        {
            $ref = new ReflectionClass($this);

            $this->id = $data[$this->idName] ?? ($data['id'] ?? "");

            for($i = 0; $i < count($this->map->publicProperties); $i++)
            {
                $prop = $this->map->publicProperties[$i];

                if((strtolower($prop->name) != $this->idName) && isset($data[strtolower($prop->baseName)]) && (strtolower($prop->name) != "id"))
                {
                    if(isset($this->deserialization[$prop->name]) || isset($this->deserialization[strtolower($prop->name)]))
                    {
                        $method = $this->deserialization[$prop->name] ?? $this->deserialization[strtolower($prop->name)];
                        $this->$method($data[$prop->baseName]);
                    }
                    else
                    {
                        if(($prop->isArray) || ($prop->type == "array"))
                        {
                            $arr = json_decode($data[$prop->baseName]);

                            if(class_exists($prop->type))
                            {
                                $arr_ref = new ReflectionClass($prop->type);

                                if($arr_ref->implementsInterface(ISerializable::class))
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = ($arr_ref->isSubclassOf(Transactable::class)) ? $arr_ref->newInstance($this->db->getConnection()) : $arr_ref->newInstance();
                                        $a->_deserialize($arr[$x]);
                                        $obj[] = $a;
                                    }
                                    $ref->getProperty($prop->name)->setValue($this, $obj);
                                }
                                else if($arr_ref->isSubclassOf(Transactable::class))
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = $arr_ref->newInstance($this->db->getConnection());
                                        $a->lazyLoadId = $arr[$x];
                                        $obj[] = $a;
                                    }
                                    $ref->getProperty($prop->name)->setValue($this, $obj);
                                }
                                else if($arr_ref->isEnum())
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $enumRef = new ReflectionEnum($arr_ref->getName());
                                        $cases = $enumRef->getCases();

                                        for($en = 0; $en < count($cases); $en++)
                                        {
                                            if($cases[$en]->getValue()->value == trim($arr[$x]))
                                            {
                                                $obj[] = $cases[$en]->getValue();
                                                break;
                                            }
                                        }
                                    }
                                    $ref->getProperty($prop->name)->setValue($this, $obj);
                                }
                                else
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = $arr_ref->newInstance();
                                        $obj[] = ((new ObjectMapper(json_decode($arr[$x])))->mapTo($a, $this->db));
                                    }
                                    $ref->getProperty($prop->name)->setValue($this, $obj);
                                }
                            }
                            else
                            {
                                $ref->getProperty($prop->name)->setValue($this, $arr);
                            }
                        }
                        else
                        {
                            if(class_exists($prop->type))
                            {
                                $objRef = new ReflectionClass($prop->type);
                                $obj = null;

                                if($objRef->isSubclassOf(Transactable::class))
                                {
                                    $obj = $objRef->newInstance($this->db->getConnection());
                                }
                                else if(!enum_exists($prop->type))
                                {
                                    $obj = $objRef->newInstance();
                                }

                                if($objRef->implementsInterface(ISerializable::class))
                                {
                                    $obj->_deserialize($data[strtolower($prop->baseName)]);
                                }
                                else if($objRef->isSubclassOf(Transactable::class))
                                {
                                    if($level > 0)
                                    {
                                        $obj->lazyLoadId = $data[strtolower($prop->baseName)];
                                    }
                                    else
                                    {
                                        $obj->fromDBResult($this->buildSubProperties($data, $prop->baseName), 1);
                                    }
                                }
                                else if(enum_exists($prop->type))
                                {
                                    $enumRef = new ReflectionEnum($prop->type);
                                    $cases = $enumRef->getCases();

                                    for($en = 0; $en < count($cases); $en++)
                                    {
                                        if($cases[$en]->getValue()->value == trim($data[strtolower($prop->baseName)], "\""))
                                        {
                                            $obj = $cases[$en]->getValue();
                                            break;
                                        }
                                    }
                                }
                                else
                                {
                                    //map data unto the object. data will most likely be json dumped
                                    $mapper = new ObjectMapper(json_decode($data[strtolower($prop->baseName)]));
                                    $obj = $mapper->mapTo($obj, $this->db);
                                }

                                if(enum_exists($prop->type))
                                {
                                    if($obj != null)
                                    {
                                        $ref->getProperty($prop->name)->setValue($this, $obj);
                                    }
                                }
                                else
                                {
                                    $ref->getProperty($prop->name)->setValue($this, $obj);
                                }
                            }
                            else if($prop->type == "int")
                            {
                                $ref->getProperty($prop->name)->setValue($this, intval($data[strtolower($prop->baseName)]));
                            }
                            else if(($prop->type == "double") || ($prop->type == "float"))
                            {
                                $ref->getProperty($prop->name)->setValue($this, doubleval($data[strtolower($prop->baseName)]));
                            }
                            else if($prop->type == "string")
                            {
                                $ref->getProperty($prop->name)->setValue($this, strval($data[strtolower($prop->baseName)]));
                            }
                            else if($prop->type == "bool")
                            {
                                $ref->getProperty($prop->name)->setValue($this, boolval($data[strtolower($prop->baseName)]));
                            }
                            else
                            {
                                $ref->getProperty($prop->name)->setValue($this, $data[strtolower($prop->baseName)]);
                            }
                        }
                    }
                }
            }
            for($i = 0; $i < count($this->map->hiddenProperties); $i++)
            {
                $prop = $this->map->hiddenProperties[$i];

                if((strtolower($prop->name) != $this->idName) && isset($data[strtolower($prop->baseName)]) && (strtolower($prop->name) != "id"))
                {
                    if(isset($this->deserialization[$prop->name]) || isset($this->deserialization[strtolower($prop->name)]))
                    {
                        $method = $this->deserialization[$prop->name] ?? $this->deserialization[strtolower($prop->name)];
                        $this->$method($data[$prop->baseName]);
                    }
                    else
                    {
                        if(($prop->isArray) || ($prop->type == "array"))
                        {
                            $arr = json_decode($data[$prop->baseName]);

                            if(class_exists($prop->type))
                            {
                                $arr_ref = new ReflectionClass($prop->type);

                                if($ref->implementsInterface(ISerializable::class))
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = ($arr_ref->isSubclassOf(Transactable::class)) ? $arr_ref->newInstance($this->db->getConnection()) : $arr_ref->newInstance();
                                        $a->_deserialize($arr[$x]);
                                        $obj[] = $a;
                                    }
                                    $this->setProperty($prop->name, $obj);
                                }
                                else if($ref->isSubclassOf(Transactable::class))
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = $arr_ref->newInstance($this->db->getConnection());
                                        $a->lazyLoadId = $arr[$x];
                                        $obj[] = $a;
                                    }
                                    $this->setProperty($prop->name, $obj);
                                }
                                else
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = $arr_ref->newInstance();
                                        $obj[] = ((new ObjectMapper(json_decode($arr[$x])))->mapTo($a, $this->db));
                                    }
                                    $this->setProperty($prop->name, $obj);
                                }
                            }
                            else
                            {
                                $this->setProperty($prop->name, $arr);
                            }
                        }
                        else
                        {
                            if(class_exists($prop->type))
                            {
                                $objRef = new ReflectionClass($prop->type);

                                if($objRef->isSubclassOf(Transactable::class))
                                {
                                    $obj = $objRef->newInstance($this->db->getConnection());

                                    if(isset($this->deserialization[$prop->name]) || isset($this->deserialization[strtolower($prop->name)]))
                                    {
                                        $method = $this->deserialization[$prop->name] ?? $this->deserialization[strtolower($prop->name)];
                                        $method($data[strtolower($prop->baseName)]);
                                    }
                                    else if($objRef->implementsInterface(ISerializable::class))
                                    {
                                        $obj->_deserialize($data[strtolower($prop->baseName)]);
                                    }
                                    else
                                    {
                                        if($level > 0)
                                        {
                                            $obj->lazyLoadId = $data[strtolower($prop->baseName)];
                                        }
                                        else
                                        {
                                            $obj->fromDBResult($this->buildSubProperties($data, $prop->baseName), 1);
                                        }
                                    }
                                    $this->setProperty($prop->name, $obj);
                                }
                                else
                                {
                                    $obj = $objRef->newInstance();

                                    if(isset($this->deserialization[$prop->name]) || isset($this->deserialization[strtolower($prop->name)]))
                                    {
                                        $method = $this->deserialization[$prop->name] ?? $this->deserialization[strtolower($prop->name)];
                                        $method($data[strtolower($prop->baseName)]);
                                    }
                                    else if(isset($this->deserialization[strtolower($prop->name)]))
                                    {
                                        $method = $this->deserialization[strtolower($prop->name)];
                                        $method($data[strtolower($prop->baseName)]);
                                    }
                                    else if($objRef->implementsInterface(ISerializable::class))
                                    {
                                        $obj->_deserialize($data[strtolower($prop->baseName)]);
                                    }
                                    else
                                    {
                                        //map data unto the object. data will most likely be json dumped
                                        $json = json_decode($data[strtolower($prop->baseName)]);
                                        $mapper = new ObjectMapper($json);
                                        $obj = $mapper->mapTo($obj, $this->db);
                                    }
                                    $this->setProperty($prop->name, $obj);
                                }
                            }
                            else if($prop->type == "int")
                            {
                                $this->setProperty($prop->name, Convert::ToInt($data[strtolower($prop->baseName)]));
                            }
                            else if(($prop->type == "double") || ($prop->type == "float"))
                            {
                                $this->setProperty($prop->name, doubleval($data[strtolower($prop->baseName)]));
                            }
                            else if($prop->type == "string")
                            {
                                $this->setProperty($prop->name, strval($data[strtolower($prop->baseName)]));
                            }
                            else if($prop->type == "bool")
                            {
                                $this->setProperty($prop->name, Convert::ToBool($data[strtolower($prop->baseName)]));
                            }
                            else
                            {
                                $this->setProperty($prop->name, $data[strtolower($prop->baseName)]);
                            }
                        }
                    }
                }
            }
        }

        /**
         * Summary of getFields
         * @return string[]
         */
        public function getFields(): array
        {
            $ret = [];

            for($i = 0; $i < count($this->map->publicProperties); $i++)
            {
                $name = $this->map->publicProperties[$i]->baseName;

                if((strtolower($name) == $this->tableName."id") || (strtolower($name) == "id"))
                {
                    if(strtolower($name) != "id")
                    {
                        $ret[] = strtolower($name);
                    }
                }
                else
                {
                    $ret[] = strtolower($name);
                }
            }
            /*
            for($i = 0; $i < count($this->map->hiddenProperties); $i++)
            {
                $name = $this->map->hiddenProperties[$i]->baseName;

                if((strtolower($name) == $this->tableName."id") || (strtolower($name) == "id"))
                {
                    if(strtolower($name) != "id")
                    {
                        $ret[] = strtolower($name);
                    }
                }
                else
                {
                    $ret[] = strtolower($name);
                }
            }
            */
            return $ret;
        }


        /**
         * Initialize the transactable object from lazy loaded id
         * @return void
         */
        public function __init(): void
        {
            if($this->lazyLoadId != "")
            {
                $this->initializeObject($this->lazyLoadId);
                $this->lazyLoadId = "";
            }
        }

        /**
         * Get the lazy loaded id
         * @return string|null
         */
        public function getLazyLoadId(): ?string
        {
            if($this->lazyLoadId != "")
            {
                return $this->lazyLoadId;
            }
            return  null;
        }

        /**
         * Initialize the arrays in the tranactable object
         * @return void
         */
        public function initObjectArrays(): void
        {
            if(!$this->hasInitiaizedArrays)
            {
                //establish connection once to be used for all instances
                $db = $this->db->getConnection();

                for($i = 0; $i < count($this->map->publicProperties); $i++)
                {
                    if(($this->map->publicProperties[$i]->isArray) && class_exists($this->map->publicProperties[$i]->type) && ((new ReflectionClass($this->map->publicProperties[$i]->type)))->isSubclassOf(Transactable::class))
                    {
                        $ref = new ReflectionClass($this->map->publicProperties[$i]->type);
                        $instance = $ref->newInstance($db);

                        $name = $this->map->publicProperties[$i]->name;
                        $vals = $this->$name;

                        if(is_array($vals) && (count($vals) > 0))
                        {
                            $ids = [];
                            $query = $instance->buildJoins(DBQuery::With(DB::Connect($this->db, $instance->getMap()->dbPrep()))
                                ->where(['deleted'=>0]));

                            for($j = 0; $j < count($vals); $j++)
                            {
                                if($vals[$j] instanceof Transactable)
                                {
                                    $id = $vals[$j]->getLazyLoadId();

                                    if(($id != null) && (trim($id) != ""))
                                    {
                                        $ids[] = $id;
                                    }
                                }
                            }
                            

                            if(count($ids) > 0) 
                            {
                                $query = $query->where(new Filter([strtolower(array_reverse(explode("\\", $instance->getMap()->name))[0])."id"=>$ids], FilterOperation::OR));
                            }
                            $result = $query->get();

                            $ret = [];

                            for($k = 0; $k < count($result->data); $k++)
                            {
                                $obj = $ref->newInstance($db);
                                $obj->fromDBResult($result->data[$k]);
                                $ret[] = $obj;
                            }
                            $this->$name = $ret;
                        }
                    }
                }
                $this->hasInitiaizedArrays = true;
            }
        }


        #region protected methods

        /**
         * Check if the transactable object uses soft deletion
         * @return bool
         */
        protected function usesSoftDelete(): bool
        {
            return $this->useSoftDelete;
        }

        /**
         * Checks if the value is in the unique array, meaning it's a unique value
         * @param mixed $property
         * @return bool
         */
        protected function isUnique($property): bool
        {
            return false;
        }

        /**
         * Build all the table joins for the transactable object
         * @param \Wixnit\Data\DBQuery $query
         * @return DBQuery
         */
        protected function buildJoins(DBQuery $query): DBQuery
        {
            for($i = 0; $i < count($this->map->publicProperties); $i++)
            {
                if((!$this->map->publicProperties[$i]->isArray) &&
                    (class_exists($this->map->publicProperties[$i]->type)) &&
                    ((new ReflectionClass($this->map->publicProperties[$i]->type))->isSubclassOf(Transactable::class)))
                {
                    $instance = (new ReflectionClass($this->map->publicProperties[$i]->type))->newInstance($this->db->getConnection());
                    $instanceMap = $instance->getMap();
                    $instanceName = strtolower(array_reverse(explode("\\", $instanceMap->name))[0]);

                    $query = $query->join($instanceMap->dbPrep(), strtolower($this->map->publicProperties[$i]->baseName), $instanceName."id", DBJoin::LEFT);
                }
            }
            for($i = 0; $i < count($this->map->hiddenProperties); $i++)
            {
                if((!$this->map->hiddenProperties[$i]->isArray) &&
                    (class_exists($this->map->hiddenProperties[$i]->type)) &&
                    ((new ReflectionClass($this->map->hiddenProperties[$i]->type))->isSubclassOf(Transactable::class)))
                {
                    $instance = (new ReflectionClass($this->map->hiddenProperties[$i]->type))->newInstance($this->db->getConnection());
                    $instanceMap = $instance->getMap();
                    $instanceName = strtolower(array_reverse(explode("\\", $instanceMap->name))[0]);

                    $query = $query->join($instanceMap->dbPrep(), strtolower($this->map->hiddenProperties[$i]->baseName), $instanceName."id", DBJoin::LEFT);
                }
            }
            return $query;
        }
        #endregion


        #region static methods

        /**
         * Remove all the entries that have been deleted using soft delete
         * @param mixed $conn
         * @return DBResult
         */
        protected static function PurgeDeleted($conn): DBResult
        {
            $config = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);
            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($config->getConnection());
            $map = $instance->getMap();

            return DBQuery::With(DB::Connect($config, $map))->where(["deleted"=>new GreaterThan(0)])->delete();
        }

        /**
         * Build a DBCollection using Filters, Searches etc
         * @param mixed $conn
         * @param array $
         * @return DBCollection
         */
        protected static function BuildCollection($conn): DBCollection
        {
            $config = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);
            $db = $config->getConnection();
            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($db);
            $map = $instance->getMap();

            $args = func_get_args();
            $query = DBQuery::With(DB::Connect($config, $map->dbPrep()))
            ->where(["deleted"=>0]);
            $pgn = null;

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof Timespan)
                {
                    $range = new Range(new Span($args[$i]->start, $args[$i]->stop));
                    $query = $query->where(['created'=>[new GreaterThan($range->start, true), new LessThan($range->stop, true)]]);
                }
                else if(($args[$i] instanceof Filter) || ($args[$i] instanceof FilterBuilder))
                {
                    $query = $query->where($args[$i]);
                }
                else if($args[$i] instanceof Search)
                {
                    if(count($args[$i]->fields) == 0)
                    {
                        $args[$i]->fields = $instance->getFields();
                    }
                    $query = $query->search($args[$i]);
                }
                else if ($args[$i] instanceof SearchBuilder)
                {
                    $query = $query->search($instance->addFieldsToSearchBuilder($args[$i]));
                }
                else if($args[$i] instanceof Order)
                {
                    $query = $query->order($args[$i]);
                }
                else if($args[$i] instanceof Span)
                {
                    $pgn = Pagination::FromSpan((new Range(new Span($args[$i]->start, $args[$i]->stop)))->toSpan());
                    $query = $query->limit($pgn->limit)->offset($pgn->offset);
                }
                else if($args[$i] instanceof DistinctOn)
                {
                    for($d = 0; $d < count($args[$i]->fields); $d++)
                    {
                        $query = $query->distinct($args[$i]->fields[$d]);
                    }
                }
                else if($args[$i] instanceof groupBy)
                {
                    $query = $query->groupBy($args[$i]);
                }
            }
            $result = $instance->buildJoins($query)->get();


            //serialize and add to this object
            $ret = new DBCollection();

            for($i = 0; $i < count($result->data); $i++)
            {
                $obj = $reflection->newInstance($db);

                //fire object life span event
                $obj->onCreated();

                //hdrate the object
                $obj->fromDBResult($result->data[$i]);

                //fire object life span event
                $obj->onInitialized();

                $ret->list[] = $obj;
            }
            $ret->totalRowCount = $result->count;
            $ret->collectionSpan->start = $result->start;
            $ret->collectionSpan->stop = $result->stop;

            if($pgn != null)
            {
                $ret->meta = DBCollectionMeta::FromPagination($result->count, $pgn);
            }
            return $ret;
        }

        /**
         * Get Items that's been soft deleted
         * @param mixed $conn
         * @param array $
         * @return DBCollection
         */
        protected static function FromDeleted($conn): DBCollection
        {
            $config = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);
            $db = $config->getConnection();
            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($db);
            $map = $instance->getMap();

            $pgn = null;
            $args = func_get_args();
            $query = DBQuery::With(DB::Connect($config, $map->dbPrep()))
                ->where(["deleted"=>new notEqual(0)]);


            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof Timespan)
                {
                    $range = new Range(new Span($args[$i]->start, $args[$i]->stop));
                    $query = $query->where(['created'=>[new GreaterThan($range->start, true), new LessThan($range->stop, true)]]);
                }
                else if(($args[$i] instanceof Filter) || ($args[$i] instanceof FilterBuilder))
                {
                    $query = $query->where($args[$i]);
                }
                else if($args[$i] instanceof Search)
                {
                    if(count($args[$i]->fields) == 0)
                    {
                        $args[$i]->fields = $instance->getFields();
                    }
                    $query = $query->search($args[$i]);
                }
                else if ($args[$i] instanceof SearchBuilder)
                {
                    $query = $query->search($instance->addFieldsToSearchBuilder($args[$i]));
                }
                else if($args[$i] instanceof Order)
                {
                    $query = $query->order($args[$i]);
                }
                else if($args[$i] instanceof Span)
                {
                    $pgn = Pagination::FromSpan((new Range(new Span($args[$i]->start, $args[$i]->stop)))->toSpan());
                    $query = $query->limit($pgn->limit)->offset($pgn->offset);
                }
                else if($args[$i] instanceof DistinctOn)
                {
                    for($d = 0; $d < count($args[$i]->getValue()); $d++)
                    {
                        $query = $query->distinct($args[$i]->getValue()[$d]);
                    }
                }
                else if($args[$i] instanceof groupBy)
                {
                    $query = $query->groupBy($args[$i]);
                }
            }
            $result = $instance->buildJoins($query)->get();


            //serialize and add to this object
            $ret = new DBCollection();

            for($i = 0; $i < count($result->data); $i++)
            {
                $obj = $reflection->newInstance($db);
                $obj->fromDBResult($result->data[$i]);

                $ret->list[] = $obj;
                $ret->totalRowCount = $result->count;
            }
            return $ret;
        }

        /**
         * Get the number of rows retrieved by processing Filters, Searches etc.
         * @param mixed $conn
         * @param mixed $
         * @return int
         */
        protected static function CountCollection($conn): int
        {
            $config = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);
            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($config->getConnection());
            $map = $instance->getMap();

            $args = func_get_args();
            $query = DBQuery::With(DB::Connect($config, $map->dbPrep()))
                ->where(["deleted"=>0]);

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof Timespan)
                {
                    $range = new Range(new Span($args[$i]->start, $args[$i]->stop));
                    $query = $query->where(['created'=>[new GreaterThan($range->start, true), new LessThan($range->stop, true)]]);
                }
                else if(($args[$i] instanceof Filter) || ($args[$i] instanceof FilterBuilder))
                {
                    $query = $query->where($args[$i]);
                }
                else if($args[$i] instanceof Search)
                {
                    if(count($args[$i]->fields) == 0)
                    {
                        $args[$i]->fields = $instance->getFields();
                    }
                    $query = $query->search($args[$i]);
                }
                else if ($args[$i] instanceof SearchBuilder)
                {
                    $query = $query->search($instance->addFieldsToSearchBuilder($args[$i]));
                }
                else if($args[$i] instanceof Order)
                {
                    $query = $query->order($args[$i]);
                }
                else if($args[$i] instanceof Span)
                {
                    $pgn = Pagination::FromSpan((new Range(new Span($args[$i]->start, $args[$i]->stop)))->toSpan());
                    $query = $query->limit($pgn->limit)->offset($pgn->offset);
                }
                else if($args[$i] instanceof DistinctOn)
                {
                    for($d = 0; $d < count($args[$i]->getValue()); $d++)
                    {
                        $query = $query->distinct($args[$i]->getValue()[$d]);
                    }
                }
                else if($args[$i] instanceof groupBy)
                {
                    $query = $query->groupBy($args[$i]);
                }
            }
            return $instance->buildJoins($query)->count();
        }

        /**
         * Get the number of rows that's been deleted
         * @param mixed $conn
         * @param mixed $
         * @return int
         */
        protected static function DeletedCount($conn): int
        {
            $config = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);
            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($config->getConnection())
                ->where(["deleted"=>new notEqual(0)]);
            $map = $instance->getMap();

            $args = func_get_args();
            $query = DBQuery::With(DB::Connect($config, $map->dbPrep()));

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof Timespan)
                {
                    $range = new Range(new Span($args[$i]->start, $args[$i]->stop));
                    $query = $query->where(['created'=>[new GreaterThan($range->start, true), new LessThan($range->stop, true)]]);
                }
                else if(($args[$i] instanceof Filter) || ($args[$i] instanceof FilterBuilder))
                {
                    $query = $query->where($args[$i]);
                }
                else if($args[$i] instanceof Search)
                {
                    if(count($args[$i]->fields) == 0)
                    {
                        $args[$i]->fields = $instance->getFields();
                    }
                    $query = $query->search($args[$i]);
                }
                else if ($args[$i] instanceof SearchBuilder)
                {
                    $query = $query->search($instance->addFieldsToSearchBuilder($args[$i]));
                }
                else if($args[$i] instanceof Order)
                {
                    $query = $query->order($args[$i]);
                }
                else if($args[$i] instanceof Span)
                {
                    $pgn = Pagination::FromSpan((new Range(new Span($args[$i]->start, $args[$i]->stop)))->toSpan());
                    $query = $query->limit($pgn->limit)->offset($pgn->offset);
                }
                else if($args[$i] instanceof DistinctOn)
                {
                    for($d = 0; $d < count($args[$i]->getValue()); $d++)
                    {
                        $query = $query->distinct($args[$i]->getValue()[$d]);
                    }
                }
                else if($args[$i] instanceof groupBy)
                {
                    $query = $query->groupBy($args[$i]);
                }
            }
            return $instance->buildJoins($query)->count();
        }

        /**
         * Delete a list of items by their object or id
         * @param mixed $conn
         * @param mixed $
         * @return DBResult
         */
        protected static function QuickDelete($conn): DBResult
        {
            $db = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);

            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($db->getConnection());
            $map = $instance->getMap()->dbPrep();

            $ids = func_get_args();
            $tableName = strtolower(array_reverse(explode("\\", $map->name))[0]);

            $data = [];

            for($i = 0; $i < count($ids); $i++)
            {
                if(is_string($ids[$i]))
                {
                    $data[] = $ids[$i];
                }
                else if(is_array($ids[$i]))
                {
                    for($j = 0; $j < count($ids[$i]); $j++)
                    {
                        if(is_string($ids[$i][$j]))
                        {
                            $data[] = $ids[$i][$j];
                        }
                        else if($ids[$i][$j] instanceof Transactable)
                        {
                            $data[] = $ids[$i][$j]->id;
                        }
                    }
                }
                if($ids[$i] instanceof Transactable)
                {
                    $data[] = $ids[$i]->id;
                }
            }

            if($instance->usesSoftDelete())
            {
                $tm = time();

                DBQuery::With(DB::Connect($db, $map))->where(new Filter([$tableName."id"=> $data], FilterOperation::OR))->update([
                    "deleted"=> $tm
                ]);

                ///TODO: return the correct db result
                return new DBResult();
            }
            else
            {
                DBQuery::With(DB::Connect($db, $map))->where(new Filter([$tableName."id"=> $data], FilterOperation::OR))->delete();
                
                ///TODO: return the correct db result
                return new DBResult();
            }
        }


        ///TODO: methods to implement

        /**
         * Save an array of the type
         * @param array $objects
         * @return void
         */
        public static function QuickSave($conn): void
        {
            $db = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);

            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($db->getConnection());
            $map = $instance->getMap()->dbPrep();

            $objs = func_get_args();
            $tableName = strtolower(array_reverse(explode("\\", $map->name))[0]);

            $data = [];

            for($i = 0; $i < count($objs); $i++)
            {
                if(is_array($objs[$i]))
                {
                    for($j = 0; $j < count($objs[$i]); $j++)
                    {
                        if($reflection->isInstance($objs[$i][$j]))
                        {
                            $data[] = $objs[$i][$j]->id;
                        }
                    }
                }
                else if($reflection->isInstance($objs[$i]))
                {
                    $data[] = $objs[$i]->id;
                }
            }

            ///TODO: process and save the data

            
        }
        #endregion


        #region private methods

        /**
         * Initialize the transactable object with default values
         * @param mixed $arg
         * @return void
         */
        private function initializeObject($arg)
        {
            if($arg != null)
            {
                $query = $this->buildJoins(
                    DBQuery::With(DB::Connect($this->db, $this->map->dbPrep()))
                        ->where(new Filter([$this->idName=>$arg, 'deleted'=>0]))
                        ->limit(1))
                    ->get();

                if($query->count > 0)
                {
                    //apply to object
                    $this->fromDBResult($query->data[0]);

                    //fire event when object is initialized
                    $this->onInitialized();
                }
                else
                {
                    $this->initializeFields();
                }
            }
            else
            {
                $this->initializeFields();
            }
        }

        /**
         * Initialize the transactable object with data from the db
         * @return void
         */
        private  function initializeFields()
        {
            $db = $this->db->getConnection();

            for($i = 0; $i < count($this->map->publicProperties); $i++)
            {
                $name = $this->map->publicProperties[$i]->name;

                if((!isset($this->$name)) && (strtolower($name) != $this->idName))
                {
                    if(($this->map->publicProperties[$i]->isArray) || ($this->map->publicProperties[$i]->type == "array"))
                    {
                        $this->$name = [];
                    }
                    else
                    {
                        if(enum_exists($this->map->publicProperties[$i]->type))
                        {
                            //cannot assign anything to an enumeration
                        }
                        else if(class_exists($this->map->publicProperties[$i]->type))
                        {
                            $objRef = new ReflectionClass($this->map->publicProperties[$i]->type);

                            if($objRef->isSubclassOf(Transactable::class))
                            {
                                $this->$name = $objRef->newInstance($db);
                            }
                            else
                            {
                                $this->$name = ObjectMapper::InitializeObject($objRef->newInstance());
                            }
                        }
                        else
                        {
                            if(($this->map->publicProperties[$i]->type == "string") || ($this->map->publicProperties[$i]->type == "null"))
                            {
                                $this->$name = "";
                            }
                            else if($this->map->publicProperties[$i]->type == "int")
                            {
                                $this->$name = 0;
                            }
                            else if(($this->map->publicProperties[$i]->type == "float") || ($this->map->publicProperties[$i]->type == "double"))
                            {
                                $this->$name = 0.00;
                            }
                            else if($this->map->publicProperties[$i]->type == "int")
                            {
                                $this->$name = 0;
                            }
                            else if($this->map->publicProperties[$i]->type == "bool")
                            {
                                $this->$name = false;
                            }
                        }
                    }
                }
            }
            for($i = 0; $i < count($this->map->hiddenProperties); $i++)
            {
                $name = $this->map->hiddenProperties[$i]->name;

                if(strtolower($name) != $this->idName)
                {
                    if(($this->map->hiddenProperties[$i]->isArray) || ($this->map->hiddenProperties[$i]->type == "array"))
                    {
                        $this->setProperty($name, []);
                    }
                    else
                    {
                        if(class_exists($this->map->hiddenProperties[$i]->type))
                        {
                            $objRef = new ReflectionClass($this->map->hiddenProperties[$i]->type);

                            if($objRef->isSubclassOf(Transactable::class))
                            {
                                $this->setProperty($name, $objRef->newInstance($db));
                            }
                            else
                            {
                                $this->setProperty($name, $objRef->newInstance());
                            }
                        }
                        else
                        {
                            if(($this->map->publicProperties[$i]->type == "string") || ($this->map->hiddenProperties[$i]->type == "null"))
                            {
                                $this->setProperty($name, "");
                            }
                            else if($this->map->hiddenProperties[$i]->type == "int")
                            {
                                $this->setProperty($name, 0);
                            }
                            else if(($this->map->hiddenProperties[$i]->type == "float") || ($this->map->hiddenProperties[$i]->type == "double"))
                            {
                                $this->setProperty($name, 0.00);
                            }
                            else if($this->map->hiddenProperties[$i]->type == "int")
                            {
                                $this->setProperty($name, 0);
                            }
                            else if($this->map->hiddenProperties[$i]->type == "bool")
                            {
                                $this->setProperty($name, false);
                            }
                        }
                    }
                }
            }
        }

        /**
         * Build second level properties that have been added through table joins
         * @param mixed $data
         * @param string $property_name
         * @return array
         */
        private function buildSubProperties($data, string $property_name): array
        {
            $ret = [];
            $keys = array_keys($data);
            $name = ((strtolower($property_name) != $this->tableName) ? strtolower($property_name) : "_".strtolower($property_name));

            while(in_array($name, $this->joined_tables))
            {
                $name = "_".$name;
            }

            $this->joined_tables[] = $name;

            for($i = 0; $i < count($keys); $i++)
            {
                $p = explode(strtolower($name."_"), $keys[$i]);

                if(count($p) == 2)
                {
                    $ret[$p[1]] = $data[$keys[$i]];
                }
            }
            return $ret;
        }

        /**
         * Process search builder & prep it for processing
         * @param \Wixnit\Data\SearchBuilder $builder
         * @return SearchBuilder
         */
        private function addFieldsToSearchBuilder(SearchBuilder $builder): SearchBuilder
        {
            for($i = 0; $i< count($builder->searches); $i++)
            {
                if($builder->searches[$i] instanceof Search)
                {
                    if(count($builder->searches[$i]->fields) <= 0)
                    {
                        $builder->searches[$i]->fields = $this->getFields();
                    }
                }
                else if($builder->searches[$i] instanceof SearchBuilder)
                {
                    $builder->searches[$i] = $this->addFieldsToSearchBuilder($builder->searches[$i]);
                }
            }
            return $builder;
        }
        #endregion


        #region event methods
        ///event methods {methods that will be called when events occur}

        /**
         * onCreated is called when the object is created and have not been initiallized
         * @return void
         */
        protected function onCreated(){}

        /**
         * onInitialized is called after the object is created and initialized
         * @return void
         */
        protected function onInitialized(){}

        /**
         * onSaved will be called after the object is updated or saved to the db for the first time
         * @return void
         */
        protected function onSaved(){}

        /**
         * onDelete is called after the object have been deleted from the db
         * @return void
         */
        protected function onDeleted(){}

        /**
         * onUpdated is called after the object is updated
         * @return void
         */
        protected function onUpdated(){}

        /**
         * onInserted is called after the object have been saved for the first time 
         * @return void
         */
        protected function onInserted(){}

        /**
         * onPreSave is called before the object is saved
         * @return void
         */
        protected function onPreSave(){}
        #endregion
    }