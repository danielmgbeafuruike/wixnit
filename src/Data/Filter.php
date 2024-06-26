<?php

    namespace Wixnit\Data;


    use ReflectionEnum;
    use Wixnit\Utilities\Range;
    use Wixnit\Utilities\Span;
    use Wixnit\Utilities\Timespan;
    use Wixnit\Utilities\WixDate;

    class Filter
    {
        public array $Parameters = [];
        public array $keys = [];
        protected array $values = [];
        protected $operation = Filter::AND;

        const AND = 1;
        const OR = 2;

        function __construct($parameters=[], $set=Filter::AND)
        {
            if(is_array($parameters))
            {
                $this->keys = array_keys($parameters);

                for($i = 0; $i < count($this->keys); $i++)
                {
                    $this->values[] = $parameters[$this->keys[$i]];
                    $this->Parameters[] = array($this->keys[$i] => $parameters[$this->keys[$i]]);
                }
            }

            if(($set == Filter::AND) || ($set == Filter::OR))
            {
                $this->operation = $set;
            }
        }

        public static function Builder(): FilterBuilder
        {
            $args = func_get_args();
            $builder = new FilterBuilder();

            for($i = 0; $i < count($args); $i++)
            {
                if(($args[$i] instanceof Filter) || ($args[$i] instanceof FilterBuilder))
                {
                    $builder->add($args[$i]);
                }
                else
                {
                    $builder->setOperation($args[$i]);
                }
            }
            return $builder;
        }

        public function Add($key="", $value="")
        {
            if((is_string($key)) && (is_string($value)))
            {
                $this->keys[] = $key;
                $this->values[] = $value;
                $this->Parameters[] = array($key => $value);
            }
        }

        public function getQuery(): DBSQLPrep
        {
            $ret = new DBSQLPrep();
            $ret->Query = " ";

            for($i = 0; $i < count($this->keys); $i++)
            {
                if($i < count($this->values))
                {
                    if(is_array($this->values[$i]))
                    {
                        for($g = 0; $g < count($this->values[$i]); $g++)
                        {
                            $ret->Query .= ((trim($ret->Query) != "") ? (($this->operation == Filter::AND) ? " AND " : " OR ") : "");

                            if($this->values[$i][$g] instanceof Span)
                            {
                                $range = new Range($this->values[$i][$g]);
                                $ret->Query .= "(".$this->keys[$i]." >= ? AND ".$this->keys[$i]." <= ?)";

                                $ret->Values[] = $range->Start;
                                $ret->Values[] = $range->Stop;

                                $ret->Types[] = "d";
                                $ret->Types[] = "d";
                            }
                            else if($this->values[$i][$g] instanceof greaterThan)
                            {
                                $ret->Query .= $this->keys[$i].">".($this->values[$i][$g]->orEqualTo ? "= " : " ").(($this->values[$i][$g]->Value instanceof fieldName) ? $this->values[$i][$g]->Value->Name : "?");

                                if(!($this->values[$i][$g]->Value instanceof fieldName))
                                {
                                    $ret->Values[] = $this->values[$i][$g]->Value;
                                    $ret->Types[] =  is_string($this->values[$i][$g]->Value) ? "s" : (is_float($this->values[$i][$g]->Value) ? "d" : "i");
                                }
                            }
                            else if($this->values[$i][$g] instanceof lessThan)
                            {
                                $ret->Query .= $this->keys[$i]."<".($this->values[$i][$g]->orEqualTo ? "= " : " ").(($this->values[$i][$g]->Value instanceof fieldName) ? $this->values[$i][$g]->Value->Name : "?");

                                if(!($this->values[$i][$g]->Value instanceof fieldName))
                                {
                                    $ret->Values[] = $this->values[$i][$g]->Value;
                                    $ret->Types[] =  is_string($this->values[$i][$g]->Value) ? "s" : (is_float($this->values[$i][$g]->Value) ? "d" : "i");
                                }
                            }
                            else if($this->values[$i][$g] instanceof notEqual)
                            {
                                for($k = 0; $k < count($this->values[$i][$g]->Value); $k++)
                                {
                                    $ret->Query .= $this->keys[$i]."!=".(($this->values[$i][$g]->Value[$k] instanceof fieldName) ? $this->values[$i][$g]->Value[$k]->Name : "?");

                                    if(!($this->values[$i][$g]->Value[$k] instanceof fieldName))
                                    {
                                        $ret->Values[] = $this->values[$i][$g]->Value[$k];
                                        $ret->Types[] =  is_string($this->values[$i][$g]->Value[$k]) ? "s" : (is_float($this->values[$i][$g]->Value[$k]) ? "d" : "i");
                                    }
                                }
                            }
                            else
                            {
                                $ret->Query .= ($this->keys[$i]."=".(($this->values[$i][$g] instanceof fieldName) ? $this->values[$i][$g]->Name : "?"));

                                if(!($this->values[$i][$g] instanceof fieldName))
                                {
                                    $ret->Values[] = $this->values[$i][$g];
                                    $ret->Types[] =  (is_string($this->values[$i][$g]) ? "s" : (is_float($this->values[$i][$g]) ? "d" : "i"));
                                }
                            }
                        }
                    }
                    else
                    {
                        $ret->Query .= ((trim($ret->Query) != "") ? (($this->operation == Filter::AND) ? " AND " : " OR ") : "");

                        if($this->values[$i] instanceof Span)
                        {
                            $range = new Range($this->values[$i]);
                            $ret->Query .= "(".$this->keys[$i]." >= ? AND ".$this->keys[$i]." <= ?)";

                            $ret->Values[] = $range->Start;
                            $ret->Values[] = $range->Stop;

                            $ret->Types[] = "d";
                            $ret->Types[] = "d";
                        }
                        else if($this->values[$i] instanceof greaterThan)
                        {
                            $ret->Query .= $this->keys[$i].">".($this->values[$i]->orEqualTo ? "= " : " ").(($this->values[$i]->Value instanceof fieldName) ? $this->values[$i]->Value->Name : "?");

                            if(!($this->values[$i]->Value instanceof fieldName))
                            {
                                $ret->Values[] = $this->values[$i]->Value;
                                $ret->Types[] =  is_string($this->values[$i]->Value) ? "s" : (is_float($this->values[$i]->Value) ? "d" : "i");
                            }
                        }
                        else if($this->values[$i] instanceof lessThan)
                        {
                            $ret->Query .= $this->keys[$i]."<".($this->values[$i]->orEqualTo ? "= " : " ").(($this->values[$i]->Value instanceof fieldName) ? $this->values[$i]->Value->Name : "?");

                            if(!($this->values[$i]->Value instanceof fieldName))
                            {
                                $ret->Values[] = $this->values[$i]->Value;
                                $ret->Types[] =  is_string($this->values[$i]->Value) ? "s" : (is_float($this->values[$i]->Value) ? "d" : "i");
                            }
                        }
                        else if($this->values[$i] instanceof notEqual)
                        {
                            for($k = 0; $k < count($this->values[$i]->Value); $k++)
                            {
                                $ret->Query .= $this->keys[$i]."!=".(($this->values[$i]->Value[$k] instanceof fieldName) ? $this->values[$i]->Value[$k]->Name : "?");

                                if(!($this->values[$i]->Value[$k] instanceof fieldName))
                                {
                                    $ret->Values[] = (($this->values[$i]->Value[$k] instanceof \UnitEnum) ? self::GetEnumValue($this->values[$i]->Value[$k]) : $this->values[$i]->Value[$k]);
                                    $ret->Types[] =  (($this->values[$i]->Value[$k] instanceof \UnitEnum) ? self::GetEnumBackingTypeDBCharacter($this->values[$i]->Value[$k]) :  (is_string($this->values[$i]->Value[$k]) ? "s" : (is_float($this->values[$i]->Value[$k]) ? "d" : "i")));
                                }
                            }
                        }
                        else
                        {
                            $ret->Query .= ($this->keys[$i]."=".(($this->values[$i] instanceof fieldName) ? $this->values[$i]->Name : "?"));

                            if(!($this->values[$i] instanceof fieldName))
                            {
                                $ret->Values[] = (($this->values[$i] instanceof \UnitEnum) ? self::GetEnumValue($this->values[$i]) : $this->values[$i]);
                                $ret->Types[] = (($this->values[$i] instanceof \UnitEnum) ? self::GetEnumBackingTypeDBCharacter($this->values[$i]) :  (is_string($this->values[$i]) ? "s" : (is_float($this->values[$i]) ? "d" : "i")));
                            }
                        }
                    }
                }
            }
            $ret->Query .= " ";
            return $ret;
        }

        public static function GetEnumBackingTypeDBCharacter(\UnitEnum $value): string
        {
            $ref = new ReflectionEnum($value);

            if($ref->isBacked())
            {
                $type = strtolower($ref->getBackingType());

                if($type == "int")
                {
                    return "i";
                }
                else if(($type == "float") || ($type == "double"))
                {
                    return "d";
                }
                else
                {
                    return "s";
                }
            }
            else
            {
                return "s";
            }
        }

        public static function GetEnumValue(\UnitEnum $value)
        {
            if(isset($value->value))
            {
                return $value->value;
            }
            else
            {
                $ref = new ReflectionEnum($value);

                if($ref->isBacked())
                {
                    $backing = $ref->getBackingType();

                    if((strtolower($backing) == "int") || (strtolower($backing) == "float") || (strtolower($backing) == "double") || strtolower($backing) == "bool")
                    {
                        return -1;
                    }
                }
                return "";
            }
        }

        public static function ByCreationDate($list, Timespan $timespan): array
        {
            $ret = [];
            $range = new Range(new Span($timespan->Start, $timespan->Stop));

            if(is_array($list))
            {
                for($i = 0; $i < count($list); $i++)
                {
                    if(isset($list[$i]->Created))
                    {
                        if(((new WixDate($list[$i]->Created))->getValue() >= $range->Start) && ((new WixDate($list[$i]->Created))->getValue() <= $range->Stop))
                        {
                            $ret[] = $list[$i];
                        }
                    }
                }
            }
            return $ret;
        }

        public static function ByModifiedDate($list, Timespan $timespan): array
        {
            $ret = [];
            $range = new Range(new Span($timespan->Start, $timespan->Stop));

            if(is_array($list))
            {
                for($i = 0; $i < count($list); $i++)
                {
                    if(isset($list[$i]->Modified))
                    {
                        if(((new WixDate($list[$i]->Modified))->getValue() >= $range->Start) && ((new WixDate($list[$i]->Created))->getValue() <= $range->Stop))
                        {
                            $ret[] = $list[$i];
                        }
                    }
                }
            }
            return $ret;
        }

        public static function ByRange($list, $field, Span $span): array
        {
            $ret = [];
            if(is_array($list))
            {

            }
            return $ret;
        }

        public static function Exclusive($list, $field, $value): array
        {
            $ret = [];
            if(is_array($list))
            {

            }
            return $ret;
        }

        public static function removeDuplicates($list): array
        {
            $ret = [];
            $store = [];

            for($i = 0; $i < count($list); $i++)
            {
                if(isset($list[$i]->Id))
                {
                    if(!in_array($list[$i]->Id, $store))
                    {
                        $ret[] = $list[$i];
                        $store[] = $list[$i]->Id;
                    }
                }
                else
                {
                    if(!in_array(json_encode($list[$i]), $store))
                    {
                        $ret[] = $list[$i];
                        $store[] = json_encode($list[$i]);
                    }
                }
            }
            return $ret;
        }
    }