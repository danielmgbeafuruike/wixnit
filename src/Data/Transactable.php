<?php

    namespace Wixnit\Data;

    use Wixnit\Utilities\Convert;
    use Wixnit\Utilities\Random;
    use Wixnit\Utilities\Range;
    use Wixnit\Utilities\Span;
    use Wixnit\Utilities\Timespan;
    use Wixnit\Utilities\WixDate;
    use Wixnit\Data\Interfaces\ISerializable;
    use ReflectionClass;
use ReflectionEnum;

    abstract class Transactable extends Mappable
    {
        public string $Id = "";
        public WixDate $Created;
        public WixDate $Modified;
        public WixDate $Deleted;

        protected bool $UseSoftDelete = false;
        protected bool $ForceAutoGenId = true;
        protected string $LazyLoadId = "";

        protected array $Serialization = [];
        protected array $Deserialization = [];

        protected array $Unique = [];
        protected array $LongText = [];


        private array $joined_tables = [];

        /**
         * @var array
         * @comment this property is used for remapping so that fields can be properly initialized both from fields and other mapping
         */
        protected array $RenamedProperties = [];

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
            $this->map = $this->GetMap();
            $this->tableName = strtolower(array_reverse(explode("\\", $this->map->Name))[0]);
            $this->idName = strtolower($this->tableName."id");

            $this->InitializeObject($arg);

            //call on created event handler
            $this->onCreated();
        }

        private function InitializeObject($arg)
        {
            if($arg != null)
            {
                $query = $this->buildJoins(
                    DBQuery::With(DB::Connect($this->db, $this->map->DBPrep()))
                        ->Where(new Filter([$this->idName=>$arg, 'deleted'=>0]))
                        ->Limit(1))
                    ->Get();

                if($query->Count > 0)
                {
                    //apply to object
                    $this->fromDBResult($query->Data[0]);

                    //fire event when object is initialized
                    $this->onInitialized();
                }
                else
                {
                    $this->InitializeFields();
                }
            }
            else
            {
                $this->InitializeFields();
            }
        }

        private  function InitializeFields()
        {
            $db = $this->db->GetConnection();

            for($i = 0; $i < count($this->map->PublicProperties); $i++)
            {
                $name = $this->map->PublicProperties[$i]->Name;

                if((!isset($this->$name)) && (strtolower($name) != $this->idName))
                {
                    if(($this->map->PublicProperties[$i]->IsArray) || ($this->map->PublicProperties[$i]->Type == "array"))
                    {
                        $this->$name = [];
                    }
                    else
                    {
                        if(enum_exists($this->map->PublicProperties[$i]->Type))
                        {
                            //cannot assign anything to an enumeration
                        }
                        else if(class_exists($this->map->PublicProperties[$i]->Type))
                        {
                            $objRef = new ReflectionClass($this->map->PublicProperties[$i]->Type);

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
                            if(($this->map->PublicProperties[$i]->Type == "string") || ($this->map->PublicProperties[$i]->Type == "null"))
                            {
                                $this->$name = "";
                            }
                            else if($this->map->PublicProperties[$i]->Type == "int")
                            {
                                $this->$name = 0;
                            }
                            else if(($this->map->PublicProperties[$i]->Type == "float") || ($this->map->PublicProperties[$i]->Type == "double"))
                            {
                                $this->$name = 0.00;
                            }
                            else if($this->map->PublicProperties[$i]->Type == "int")
                            {
                                $this->$name = 0;
                            }
                            else if($this->map->PublicProperties[$i]->Type == "bool")
                            {
                                $this->$name = false;
                            }
                        }
                    }
                }
            }
            for($i = 0; $i < count($this->map->HiddenProperties); $i++)
            {
                $name = $this->map->HiddenProperties[$i]->Name;

                if(strtolower($name) != $this->idName)
                {
                    if(($this->map->HiddenProperties[$i]->IsArray) || ($this->map->HiddenProperties[$i]->Type == "array"))
                    {
                        $this->setProperty($name, []);
                    }
                    else
                    {
                        if(class_exists($this->map->HiddenProperties[$i]->Type))
                        {
                            $objRef = new ReflectionClass($this->map->HiddenProperties[$i]->Type);

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
                            if(($this->map->PublicProperties[$i]->Type == "string") || ($this->map->HiddenProperties[$i]->Type == "null"))
                            {
                                $this->setProperty($name, "");
                            }
                            else if($this->map->HiddenProperties[$i]->Type == "int")
                            {
                                $this->setProperty($name, 0);
                            }
                            else if(($this->map->HiddenProperties[$i]->Type == "float") || ($this->map->HiddenProperties[$i]->Type == "double"))
                            {
                                $this->setProperty($name, 0.00);
                            }
                            else if($this->map->HiddenProperties[$i]->Type == "int")
                            {
                                $this->setProperty($name, 0);
                            }
                            else if($this->map->HiddenProperties[$i]->Type == "bool")
                            {
                                $this->setProperty($name, false);
                            }
                        }
                    }
                }
            }
        }

        public function Save()
        {
            //call the presave event handler
            $this->onPreSave();

            $id = $this->Id;
            $this->Modified = new WixDate(time());

            if(DBQuery::With(DB::Connect($this->db, $this->tableName))->Where([$this->idName=>$id])->Limit(1)->Count() > 0)
            {
                //set the last modified date
                $this->Modified = new WixDate(time());

                DBQuery::With(DB::Connect($this->db, $this->tableName))
                    ->Where([$this->idName=>$id])
                    ->Update($this->toDBObject());

                //call on updated event handler
                $this->onUpdated();
            }
            else
            {
                $this->Created = $this->Modified;

                if(($this->ForceAutoGenId) || ($this->Id == ""))
                {
                    $id = Random::Generate(32);
                    if(DBQuery::With(DB::Connect($this->db, $this->tableName))->Where([$this->idName=>$id])->Limit(1)->Count() > 0)
                    {
                        $id = Random::Generate(32, Random::Alphanumeric);
                    }
                    $this->Id = $id;
                }

                DBQuery::With(DB::Connect($this->db, $this->tableName))
                    ->Insert($this->toDBObject());

                //call on inserted event handler
                $this->onInserted();
            }
            //call the general saved method
            $this->onSaved();
        }

        protected static function buildCollection($conn): DBCollection
        {
            $config = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);
            $db = $config->GetConnection();
            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($db);
            $map = $instance->GetMap();

            $args = func_get_args();
            $query = DBQuery::With(DB::Connect($config, $map->DBPrep()))
            ->Where(["deleted"=>0]);
            $pgn = null;

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof Timespan)
                {
                    $range = new Range(new Span($args[$i]->Start, $args[$i]->Stop));
                    $query = $query->Where(['created'=>[new greaterThan($range->Start, true), new lessThan($range->Stop, true)]]);
                }
                else if(($args[$i] instanceof Filter) || ($args[$i] instanceof FilterBuilder))
                {
                    $query = $query->Where($args[$i]);
                }
                else if($args[$i] instanceof Search)
                {
                    if(count($args[$i]->fields) == 0)
                    {
                        $args[$i]->fields = $instance->getFields();
                    }
                    $query = $query->Search($args[$i]);
                }
                else if ($args[$i] instanceof SearchBuilder)
                {
                    $query = $query->Search($instance->addFieldsToSearchBuilder($args[$i]));
                }
                else if($args[$i] instanceof Order)
                {
                    $query = $query->Order($args[$i]);
                }
                else if($args[$i] instanceof Span)
                {
                    $pgn = Pagination::FromSpan((new Range(new Span($args[$i]->Start, $args[$i]->Stop)))->toSpan());
                    $query = $query->Limit($pgn->Limit)->Offset($pgn->Offset);
                }
            }
            $result = $instance->buildJoins($query)->Get();


            //serialize and add to this object
            $ret = new DBCollection();

            for($i = 0; $i < count($result->Data); $i++)
            {
                $obj = $reflection->newInstance($db);
                $obj->fromDBResult($result->Data[$i]);

                $ret->List[] = $obj;
            }
            $ret->TotalRowCount = $result->Count;
            $ret->Collectionspan->Start = $result->Start;
            $ret->Collectionspan->Stop = $result->Stop;

            if($pgn != null)
            {
                $ret->Meta = DBCollectionMeta::ByPagination($result->Count, $pgn);
            }
            return $ret;
        }

        protected static function fromDeleted($conn): DBCollection
        {
            $config = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);
            $db = $config->GetConnection();
            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($db);
            $map = $instance->GetMap();

            $pgn = null;
            $args = func_get_args();
            $query = DBQuery::With(DB::Connect($config, $map->DBPrep()))
                ->Where(["deleted"=>new notEqual(0)]);


            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof Timespan)
                {
                    $range = new Range(new Span($args[$i]->Start, $args[$i]->Stop));
                    $query = $query->Where(['created'=>[new greaterThan($range->Start, true), new lessThan($range->Stop, true)]]);
                }
                else if(($args[$i] instanceof Filter) || ($args[$i] instanceof FilterBuilder))
                {
                    $query = $query->Where($args[$i]);
                }
                else if($args[$i] instanceof Search)
                {
                    if(count($args[$i]->fields) == 0)
                    {
                        $args[$i]->fields = $instance->getFields();
                    }
                    $query = $query->Search($args[$i]);
                }
                else if ($args[$i] instanceof SearchBuilder)
                {
                    $query = $query->Search($instance->addFieldsToSearchBuilder($args[$i]));
                }
                else if($args[$i] instanceof Order)
                {
                    $query = $query->Order($args[$i]);
                }
                else if($args[$i] instanceof Span)
                {
                    $pgn = Pagination::FromSpan((new Range(new Span($args[$i]->Start, $args[$i]->Stop)))->toSpan());
                    $query = $query->Limit($pgn->Limit)->Offset($pgn->Offset);
                }
            }
            $result = $instance->buildJoins($query)->Get();


            //serialize and add to this object
            $ret = new DBCollection();

            for($i = 0; $i < count($result->Data); $i++)
            {
                $obj = $reflection->newInstance($db);
                $obj->fromDBResult($result->Data[$i]);

                $ret->List[] = $obj;
                $ret->TotalRowCount = $result->Count;
            }
            return $ret;
        }

        protected static function countCollection($conn): int
        {
            $config = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);
            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($config->GetConnection());
            $map = $instance->GetMap();

            $args = func_get_args();
            $query = DBQuery::With(DB::Connect($config, $map->DBPrep()))
                ->Where(["deleted"=>0]);

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof Timespan)
                {
                    $range = new Range(new Span($args[$i]->Start, $args[$i]->Stop));
                    $query = $query->Where(['created'=>[new greaterThan($range->Start, true), new lessThan($range->Stop, true)]]);
                }
                else if(($args[$i] instanceof Filter) || ($args[$i] instanceof FilterBuilder))
                {
                    $query = $query->Where($args[$i]);
                }
                else if($args[$i] instanceof Search)
                {
                    if(count($args[$i]->fields) == 0)
                    {
                        $args[$i]->fields = $instance->getFields();
                    }
                    $query = $query->Search($args[$i]);
                }
                else if ($args[$i] instanceof SearchBuilder)
                {
                    $query = $query->Search($instance->addFieldsToSearchBuilder($args[$i]));
                }
                else if($args[$i] instanceof Order)
                {
                    $query = $query->Order($args[$i]);
                }
                else if($args[$i] instanceof Span)
                {
                    $pgn = Pagination::FromSpan((new Range(new Span($args[$i]->Start, $args[$i]->Stop)))->toSpan());
                    $query = $query->Limit($pgn->Limit)->Offset($pgn->Offset);
                }
            }
            return $instance->buildJoins($query)->Count();
        }

        protected static function deletedCount($conn): int
        {
            $config = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);
            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($config->GetConnection())
                ->Where(["deleted"=>new notEqual(0)]);
            $map = $instance->GetMap();

            $args = func_get_args();
            $query = DBQuery::With(DB::Connect($config, $map->DBPrep()));

            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof Timespan)
                {
                    $range = new Range(new Span($args[$i]->Start, $args[$i]->Stop));
                    $query = $query->Where(['created'=>[new greaterThan($range->Start, true), new lessThan($range->Stop, true)]]);
                }
                else if(($args[$i] instanceof Filter) || ($args[$i] instanceof FilterBuilder))
                {
                    $query = $query->Where($args[$i]);
                }
                else if($args[$i] instanceof Search)
                {
                    if(count($args[$i]->fields) == 0)
                    {
                        $args[$i]->fields = $instance->getFields();
                    }
                    $query = $query->Search($args[$i]);
                }
                else if ($args[$i] instanceof SearchBuilder)
                {
                    $query = $query->Search($instance->addFieldsToSearchBuilder($args[$i]));
                }
                else if($args[$i] instanceof Order)
                {
                    $query = $query->Order($args[$i]);
                }
                else if($args[$i] instanceof Span)
                {
                    $pgn = Pagination::FromSpan((new Range(new Span($args[$i]->Start, $args[$i]->Stop)))->toSpan());
                    $query = $query->Limit($pgn->Limit)->Offset($pgn->Offset);
                }
            }
            return $instance->buildJoins($query)->Count();
        }

        public function Delete()
        {
            if($this->UseSoftDelete)
            {
                //set the objects delete time
                $this->Deleted = new WixDate(time());

                //remove from general access
                DBQuery::With(DB::Connect($this->db, $this->tableName))->Where([$this->idName=>$this->Id])->Update([
                    "deleted"=>time()
                ]);
            }
            else
            {
                DBQuery::With(DB::Connect($this->db, $this->tableName))->Where([$this->idName=>$this->Id])->Delete();
            }

            //call post delete method
            $this->onDeleted();
        }

        protected static function purgeDeleted($conn)
        {
            $config = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);
            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($config->GetConnection());
            $map = $instance->GetMap();

            return DBQuery::With(DB::Connect($config, $map))->Where(["deleted"=>new greaterThan(0)])->Delete();
        }

        public function getDBImage(): DBTable
        {
            $map = $this->GetMap();

            $image = new DBTable();
            $image->Name = strtolower(array_reverse(explode("\\", $map->Name))[0]);


            //add id to the lexer
            $idProp = new DBTableField();
            $idProp->Name = "id";
            $idProp->Type = DBFieldType::Int;
            $idProp->IsPrimary = true;
            $idProp->AutoIncrement = true;
            $image->AddField($idProp);


            $midProp = new DBTableField();
            $midProp->Name = strtolower(array_reverse(explode("\\", $map->Name))[0])."id";
            $midProp->Type = DBFieldType::Varchar;
            $midProp->IsUnique = true;
            $midProp->Length = 64;
            $image->AddField($midProp);


            $createdProp = new DBTableField();
            $createdProp->Name = "created";
            $createdProp->Type = DBFieldType::Int;
            $image->AddField($createdProp);


            $modifiedProp = new DBTableField();
            $modifiedProp->Name = "modified";
            $modifiedProp->Type = DBFieldType::Int;
            $image->AddField($modifiedProp);


            $modifiedProp = new DBTableField();
            $modifiedProp->Name = "deleted";
            $modifiedProp->Type = DBFieldType::Int;
            $image->AddField($modifiedProp);


            //add regular fields
            for($i = 0; $i < count($map->PublicProperties); $i++)
            {
                $prop = $map->PublicProperties[$i];

                if(!$this->isExcluded($prop->Name) && (strtolower($prop->Name) != "id") &&
                    (strtolower($prop->Name) != strtolower($map->Name)."id") &&
                    (strtolower($prop->Name) != "created") && (strtolower($prop->Name) != "modified") &&
                    (strtolower($prop->Name) != "deleted"))
                {
                    $fieldProp = new DBTableField();
                    $fieldProp->Name = strtolower($prop->baseName);

                    if($prop->IsArray)
                    {
                        $fieldProp->Type = DBFieldType::LongText;
                        $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                    }
                    else if(enum_exists($prop->Type))
                    {
                        $ref = new ReflectionEnum($prop->Type);

                        if($ref->isBacked())
                        {
                            $backedType = $ref->getBackingType();

                            if($backedType != null)
                            {
                                if(strtolower($backedType->getName()) == "int")
                                {
                                    $fieldProp->Type = DBFieldType::Int;
                                    $fieldProp->Length = 11;
                                    $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                                }
                                else if(strtolower($backedType->getName()) == "float")
                                {
                                    $fieldProp->Type = DBFieldType::Double;
                                    $fieldProp->Length = 11;
                                    $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                                }
                                else if(strtolower($backedType->getName()) == "string")
                                {
                                    $fieldProp->Type = DBFieldType::Varchar;
                                    $fieldProp->Length = 200;
                                    $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                                }
                                else if(strtolower($backedType->getName()) == "bool")
                                {
                                    $fieldProp->Type = DBFieldType::Int;
                                    $fieldProp->Length = 11;
                                    $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                                }
                                else
                                {
                                    $fieldProp->Type = DBFieldType::Text;
                                    $fieldProp->Length = 100;
                                    $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                                }
                            }
                        }
                        else
                        {
                            $fieldProp->Type = DBFieldType::Text;
                            $fieldProp->Length = 100;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                    }
                    else if(class_exists($prop->Type))
                    {
                        $ref = new ReflectionClass($prop->Type);

                        if($ref->implementsInterface(ISerializable::class))
                        {
                            $obj = ($ref->isSubclassOf(Transactable::class)) ? $ref->newInstance($this->db->GetConnection()) : $ref->newInstance();

                            $fieldProp->Type = $obj->_DBType();
                            $fieldProp->Length = 100;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else if($ref->isSubclassOf(Transactable::class))
                        {
                            $fieldProp->Type = DBFieldType::Varchar;
                            $fieldProp->Length = 64;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else
                        {
                            $fieldProp->Type = DBFieldType::Text;
                            $fieldProp->Length = 100;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                    }
                    else
                    {
                        if($prop->Type == "string")
                        {
                            $fieldProp->Type = ((in_array($prop->Name, $this->LongText) || in_array(strtolower($prop->Name), $this->LongText)) ? DBFieldType::LongText : DBFieldType::Varchar);
                            $fieldProp->Length = 100;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else if($prop->Type == "int")
                        {
                            $fieldProp->Type = DBFieldType::Int;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else if($prop->Type == "bool")
                        {
                            $fieldProp->Type = DBFieldType::Int;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else if(($prop->Type == "float") || ($prop->Type == "double"))
                        {
                            $fieldProp->Type = DBFieldType::Double;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else
                        {
                            $fieldProp->Type = DBFieldType::Text;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                    }
                    $image->AddField($fieldProp);
                }
            }

            //add hidden fields
            for($i = 0; $i < count($map->HiddenProperties); $i++)
            {
                $prop = $map->HiddenProperties[$i];

                if(!$this->isExcluded($prop->Name) && (strtolower($prop->Name) != "id") &&
                    (strtolower($prop->Name) != strtolower($map->Name)."id") &&
                    (strtolower($prop->Name) != "created") && (strtolower($prop->Name) != "modified") &&
                    (strtolower($prop->Name) != "deleted"))
                {
                    $fieldProp = new DBTableField();
                    $fieldProp->Name = strtolower($prop->baseName);

                    if($prop->IsArray)
                    {
                        $fieldProp->Type = DBFieldType::LongText;
                        $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                    }
                    else if(class_exists($prop->Type))
                    {
                        $ref = new ReflectionClass($prop->Type);

                        if($ref->implementsInterface(ISerializable::class))
                        {
                            $obj = ($ref->isSubclassOf(Transactable::class)) ? $ref->newInstance($this->db->GetConnection()) : $ref->newInstance();

                            $fieldProp->Type = $obj->_DBType();
                            $fieldProp->Length = 100;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else if($ref->isSubclassOf(Transactable::class))
                        {
                            $fieldProp->Type = DBFieldType::Varchar;
                            $fieldProp->Length = 64;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else
                        {
                            $fieldProp->Type = DBFieldType::Text;
                            $fieldProp->Length = 100;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                    }
                    else
                    {
                        if($prop->Type == "string")
                        {
                            $fieldProp->Type = ((in_array($prop->Name, $this->LongText) || in_array(strtolower($prop->Name), $this->LongText)) ? DBFieldType::LongText : DBFieldType::Varchar);
                            $fieldProp->Length = 100;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else if($prop->Type == "int")
                        {
                            $fieldProp->Type = DBFieldType::Int;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else if($prop->Type == "bool")
                        {
                            $fieldProp->Type = DBFieldType::Int;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else if(($prop->Type == "float") || ($prop->Type == "double"))
                        {
                            $fieldProp->Type = DBFieldType::Double;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                        else
                        {
                            $fieldProp->Type = DBFieldType::Text;
                            $fieldProp->IsUnique = ((in_array($prop->Name, $this->Unique)) || (in_array(strtolower($prop->Name), $this->Unique)));
                        }
                    }
                    $image->AddField($fieldProp);
                }
            }

            //add included fields (includes)
            for($i = 0; $i < count($this->Includes); $i++)
            {
                $fieldProp = new DBTableField();
                $fieldProp->Name = $this->mappedName(strtolower($this->Includes[$i]));

                if($this->mappedType($this->Includes[$i], 'null') == "array")
                {
                    $fieldProp->Type = DBFieldType::Text;
                    $fieldProp->IsUnique = ((in_array($this->Includes[$i], $this->Unique)) || (in_array(strtolower($this->Includes[$i]), $this->Unique)));

                }
                else if($this->mappedType($this->Includes[$i], 'null') == "string")
                {
                    $fieldProp->Type = ((in_array($this->Includes[$i], $this->LongText) || in_array(strtolower($this->Includes[$i]), $this->LongText)) ? DBFieldType::LongText : DBFieldType::Varchar);;
                    $fieldProp->Length = 100;
                    $fieldProp->IsUnique = ((in_array($this->Includes[$i], $this->Unique)) || (in_array(strtolower($this->Includes[$i]), $this->Unique)));
                }
                else if($this->mappedType($this->Includes[$i], 'null') == "int")
                {
                    $fieldProp->Type = DBFieldType::Int;
                    $fieldProp->IsUnique = ((in_array($this->Includes[$i], $this->Unique)) || (in_array(strtolower($this->Includes[$i]), $this->Unique)));
                }
                else if($this->mappedType($this->Includes[$i], 'null') == "bool")
                {
                    $fieldProp->Type = DBFieldType::Int;
                    $fieldProp->IsUnique = ((in_array($this->Includes[$i], $this->Unique)) || (in_array(strtolower($this->Includes[$i]), $this->Unique)));
                }
                else if($this->mappedType($this->Includes[$i], 'null') == "float")
                {
                    $fieldProp->Type = DBFieldType::Double;
                    $fieldProp->IsUnique = ((in_array($this->Includes[$i], $this->Unique)) || (in_array(strtolower($this->Includes[$i]), $this->Unique)));
                }
                else if($this->mappedType($this->Includes[$i], 'null') == "double")
                {
                    $fieldProp->Type = DBFieldType::Double;
                    $fieldProp->IsUnique = ((in_array($this->Includes[$i], $this->Unique)) || (in_array(strtolower($this->Includes[$i]), $this->Unique)));
                }
                else if($this->mappedType($this->Includes[$i], 'null') == "null")
                {
                    $fieldProp->Type = DBFieldType::Varchar;
                    $fieldProp->Length = 100;
                    $fieldProp->IsUnique = ((in_array($this->Includes[$i], $this->Unique)) || (in_array(strtolower($this->Includes[$i]), $this->Unique)));
                }
                else if(array_reverse(explode("\\", $this->mappedType($this->Includes[$i], 'null')))[0] == "WixDate")
                {
                    $fieldProp->Type = DBFieldType::Int;
                    $fieldProp->IsUnique = ((in_array($this->Includes[$i], $this->Unique)) || (in_array(strtolower($this->Includes[$i]), $this->Unique)));
                }
                else
                {
                    $fieldProp->Type = DBFieldType::Text;
                    $fieldProp->IsUnique = ((in_array($this->Includes[$i], $this->Unique)) || (in_array(strtolower($this->Includes[$i]), $this->Unique)));
                }
                $image->AddField($fieldProp);
            }
            return $image;
        }

        protected function UsesSoftDelete(): bool
        {
            return $this->UseSoftDelete;
        }

        protected static function ListDelete($conn)
        {
            $db = ($conn instanceof DBConfig) ? $conn : DBConfig::Use($conn);

            $reflection = new ReflectionClass(get_called_class());
            $instance = $reflection->newInstance($db->GetConnection());
            $map = $instance->GetMap()->DBPrep();

            $ids = func_get_args();
            $tableName = strtolower(array_reverse(explode("\\", $map->Name))[0]);

            $data = [];

            for($i = 0; $i < count($ids); $i++)
            {
                if(is_string($ids[$i]))
                {
                    $data[] = $ids[$i];
                }
            }

            if($instance->UsesSoftDelete())
            {
                $tm = time();

                return DBQuery::With(DB::Connect($db, $map))->Where(new Filter([$tableName."id"=>$data], Filter::OR))->Update([
                    "deleted"=>$tm
                ]);
            }
            else
            {
                return DBQuery::With(DB::Connect($db, $map))->Where(new Filter([$tableName."id"=>$data], Filter::OR))->Delete();
            }
        }

        protected function isUnique($property): bool
        {
            return false;
        }

        public function toDBObject(): array
        {
            $ret = [];

            $ret[$this->idName] = $this->Id;

            for($i = 0; $i < count($this->map->PublicProperties); $i++)
            {
                $prop = $this->map->PublicProperties[$i];
                $name = $prop->Name;

                if((strtolower($name) != $this->tableName."id") && (strtolower($name) != "id"))
                {
                    $val = $this->$name;

                    if(isset($this->Serialization[$name]) || isset($this->Serialization[strtolower($name)]))
                    {
                        $method = $this->Serialization[$name];
                        $v = $this->$method($val);

                        if(is_object($v) || is_array($v))
                        {
                            if((new ReflectionClass($v))->implementsInterface(ISerializable::class))
                            {
                                $ret[strtolower($prop->baseName)] = $v->_Serialize();
                            }
                            else if($val instanceof Transactable)
                            {
                                $ret[strtolower($prop->baseName)] = $v->Id;
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
                        if((($prop->IsArray) || ($prop->Type == "array")) && (is_array($val)))
                        {
                            $arrData = [];

                            for($j = 0; $j < count($val); $j++)
                            {
                                if(is_object($val[$j]))
                                {
                                    $ref = new ReflectionClass($val[$j]);

                                    if($ref->implementsInterface(ISerializable::class))
                                    {
                                        $arrData[] = $val[$j]->_Serialize();
                                    }
                                    else if($ref->isSubclassOf(Transactable::class))
                                    {
                                        $arrData[] = ($val[$j]->Id != "") ? $val[$j]->Id : (($val[$j]->GetLazyLoadId() != "") ? $val[$j]->GetLazyLoadId() : "");
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
                                    $ret[strtolower($prop->baseName)] = $val->_Serialize();
                                }
                                else if($val instanceof Transactable)
                                {
                                    $ret[strtolower($prop->baseName)] = $val->Id;
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
            for($i = 0; $i < count($this->map->HiddenProperties); $i++)
            {
                $prop = $this->map->HiddenProperties[$i];
                $name = $prop->Name;

                if((strtolower($name) == $this->tableName."id") || (strtolower($name) == "id"))
                {
                    if(strtolower($name) != "id")
                    {
                        $ret[strtolower($name)] = $this->Id;
                    }
                }
                else
                {
                    $val = $this->HiddenProperties[$name];

                    if(isset($this->Serialization[$name]) || isset($this->Serialization[strtolower($name)]))
                    {
                        $method = $this->Serialization[$name];
                        $v = $this->$method($val);

                        if(is_object($v) || is_array($v))
                        {
                            if((new ReflectionClass($v))->implementsInterface(ISerializable::class))
                            {
                                $ret[strtolower($prop->baseName)] = $v->_Serialize();
                            }
                            else if($val instanceof Transactable)
                            {
                                $ret[strtolower($prop->baseName)] = ($v->Id != "") ? $v->Id : (($v->GetLazyLoadId() != "") ? $v->GetLazyLoadId() : "");
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
                        if((($prop->IsArray) || ($prop->Type == "array")) && (is_array($val)))
                        {
                            $arrData = [];

                            for($j = 0; $j < count($val); $j++)
                            {
                                if(is_object($val[$j]))
                                {
                                    $ref = new ReflectionClass($val[$j]);

                                    if($ref->implementsInterface(ISerializable::class))
                                    {
                                        $arrData[] = $val[$j]->_Serialize();
                                    }
                                    else if($ref->isSubclassOf(Transactable::class))
                                    {
                                        $arrData[] = $val[$j]->Id;
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
                                    $ret[strtolower($prop->baseName)] = $val->_Serialize();
                                }
                                else if($val instanceof Transactable)
                                {
                                    $ret[strtolower($prop->baseName)] = $val->Id;
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
                                    $ret[strtolower($prop->baseName)] = $this->HiddenProperties[$name];
                                }
                                else if(is_int($val))
                                {
                                    $ret[strtolower($prop->baseName)] = intval($this->HiddenProperties[$name]);
                                }
                                else if(is_bool($val))
                                {
                                    $ret[strtolower($prop->baseName)] = Convert::ToInt($this->HiddenProperties[$name]);
                                }
                                else if((is_float($val)) || (is_double($val)))
                                {
                                    $ret[strtolower($prop->baseName)] = doubleval($this->HiddenProperties[$name]);
                                }
                                else
                                {
                                    $ret[strtolower($prop->baseName)] = $this->HiddenProperties[$name];
                                }
                            }
                        }
                    }
                }
            }
            return $ret;
        }

        public function fromDBResult($data, int $level=0)
        {
            $ref = new ReflectionClass($this);

            $this->Id = $data[$this->idName] ?? ($data['id'] ?? "");

            for($i = 0; $i < count($this->map->PublicProperties); $i++)
            {
                $prop = $this->map->PublicProperties[$i];

                if((strtolower($prop->Name) != $this->idName) && isset($data[strtolower($prop->baseName)]) && (strtolower($prop->Name) != "id"))
                {
                    if(isset($this->Deserialization[$prop->Name]) || isset($this->Deserialization[strtolower($prop->Name)]))
                    {
                        $method = $this->Deserialization[$prop->Name] ?? $this->Deserialization[strtolower($prop->Name)];
                        $this->$method($data[$prop->baseName]);
                    }
                    else
                    {
                        if(($prop->IsArray) || ($prop->Type == "array"))
                        {
                            $arr = json_decode($data[$prop->baseName]);

                            if(class_exists($prop->Type))
                            {
                                $arr_ref = new ReflectionClass($prop->Type);

                                if($arr_ref->implementsInterface(ISerializable::class))
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = ($arr_ref->isSubclassOf(Transactable::class)) ? $arr_ref->newInstance($this->db->GetConnection()) : $arr_ref->newInstance();
                                        $a->_Deserialize($arr[$x]);
                                        $obj[] = $a;
                                    }
                                    $ref->getProperty($prop->Name)->setValue($this, $obj);
                                }
                                else if($arr_ref->isSubclassOf(Transactable::class))
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = $arr_ref->newInstance($this->db->GetConnection());
                                        $a->LazyLoadId = $arr[$x];
                                        $obj[] = $a;
                                    }
                                    $ref->getProperty($prop->Name)->setValue($this, $obj);
                                }
                                else
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = $arr_ref->newInstance();
                                        $obj[] = ((new ObjectMapper(json_decode($arr[$x])))->mapTo($a, $this->db));
                                    }
                                    $ref->getProperty($prop->Name)->setValue($this, $obj);
                                }
                            }
                            else
                            {
                                $ref->getProperty($prop->Name)->setValue($this, $arr);
                            }
                        }
                        else
                        {
                            if(class_exists($prop->Type))
                            {
                                $objRef = new ReflectionClass($prop->Type);
                                $obj = null;

                                if($objRef->isSubclassOf(Transactable::class))
                                {
                                    $obj = $objRef->newInstance($this->db->GetConnection());
                                }
                                else if(!enum_exists($prop->Type))
                                {
                                    $obj = $objRef->newInstance();
                                }

                                if($objRef->implementsInterface(ISerializable::class))
                                {
                                    $obj->_Deserialize($data[strtolower($prop->baseName)]);
                                }
                                else if($objRef->isSubclassOf(Transactable::class))
                                {
                                    if($level > 0)
                                    {
                                        $obj->LazyLoadId = $data[strtolower($prop->baseName)];
                                    }
                                    else
                                    {
                                        $obj->fromDBResult($this->buildSubProperties($data, $prop->baseName), 1);
                                    }
                                }
                                else if(enum_exists($prop->Type))
                                {
                                    $enumRef = new ReflectionEnum($prop->Type);
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

                                if(enum_exists($prop->Type))
                                {
                                    if($obj != null)
                                    {
                                        $ref->getProperty($prop->Name)->setValue($this, $obj);
                                    }
                                }
                                else
                                {
                                    $ref->getProperty($prop->Name)->setValue($this, $obj);
                                }
                            }
                            else if($prop->Type == "int")
                            {
                                $ref->getProperty($prop->Name)->setValue($this, intval($data[strtolower($prop->baseName)]));
                            }
                            else if(($prop->Type == "double") || ($prop->Type == "float"))
                            {
                                $ref->getProperty($prop->Name)->setValue($this, doubleval($data[strtolower($prop->baseName)]));
                            }
                            else if($prop->Type == "string")
                            {
                                $ref->getProperty($prop->Name)->setValue($this, strval($data[strtolower($prop->baseName)]));
                            }
                            else if($prop->Type == "bool")
                            {
                                $ref->getProperty($prop->Name)->setValue($this, boolval($data[strtolower($prop->baseName)]));
                            }
                            else
                            {
                                $ref->getProperty($prop->Name)->setValue($this, $data[strtolower($prop->baseName)]);
                            }
                        }
                    }
                }
            }
            for($i = 0; $i < count($this->map->HiddenProperties); $i++)
            {
                $prop = $this->map->HiddenProperties[$i];

                if((strtolower($prop->Name) != $this->idName) && isset($data[strtolower($prop->baseName)]) && (strtolower($prop->Name) != "id"))
                {
                    if(isset($this->Deserialization[$prop->Name]) || isset($this->Deserialization[strtolower($prop->Name)]))
                    {
                        $method = $this->Deserialization[$prop->Name] ?? $this->Deserialization[strtolower($prop->Name)];
                        $this->$method($data[$prop->baseName]);
                    }
                    else
                    {
                        if(($prop->IsArray) || ($prop->Type == "array"))
                        {
                            $arr = json_decode($data[$prop->baseName]);

                            if(class_exists($prop->Type))
                            {
                                $arr_ref = new ReflectionClass($prop->Type);

                                if($ref->implementsInterface(ISerializable::class))
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = ($arr_ref->isSubclassOf(Transactable::class)) ? $arr_ref->newInstance($this->db->GetConnection()) : $arr_ref->newInstance();
                                        $a->_Deserialize($arr[$x]);
                                        $obj[] = $a;
                                    }
                                    $this->setProperty($prop->Name, $obj);
                                }
                                else if($ref->isSubclassOf(Transactable::class))
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = $arr_ref->newInstance($this->db->GetConnection());
                                        $a->LazyLoadId = $arr[$x];
                                        $obj[] = $a;
                                    }
                                    $this->setProperty($prop->Name, $obj);
                                }
                                else
                                {
                                    $obj = [];

                                    for($x = 0; $x < count($arr); $x++)
                                    {
                                        $a = $arr_ref->newInstance();
                                        $obj[] = ((new ObjectMapper(json_decode($arr[$x])))->mapTo($a, $this->db));
                                    }
                                    $this->setProperty($prop->Name, $obj);
                                }
                            }
                            else
                            {
                                $this->setProperty($prop->Name, $arr);
                            }
                        }
                        else
                        {
                            if(class_exists($prop->Type))
                            {
                                $objRef = new ReflectionClass($prop->Type);

                                if($objRef->isSubclassOf(Transactable::class))
                                {
                                    $obj = $objRef->newInstance($this->db->GetConnection());

                                    if(isset($this->Deserialization[$prop->Name]) || isset($this->Deserialization[strtolower($prop->Name)]))
                                    {
                                        $method = $this->Deserialization[$prop->Name] ?? $this->Deserialization[strtolower($prop->Name)];
                                        $method($data[strtolower($prop->baseName)]);
                                    }
                                    else if($objRef->implementsInterface(ISerializable::class))
                                    {
                                        $obj->_Deserialize($data[strtolower($prop->baseName)]);
                                    }
                                    else
                                    {
                                        if($level > 0)
                                        {
                                            $obj->LazyLoadId = $data[strtolower($prop->baseName)];
                                        }
                                        else
                                        {
                                            $obj->fromDBResult($this->buildSubProperties($data, $prop->baseName), 1);
                                        }
                                    }
                                    $this->setProperty($prop->Name, $obj);
                                }
                                else
                                {
                                    $obj = $objRef->newInstance();

                                    if(isset($this->Deserialization[$prop->Name]) || isset($this->Deserialization[strtolower($prop->Name)]))
                                    {
                                        $method = $this->Deserialization[$prop->Name] ?? $this->Deserialization[strtolower($prop->Name)];
                                        $method($data[strtolower($prop->baseName)]);
                                    }
                                    else if(isset($this->Deserialization[strtolower($prop->Name)]))
                                    {
                                        $method = $this->Deserialization[strtolower($prop->Name)];
                                        $method($data[strtolower($prop->baseName)]);
                                    }
                                    else if($objRef->implementsInterface(ISerializable::class))
                                    {
                                        $obj->_Deserialize($data[strtolower($prop->baseName)]);
                                    }
                                    else
                                    {
                                        //map data unto the object. data will most likely be json dumped
                                        $json = json_decode($data[strtolower($prop->baseName)]);
                                        $mapper = new ObjectMapper($json);
                                        $obj = $mapper->mapTo($obj, $this->db);
                                    }
                                    $this->setProperty($prop->Name, $obj);
                                }
                            }
                            else if($prop->Type == "int")
                            {
                                $this->setProperty($prop->Name, Convert::ToInt($data[strtolower($prop->baseName)]));
                            }
                            else if(($prop->Type == "double") || ($prop->Type == "float"))
                            {
                                $this->setProperty($prop->Name, doubleval($data[strtolower($prop->baseName)]));
                            }
                            else if($prop->Type == "string")
                            {
                                $this->setProperty($prop->Name, strval($data[strtolower($prop->baseName)]));
                            }
                            else if($prop->Type == "bool")
                            {
                                $this->setProperty($prop->Name, Convert::ToBool($data[strtolower($prop->baseName)]));
                            }
                            else
                            {
                                $this->setProperty($prop->Name, $data[strtolower($prop->baseName)]);
                            }
                        }
                    }
                }
            }
        }

        protected function buildJoins(DBQuery $query): DBQuery
        {
            for($i = 0; $i < count($this->map->PublicProperties); $i++)
            {
                if((!$this->map->PublicProperties[$i]->IsArray) &&
                    (class_exists($this->map->PublicProperties[$i]->Type)) &&
                    ((new ReflectionClass($this->map->PublicProperties[$i]->Type))->isSubclassOf(Transactable::class)))
                {
                    $instance = (new ReflectionClass($this->map->PublicProperties[$i]->Type))->newInstance($this->db->GetConnection());
                    $instanceMap = $instance->GetMap();
                    $instanceName = strtolower(array_reverse(explode("\\", $instanceMap->Name))[0]);

                    $query = $query->Join($instanceMap->DBPrep(), strtolower($this->map->PublicProperties[$i]->baseName), $instanceName."id", DBJoin::Left);
                }
            }
            for($i = 0; $i < count($this->map->HiddenProperties); $i++)
            {
                if((!$this->map->HiddenProperties[$i]->IsArray) &&
                    (class_exists($this->map->HiddenProperties[$i]->Type)) &&
                    ((new ReflectionClass($this->map->HiddenProperties[$i]->Type))->isSubclassOf(Transactable::class)))
                {
                    $instance = (new ReflectionClass($this->map->HiddenProperties[$i]->Type))->newInstance($this->db->GetConnection());
                    $instanceMap = $instance->GetMap();
                    $instanceName = strtolower(array_reverse(explode("\\", $instanceMap->Name))[0]);

                    $query = $query->Join($instanceMap->DBPrep(), strtolower($this->map->HiddenProperties[$i]->baseName), $instanceName."id", DBJoin::Left);
                }
            }
            return $query;
        }

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

        public function getFields(): array
        {
            $ret = [];

            for($i = 0; $i < count($this->map->PublicProperties); $i++)
            {
                $name = $this->map->PublicProperties[$i]->baseName;

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
            for($i = 0; $i < count($this->map->HiddenProperties); $i++)
            {
                $name = $this->map->HiddenProperties[$i]->baseName;

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

        public function __Init()
        {
            if($this->LazyLoadId != "")
            {
                $this->InitializeObject($this->LazyLoadId);
                $this->LazyLoadId = "";
            }
        }

        public function GetLazyLoadId(): ?string
        {
            if($this->LazyLoadId != "")
            {
                return $this->LazyLoadId;
            }
            return  null;
        }

        public function InitObjectArrays()
        {
            if(!$this->hasInitiaizedArrays)
            {
                //establish connection once to be used for all instances
                $db = $this->db->GetConnection();

                for($i = 0; $i < count($this->map->PublicProperties); $i++)
                {
                    if(($this->map->PublicProperties[$i]->IsArray) && class_exists($this->map->PublicProperties[$i]->Type) && ((new ReflectionClass($this->map->PublicProperties[$i]->Type)))->isSubclassOf(Transactable::class))
                    {
                        $ref = new ReflectionClass($this->map->PublicProperties[$i]->Type);
                        $instance = $ref->newInstance($db);

                        $name = $this->map->PublicProperties[$i]->Name;
                        $vals = $this->$name;

                        if(is_array($vals) && (count($vals) > 0))
                        {
                            $ids = [];
                            $query = $instance->buildJoins(DBQuery::With(DB::Connect($this->db, $instance->GetMap()->DBPrep()))
                                ->Where(['deleted'=>0]));

                            for($j = 0; $j < count($vals); $j++)
                            {
                                if($vals[$j] instanceof Transactable)
                                {
                                    $id = $vals[$j]->GetLazyLoadId();

                                    if(($id != null) && (trim($id) != ""))
                                    {
                                        $ids[] = $id;
                                    }
                                }
                            }
                            

                            if(count($ids) > 0) 
                            {
                                $query = $query->Where(new Filter([strtolower(array_reverse(explode("\\", $instance->GetMap()->Name))[0])."id"=>$ids], Filter::OR));
                            }
                            $result = $query->Get();

                            $ret = [];

                            for($k = 0; $k < count($result->Data); $k++)
                            {
                                $obj = $ref->newInstance($db);
                                $obj->fromDBResult($result->Data[$k]);
                                $ret[] = $obj;
                            }
                            $this->$name = $ret;
                        }
                    }
                }
                $this->hasInitiaizedArrays = true;
            }
        }

        ///event methods {methods that will be called as
        protected function onCreated(){}

        protected function onInitialized(){}

        protected function onSaved(){}

        protected function onDeleted(){}

        protected function onUpdated(){}

        protected function onInserted(){}

        protected function onPreSave(){}
    }