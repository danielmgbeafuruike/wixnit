<?php

    namespace Wixnit\Data;

    use ReflectionClass;
    use ReflectionEnum;
    use Wixnit\Enum\DBFieldType;
    use Wixnit\Enum\FilterOperation;
    use Wixnit\Interfaces\ISerializable;
    use Wixnit\Utilities\Date;
    use Wixnit\Utilities\Range;
    use Wixnit\Utilities\Span;
    use Wixnit\Utilities\Timespan;

    class Filter
    {
        public array $parameters = [];
        public array $keys = [];
        protected array $values = [];
        protected $operation = FilterOperation::AND;

        function __construct($parameters=[], $set=FilterOperation::AND)
        {
            if(is_array($parameters))
            {
                $this->keys = array_keys($parameters);

                for($i = 0; $i < count($this->keys); $i++)
                {
                    Identifier::assertSafe($this->keys[$i]);

                    $this->values[] = $parameters[$this->keys[$i]];
                    $this->parameters[] = array($this->keys[$i] => $parameters[$this->keys[$i]]);
                }
            }

            if(($set == FilterOperation::AND) || ($set == FilterOperation::OR))
            {
                $this->operation = $set;
            }
        }

        /**
         * Add parameters to the filter
         * @param mixed $key
         * @param mixed $value
         * @return void
         */
        public function add($key="", $value="")
        {
            if((is_string($key)) && (is_string($value)))
            {
                Identifier::assertSafe($key);

                $this->keys[] = $key;
                $this->values[] = $value;
                $this->parameters[] = array($key => $value);
            }
        }

        /**
         * Get the query for the filter
         * @return DBSQLPrep
         */
        public function getquery(): DBSQLPrep
        {
            $ret = new DBSQLPrep();
            $ret->query = " ";

            for($i = 0; $i < count($this->keys); $i++)
            {
                if($i < count($this->values))
                {
                    if(is_array($this->values[$i]))
                    {
                        for($g = 0; $g < count($this->values[$i]); $g++)
                        {
                            $ret->query .= ((trim($ret->query) != "") ? (($this->operation == FilterOperation::AND) ? " AND " : " OR ") : "");

                            if($this->values[$i][$g] instanceof Span)
                            {
                                $range = new Range($this->values[$i][$g]);
                                $ret->query .= "(".$this->keys[$i]." >= ? AND ".$this->keys[$i]." <= ?)";

                                $ret->values[] = $range->start;
                                $ret->values[] = $range->stop;

                                $ret->types[] = "d";
                                $ret->types[] = "d";
                            }
                            else if($this->values[$i][$g] instanceof GreaterThan)
                            {
                                $ret->query .= $this->keys[$i].">".($this->values[$i][$g]->orEqualTo ? "= " : " ").(($this->values[$i][$g]->value instanceof fieldName) ? $this->values[$i][$g]->value->name : "?");

                                if(!($this->values[$i][$g]->value instanceof FieldName))
                                {
                                    $ret->values[] = $this->values[$i][$g]->value;
                                    $ret->types[] =  is_string($this->values[$i][$g]->value) ? "s" : (is_float($this->values[$i][$g]->value) ? "d" : "i");
                                }
                            }
                            else if($this->values[$i][$g] instanceof LessThan)
                            {
                                $ret->query .= $this->keys[$i]."<".($this->values[$i][$g]->orEqualTo ? "= " : " ").(($this->values[$i][$g]->value instanceof fieldName) ? $this->values[$i][$g]->value->name : "?");

                                if(!($this->values[$i][$g]->value instanceof FieldName))
                                {
                                    $ret->values[] = $this->values[$i][$g]->value;
                                    $ret->types[] =  is_string($this->values[$i][$g]->value) ? "s" : (is_float($this->values[$i][$g]->value) ? "d" : "i");
                                }
                            }
                            else if($this->values[$i][$g] instanceof NotEqual)
                            {
                                for($k = 0; $k < count($this->values[$i][$g]->value); $k++)
                                {
                                    $ret->query .= $this->keys[$i]."!=".(($this->values[$i][$g]->value[$k] instanceof FieldName) ? $this->values[$i][$g]->value[$k]->name : "?");

                                    if(!($this->values[$i][$g]->value[$k] instanceof FieldName))
                                    {
                                        $ret->values[] = $this->values[$i][$g]->value[$k];
                                        $ret->types[] =  is_string($this->values[$i][$g]->value[$k]) ? "s" : (is_float($this->values[$i][$g]->value[$k]) ? "d" : "i");
                                    }
                                }
                            }
                            else if($this->values[$i][$g] instanceof In)
                            {
                                $ret->query .= $this->buildInClause($this->keys[$i], $this->values[$i][$g]->value, false, $ret);
                            }
                            else if($this->values[$i][$g] instanceof NotIn)
                            {
                                $ret->query .= $this->buildInClause($this->keys[$i], $this->values[$i][$g]->value, true, $ret);
                            }
                            else if($this->values[$i][$g] instanceof IsNull)
                            {
                                $ret->query .= $this->keys[$i]." IS NULL";
                            }
                            else if($this->values[$i][$g] instanceof IsNotNull)
                            {
                                $ret->query .= $this->keys[$i]." IS NOT NULL";
                            }
                            else if(is_object($this->values[$i][$g]) && (!($this->values[$i][$g] instanceof FieldName)) && (!($this->values[$i][$g] instanceof \UnitEnum)))
                            {
                                if((new ReflectionClass($this->values[$i][$g]))->implementsInterface(ISerializable::class))
                                {
                                    $ret->query .= ($this->keys[$i]."= ?");

                                    $ret->values[] = $this->values[$i][$g]->_serialize();
                                    $ret->types[] = in_array($this->values[$i][$g]->_dbType(), [DBFieldType::INT, DBFieldType::TINY_INT, DBFieldType::SMALL_INT, DBFieldType::MEDIUM_INT, DBFieldType::BIG_INT]) ? "i" : (in_array($this->values[$i][$g]->_dbType(), [DBFieldType::DECIMAL, DBFieldType::FLOAT, DBFieldType::DOUBLE, DBFieldType::BIT]) ? "d" : "s");
                                }
                                else if($this->values[$i][$g] instanceof Transactable)
                                {
                                    $ret->query .= ($this->keys[$i]."= ?");

                                    $ret->values[] = $this->values[$i][$g]->id;
                                    $ret->types[] = "s";
                                }
                                else
                                {
                                    $ret->query .= ($this->keys[$i]."= ?");

                                    $ret->values[] = json_encode($this->values[$i][$g]);
                                    $ret->types[] = "s";
                                }
                            }
                            else
                            {
                                $ret->query .= ($this->keys[$i]."=".(($this->values[$i][$g] instanceof FieldName) ? $this->values[$i][$g]->name : "?"));

                                if(!($this->values[$i][$g] instanceof FieldName))
                                {
                                    //$ret->values[] = $this->values[$i][$g];
                                    //$ret->types[] =  (is_string($this->values[$i][$g]) ? "s" : (is_float($this->values[$i][$g]) ? "d" : "i"));
                                
                                    $ret->values[] = (($this->values[$i][$g] instanceof \UnitEnum) ? self::GetEnumvalue($this->values[$i][$g]) : $this->values[$i][$g]);
                                    $ret->types[] = (($this->values[$i][$g] instanceof \UnitEnum) ? self::GetEnumBackingTypeDBCharacter($this->values[$i][$g]) :  (is_string($this->values[$i][$g]) ? "s" : (is_float($this->values[$i][$g]) ? "d" : "i")));
                                }
                            }
                        }
                    }
                    else
                    {
                        $ret->query .= ((trim($ret->query) != "") ? (($this->operation == FilterOperation::AND) ? " AND " : " OR ") : "");

                        if($this->values[$i] instanceof Span)
                        {
                            $range = new Range($this->values[$i]);
                            $ret->query .= "(".$this->keys[$i]." >= ? AND ".$this->keys[$i]." <= ?)";

                            $ret->values[] = $range->start;
                            $ret->values[] = $range->stop;

                            $ret->types[] = "d";
                            $ret->types[] = "d";
                        }
                        else if($this->values[$i] instanceof GreaterThan)
                        {
                            $ret->query .= $this->keys[$i].">".($this->values[$i]->orEqualTo ? "= " : " ").(($this->values[$i]->value instanceof FieldName) ? $this->values[$i]->value->name : "?");

                            if(!($this->values[$i]->value instanceof FieldName))
                            {
                                $ret->values[] = $this->values[$i]->value;
                                $ret->types[] =  is_string($this->values[$i]->value) ? "s" : (is_float($this->values[$i]->value) ? "d" : "i");
                            }
                        }
                        else if($this->values[$i] instanceof LessThan)
                        {
                            $ret->query .= $this->keys[$i]."<".($this->values[$i]->orEqualTo ? "= " : " ").(($this->values[$i]->value instanceof FieldName) ? $this->values[$i]->value->name : "?");

                            if(!($this->values[$i]->value instanceof FieldName))
                            {
                                $ret->values[] = $this->values[$i]->value;
                                $ret->types[] =  is_string($this->values[$i]->value) ? "s" : (is_float($this->values[$i]->value) ? "d" : "i");
                            }
                        }
                        else if($this->values[$i] instanceof NotEqual)
                        {
                            for($k = 0; $k < count($this->values[$i]->value); $k++)
                            {
                                $ret->query .= $this->keys[$i]."!=".(($this->values[$i]->value[$k] instanceof FieldName) ? $this->values[$i]->value[$k]->name : "?");

                                if(!($this->values[$i]->value[$k] instanceof FieldName))
                                {
                                    $ret->values[] = (($this->values[$i]->value[$k] instanceof \UnitEnum) ? self::GetEnumvalue($this->values[$i]->value[$k]) : $this->values[$i]->value[$k]);
                                    $ret->types[] =  (($this->values[$i]->value[$k] instanceof \UnitEnum) ? self::GetEnumBackingTypeDBCharacter($this->values[$i]->value[$k]) :  (is_string($this->values[$i]->value[$k]) ? "s" : (is_float($this->values[$i]->value[$k]) ? "d" : "i")));
                                }
                            }
                        }
                        else if($this->values[$i] instanceof In)
                        {
                            $ret->query .= $this->buildInClause($this->keys[$i], $this->values[$i]->value, false, $ret);
                        }
                        else if($this->values[$i] instanceof NotIn)
                        {
                            $ret->query .= $this->buildInClause($this->keys[$i], $this->values[$i]->value, true, $ret);
                        }
                        else if($this->values[$i] instanceof IsNull)
                        {
                            $ret->query .= $this->keys[$i]." IS NULL";
                        }
                        else if($this->values[$i] instanceof IsNotNull)
                        {
                            $ret->query .= $this->keys[$i]." IS NOT NULL";
                        }
                        else if(is_object($this->values[$i]) && (!($this->values[$i] instanceof FieldName)) && (!($this->values[$i] instanceof \UnitEnum)))
                        {
                            if((new ReflectionClass($this->values[$i]))->implementsInterface(ISerializable::class))
                            {
                                $ret->query .= ($this->keys[$i]."= ?");

                                $ret->values[] = $this->values[$i]->_serialize();
                                $ret->types[] = in_array($this->values[$i]->_dbType(), [DBFieldType::INT, DBFieldType::TINY_INT, DBFieldType::SMALL_INT, DBFieldType::MEDIUM_INT, DBFieldType::BIG_INT]) ? "i" : (in_array($this->values[$i]->_dbType(), [DBFieldType::DECIMAL, DBFieldType::FLOAT, DBFieldType::DOUBLE, DBFieldType::BIT]) ? "d" : "s");
                            }
                            else if($this->values[$i] instanceof Transactable)
                            {
                                $ret->query .= ($this->keys[$i]."= ?");

                                $ret->values[] = $this->values[$i]->id;
                                $ret->types[] = "s";
                            }
                            else
                            {
                                $ret->query .= ($this->keys[$i]."= ?");

                                $ret->values[] = json_encode($this->values[$i]);
                                $ret->types[] = "s";
                            }
                        }
                        else
                        {
                            $ret->query .= ($this->keys[$i]."=".(($this->values[$i] instanceof FieldName) ? $this->values[$i]->name : "?"));

                            if(!($this->values[$i] instanceof FieldName))
                            {
                                $ret->values[] = (($this->values[$i] instanceof \UnitEnum) ? self::GetEnumvalue($this->values[$i]) : $this->values[$i]);
                                $ret->types[] = (($this->values[$i] instanceof \UnitEnum) ? self::GetEnumBackingTypeDBCharacter($this->values[$i]) :  (is_string($this->values[$i]) ? "s" : (is_float($this->values[$i]) ? "d" : "i")));
                            }
                        }
                    }
                }
            }
            $ret->query .= " ";
            return $ret;
        }

        /**
         * Build a "field IN (?, ?, ...)" / "field NOT IN (?, ?, ...)" clause and push
         * its bound values/types onto the given DBSQLPrep. An empty value list renders
         * a condition that is always false (IN) or always true (NOT IN) rather than
         * emitting invalid SQL like "IN ()".
         * @param string $key
         * @param array $values
         * @param bool $negate
         * @param DBSQLPrep $ret
         * @return string
         */
        private function buildInClause(string $key, array $values, bool $negate, DBSQLPrep $ret): string
        {
            if(count($values) == 0)
            {
                return $negate ? "1=1" : "1=0";
            }

            $placeholders = [];

            for($k = 0; $k < count($values); $k++)
            {
                $placeholders[] = "?";
                $ret->values[] = ($values[$k] instanceof \UnitEnum) ? self::GetEnumvalue($values[$k]) : $values[$k];
                $ret->types[] = ($values[$k] instanceof \UnitEnum) ? self::GetEnumBackingTypeDBCharacter($values[$k]) : (is_string($values[$k]) ? "s" : (is_float($values[$k]) ? "d" : "i"));
            }

            return $key.($negate ? " NOT IN (" : " IN (").implode(", ", $placeholders).")";
        }


        #region static methods

        /**
         * Build a Filter scoped to a joined relation (an auto-joined typed property), so
         * callers don't need to know the internal "relation.field" aliasing rule by hand.
         * Usage: Filter::On('wallet', ['amount' => new GreaterThan(100)])
         * @param string $relation the related model's property name on the parent
         * @param array $conditions field => value/operator pairs, same shape as Filter's constructor array
         * @param mixed $set FilterOperation::AND or FilterOperation::OR to combine the conditions
         * @return Filter
         */
        public static function On(string $relation, array $conditions, $set = FilterOperation::AND): Filter
        {
            if(trim($relation) === "")
            {
                throw \Wixnit\Exception\RelationException::EmptyRelationTarget("Filter::On()", "relation");
            }
            if(count($conditions) === 0)
            {
                throw \Wixnit\Exception\RelationException::NoConditionsProvided($relation);
            }

            Identifier::assertSafe($relation);

            $prefixed = [];

            foreach($conditions as $key => $value)
            {
                $prefixed[strtolower($relation).".".$key] = $value;
            }
            return new Filter($prefixed, $set);
        }

        /**
         * Get the backing type of an enum value as a database character
         * @param \UnitEnum $value
         * @return string
         */
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

        /**
         * Get the value of an enum
         * @param \UnitEnum $value
         * @return mixed
         */
        public static function GetEnumvalue(\UnitEnum $value)
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

        /**
         * Get a list of items by their creation date
         * @param array $list
         * @param Timespan $timespan
         * @return array
         */
        public static function ByCreationDate($list, Timespan $timespan): array
        {
            $ret = [];
            $range = new Range(new Span($timespan->start, $timespan->stop));

            if(is_array($list))
            {
                for($i = 0; $i < count($list); $i++)
                {
                    if(isset($list[$i]->created))
                    {
                        if(((new Date($list[$i]->created))->toEpochSeconds() >= $range->start) && ((new Date($list[$i]->created))->toEpochSeconds() <= $range->stop))
                        {
                            $ret[] = $list[$i];
                        }
                    }
                }
            }
            return $ret;
        }

        /**
         * Get a list of items by their modified date
         * @param array $list
         * @param Timespan $timespan
         * @return array
         */
        public static function ByModifiedDate($list, Timespan $timespan): array
        {
            $ret = [];
            $range = new Range(new Span($timespan->start, $timespan->stop));

            if(is_array($list))
            {
                for($i = 0; $i < count($list); $i++)
                {
                    if(isset($list[$i]->modified))
                    {
                        if(((new Date($list[$i]->modified))->toEpochSeconds() >= $range->start) && ((new Date($list[$i]->created))->toEpochSeconds() <= $range->stop))
                        {
                            $ret[] = $list[$i];
                        }
                    }
                }
            }
            return $ret;
        }

        /**
         * Get a list of items by their creation date
         * @param array $list
         * @param string $field
         * @param Span $span
         * @return array
         */
        public static function ByRange($list, $field, Span $span): array
        {
            $ret = [];
            if(is_array($list))
            {

            }
            return $ret;
        }

        /**
         * Get a list of items by their field value
         * @param array $list
         * @param string $field
         * @param mixed $value
         * @return array
         */
        public static function Exclusive($list, $field, $value): array
        {
            $ret = [];
            if(is_array($list))
            {

            }
            return $ret;
        }

        /**
         * Remove duplicates from a list
         * @param array $list
         * @return array
         */
        public static function RemoveDuplicates($list): array
        {
            $ret = [];
            $store = [];

            for($i = 0; $i < count($list); $i++)
            {
                if(isset($list[$i]->id))
                {
                    if(!in_array($list[$i]->id, $store))
                    {
                        $ret[] = $list[$i];
                        $store[] = $list[$i]->id;
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

        /**
         * Create a filter builder
         * @return FilterBuilder
         */
        public static function Builder(): FilterBuilder
        {
            $args = func_get_args();
            $builder = new FilterBuilder();

            for($i = 0; $i < count($args); $i++)
            {
                if(!($args[$i] instanceof FilterOperation))
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
        #endregion
    }