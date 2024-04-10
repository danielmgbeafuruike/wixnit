<?php

    namespace Wixnit\Data;

    use Wixnit\Utilities\Range;
    use Wixnit\Utilities\Span;
    use Wixnit\Utilities\Timespan;
    use Exception;
    use mysqli;

    class DBQuery
    {
        public DB $db;

        protected ?Order $order = null;
        protected ?Pagination $pagination = null;

        protected array $operations = [];
        protected array $fields = [];

        protected array $joins = [];
        private array $joined_tables = [];

        private array $distinct_on = [];
        private string $group_by = "";

        protected array $selectFields = [];


        //post sorting and arranging
        private string $query = "";
        private array $args = [];
        private string $argTypes = "";

        public static function With(DB $database): DBQuery
        {
            $ret = new DBQuery();
            $ret->db = $database;
            return $ret;
        }

        public function Search(): DBQuery
        {
            $args = func_get_args();

            //check if args where passed in the raw form
            if((count($args) == 2) && (is_string($args[0]) && is_array($args[1])))
            {
                $this->operations[] = new Search($args[0], $args[1]);
                return  $this;
            }

            //check if it was passed as an assoc array
            if(is_array($args[0]))
            {
                $keys = array_keys($args[0]);

                for($i = 0; $i < count($keys); $i++)
                {
                    if(is_array($keys[$i]))
                    {
                        $this->operations[] = new Search($keys[$i], $args[0][$keys[$i]]);
                    }
                }
                return  $this;
            }

            //find all filters and add them to the operations stack
            for($i = 0; $i < count($args); $i++)
            {
                if(($args[$i] instanceof Search) || ($args[$i] instanceof  SearchBuilder))
                {
                    $this->operations[] = $args[$i];
                }
            }
            return  $this;
        }

        public function Where(): DBQuery
        {
            $args = func_get_args();

            //check if args where passed in the raw form
            if(count($args) == 2)
            {
                if(is_string($args[0]))
                {
                    $this->operations[] = new Filter([$args[0]=>$args[1]]);
                }
                return  $this;
            }

            //check if it was passed as an assoc array
            if(is_array($args[0]))
            {
                $keys = array_keys($args[0]);

                for($i = 0; $i < count($keys); $i++)
                {
                    $this->operations[] = new Filter([$keys[$i]=>$args[0][$keys[$i]]]);
                }
                return  $this;
            }

            //find all filters and add them to the operations stack
            for($i = 0; $i < count($args); $i++)
            {
                if(($args[$i] instanceof Filter) || ($args[$i] instanceof  FilterBuilder))
                {
                    $this->operations[] = $args[$i];
                }
            }
            return  $this;
        }

        public function OrWhere(): DBQuery
        {
            $args = func_get_args();

            //check if args where passed in the raw form
            if(count($args) == 2)
            {
                if(is_string($args[0]))
                {
                    $this->operations[] = new Filter([$args[0]=>$args[1]], Filter::OR);
                    return  $this;
                }
            }

            //check if it was passed as an assoc array
            if(is_array($args[0]))
            {
                $keys = array_keys($args[0]);

                for($i = 0; $i < count($keys); $i++)
                {
                    $this->operations[] = new Filter([$keys[$i]=>$args[0][$keys[$i]]], Filter::OR);
                }
                return  $this;
            }

            //find all filters and add them to the operations stack
            for($i = 0; $i < count($args); $i++)
            {
                if($args[$i] instanceof Filter)
                {
                    $this->operations[] = Filter::Builder($args[$i], Filter::OR);
                }
                else if($args[$i] instanceof  FilterBuilder)
                {
                    $args[$i]->setOperation(Filter::OR);
                    $this->operations[] = $args[$i];
                }
            }
            return  $this;
        }

        public function Limit($limit): DBQuery
        {
            if(is_int($limit))
            {
                if($this->pagination == null)
                {
                    $this->pagination = new Pagination();
                }
                $this->pagination->Limit = $limit;
            }
            return  $this;
        }

        public function Offset($offset): DBQuery
        {
            if(is_int($offset))
            {
                if($this->pagination == null)
                {
                    $this->pagination = new Pagination();
                }
                $this->pagination->Offset = $offset;
            }
            return  $this;
        }

        public  function Order($order, $direction=null): DBQuery
        {
            if($order instanceof Order)
            {
                $this->order = $order;
            }
            else if(is_string($order))
            {
                if(($direction == Order::ASCENDING) || ($direction == Order::DESCENDING))
                {
                    $this->order = new Order($order, $direction);
                }
                else
                {
                    $this->order = new Order($order);
                }
            }
            return  $this;
        }

        public function Paginate(Pagination $pagination): DBQuery
        {
            $this->pagination = $pagination;
            return  $this;
        }

        public function Distinct($field=null): DBQuery
        {
            $this->distinct_on[] = $field;
            return  $this;
        }

        public function GroupBy($field): DBQuery
        {
            if($field instanceof groupBy)
            {
                $this->group_by = $field->Value;
            }
            else
            {
                $this->group_by = $field;
            }
            return $this;
        }

        public function Not(DBQuery $query): DBQuery
        {
            return  $this;
        }

        public function SQL()
        {
            $args = func_get_args();

            $fields = count($args) > 0 ? implode(", ", $args) : "*";

            $this->query = "SELECT ".((($fields == "*") && (count($this->joins) > 0)) ? $this->buildFieldSelection() : $fields)." FROM ".$this->db->tableName." ";

            $this->executeJoins();

            $this->executeOperations();

            $this->executeLimitsAndOrder();

            return  $this->query;
        }

        public function Join($map_or_tableName, $left_table_id, $right_table_id, $join=DBJoin::Left): DBQuery
        {
            $this->joins[] = [
              "join"=>$join,
              "field_1"=>$left_table_id,
              "field_2"=>$right_table_id,
              "map"=>$map_or_tableName
            ];
            return $this;
        }

        /**
         * @return void
         * @comment will delete rows based on the where and clauses that have been set
         */
        public function Delete(): DBResult
        {
            $ret = new DBResult();

            $this->query = "DELETE FROM ".$this->db->tableName." ";

            $this->executeOperations();


            //die($this->query);

            if(count($this->args) > 0)
            {
                $operation = $this->db->db->prepare($this->query);
                $operation->bind_param($this->argTypes, ...$this->args);

                if($operation->execute())
                {
                    $result = $operation->get_result();

                    //close the connection
                    //$this->db->db->close();
                    return $ret;
                }
                else
                {
                    //throw (new Exception($operation->get_warnings()));
                }
            }
            else
            {
                $res = $this->db->db->query($this->query);

                //close the connection
                //$this->db->db->close();

                return $ret;
            }
        }

        /**
         * @return void
         * @comment will accept an array of data and prep it for inserting to be inserted
         */
        public function Insert(array $data): DBResult
        {
            $ret = new DBResult();

            if(count(array_keys($data)) > 0)
            {
                if(count($this->operations) > 0)
                {
                    return $this->Update($data);
                }
                else
                {
                    $ret = new DBResult();

                    $prep = $this->prepInsert($data);

                    $this->query = "INSERT INTO ".$this->db->tableName." (".$prep['fields'].") VALUES (".$prep["placeholder"]." )";

                    $operation = $this->db->db->prepare($this->query);
                    $operation->bind_param($this->argTypes, ...$this->args);

                    if($operation->execute())
                    {
                        $result = $operation->get_result();
                        $ret->Count = $operation->num_rows();

                        //close the connection
                        //$this->db->db->close();
                        return $ret;
                    }
                    else
                    {
                        //throw (new Exception($operation->get_warnings()));
                    }
                }
            }
            return $ret;
        }

        /**
         * @param array $data
         * @return void
         */
        public function Update(array $data): DBResult
        {
            if(count(array_keys($data)) > 0)
            {
                $ret = new DBResult();

                $prep = $this->prepUpdate($data);

                $this->query = "UPDATE ".$this->db->tableName." SET ".$prep." ";

                $this->executeOperations();

                $operation = $this->db->db->prepare($this->query);
                $operation->bind_param($this->argTypes, ...$this->args);

                if($operation->execute())
                {
                    $result = $operation->get_result();
                    $ret->Count = $operation->num_rows();

                    //close the connection
                    //$this->db->db->close();
                    return $ret;
                }
                else
                {
                    //throw (new Exception($operation->get_warnings()));
                }
            }
        }

        public function Get(): DBResult
        {
            $args = func_get_args();

            $ret = new DBResult();

            $fields = count($args) > 0 ? implode(", ", $args) : "*";

            $this->query = "SELECT ".$this->prepDistinct().((($fields == "*") && (count($this->joins) > 0)) ? $this->buildFieldSelection() : $fields)." FROM ".$this->db->tableName." ";

            $this->executeJoins();

            $this->executeOperations();

            $this->prepGroupBy();


            //prepare count query
            $countQuery = "SELECT COUNT(1) FROM ".array_reverse(explode("FROM", $this->query))[0];
            $countArgs = $this->args;
            $countArgTypes = $this->argTypes;


            $this->executeLimitsAndOrder();

            if($this->db->tableName == "message")
            {
                //die($this->query);
            }
            

            if(count($this->args) > 0)
            {
                $operation = $this->db->db->prepare($this->query);

                if(is_bool($operation))
                {
                    //die($this->query);
                }

                $operation->bind_param($this->argTypes, ...$this->args);

                if($operation->execute())
                {
                    $result = $operation->get_result();
                    $ret->Data = $result->fetch_all(MYSQLI_ASSOC);

                    if($this->pagination != null)
                    {
                        $countOperation = $this->db->db->prepare($countQuery);
                        $countOperation->bind_param($countArgTypes, ...$countArgs);

                        if($countOperation->execute())
                        {
                            $result = $countOperation->get_result();

                            /*if($this->db->tableName == "message")
                            {
                                var_dump($result);
                                die();
                            }*/
                            if($this->group_by != "")
                            {
                                $ret->Count = $result->num_rows;
                            }
                            else
                            {
                                $ret->Count = $result->fetch_array()[0];
                            }

                            $ret->Start = (($this->pagination->Offset + 1) > 0) ? ($this->pagination->Offset + 1) : 1;
                            $ret->Stop = ($this->pagination->Offset + $this->pagination->Limit);
                        }
                        else
                        {
                            $ret->Count = $result->num_rows;
                        }
                    }
                    else
                    {
                        $ret->Count = $result->num_rows;
                    }
                    

                    //close the connection
                    //$this->db->db->close();
                    return $ret;
                }
                else
                {
                    //throw (new Exception($operation->get_warnings()));
                }
            }
            else
            {
                $res = $this->db->db->query($this->query);

                $ret->Data = $res->fetch_all(MYSQLI_ASSOC);

                if($this->pagination != null)
                {
                    $res = $this->db->db->query($countQuery);
                    $ret->Count = $res->fetch_array()[0];

                    $ret->Start = (($this->pagination->Offset + 1) > 0) ? ($this->pagination->Offset + 1) : 1;
                    $ret->Stop = ($this->pagination->Offset + $this->pagination->Limit);
                }
                else
                {
                    $ret->Count = $res->num_rows;
                }

                //close the connection
                //$this->db->db->close();

                return $ret;
            }
        }

        public function Count(): int
        {
            $args = func_get_args();

            $ret = 0;

            $fields = count($args) > 0 ? implode(", ", $args) : "*";

            $this->query = "SELECT ".((count($this->distinct_on) > 0) ? "COUNT(".$this->prepDistinct().")" : "COUNT(1)" )." FROM ".$this->db->tableName." ";

            $this->executeJoins();

            $this->executeOperations();

            $this->prepGroupBy();

            if(count($this->args) > 0)
            {
                $operation = $this->db->db->prepare($this->query);
                $operation->bind_param($this->argTypes, ...$this->args);

                if($operation->execute())
                {
                    $result = $operation->get_result();
                    $ret = $result->fetch_array()[0];

                    //close the connection
                    //$this->db->db->close();
                    return $ret;
                }
                else
                {
                    //throw (new Exception($operation->get_warnings()));
                }
            }
            else
            {
                $res = $this->db->db->query($this->query);
                $ret = $res->fetch_array()[0];

                //close the connection
                //$this->db->db->close();

                return $ret;
            }
        }


        //privately used methods
        private function executeOperations()
        {
            $conditionals = "";

            $createdFilter = "";
            $filter = "";
            $search = "";

            $filterArgs = [];
            $filterTypes = "";

            $searchArgs = [];
            $searchTypes = "";

            $timeSpanArgs = [];
            $timeSpanTypes = "";

            for($i = 0; $i < count($this->operations); $i++)
            {
                if($this->operations[$i] instanceof Timespan)
                {
                    if(trim($createdFilter) == "")
                    {
                        $range = new Range(new Span($this->operations[$i]->Start, $this->operations[$i]->Stop));

                        if(count($this->joins) > 0)
                        {
                            $createdFilter = " (".$this->db->tableName.".created BETWEEN ? And ?) ";
                        }
                        else
                        {
                            $createdFilter = " (created BETWEEN ? AND ?) ";
                        }

                        $timeSpanTypes .= "ii";

                        $timeSpanArgs[] = $range->Start;
                        $timeSpanArgs[] = $range->Stop;
                    }
                }
                else if(($this->operations[$i] instanceof Search) || ($this->operations[$i] instanceof SearchBuilder))
                {
                    $sP = $this->preprocessOp($this->operations[$i])->getQuery();
                    $search .= ((trim($search) != "") ? " AND " : "").$sP->Query;

                    $searchArgs = array_merge($searchArgs, $sP->Values);
                    $searchTypes .= implode("", $sP->Types);
                }
                else if(($this->operations[$i] instanceof Filter) || ($this->operations[$i] instanceof FilterBuilder))
                {
                    $fP = $this->preprocessOp($this->operations[$i])->getQuery();
                    $filter .= ((trim($filter) != "") ? " AND " : "").$fP->Query;

                    $filterArgs = array_merge($filterArgs, $fP->Values);
                    $filterTypes .= implode("", $fP->Types);
                }
            }


            $this->query .= ((trim($filter) != "") ? " WHERE (".$filter.") " : "").
                ((trim($search) != "") ? ((trim($filter) == "") ? " WHERE (" : " AND (").$search.")" : "").
                ((trim($createdFilter) != "") ? (((trim($filter) == "") && (trim($search) == "")) ? " WHERE (" : " AND (").$createdFilter.")" : "");


                $this->args = array_merge($this->args, $filterArgs, $searchArgs, $timeSpanArgs);
                $this->argTypes .= $filterTypes.$searchTypes.$timeSpanTypes;
        }

        private function executeJoins()
        {
            $this->joined_tables = [];

            for($i = 0; $i < count($this->joins); $i++)
            {
                $name = ($this->joins[$i]['map'] instanceof ObjectMap) ? strtolower(array_reverse(explode("\\", $this->joins[$i]['map']->Name))[0]) : $this->joins[$i]['join'];

                $joinName = ($this->joins[$i]['field_1'] != $this->db->tableName) ? $this->joins[$i]['field_1'] : "_".$this->joins[$i]['field_1'];

                while (in_array($joinName, $this->joined_tables))
                {
                    $joinName = "_".$joinName;
                }

                if($this->joins[$i]['map'] instanceof ObjectMap)
                {
                    $this->query .= " ".$this->joins[$i]['join']." JOIN ".$name." ".$joinName." ON ".$this->db->tableName.".".$this->joins[$i]['field_1'].
                        " = ".$joinName.".".$this->joins[$i]['field_2'];

                    $this->joined_tables[] = $joinName;
                }
                else
                {
                    $this->query .= " ".$this->joins[$i]['join']." JOIN ".$this->joins[$i]['map']." ON ".$this->db->tableName.".".$this->joins[$i]['field_1'].
                        " = ".$joinName.".".$this->joins[$i]['field_2'];

                    $this->joined_tables[] = $joinName;
                }
            }
        }

        private function executeLimitsAndOrder()
        {
            $spanArgs = [];
            $spanTypes = "";

            $order = "";
            $pagination = "";
            $pgn = null;

            if($this->pagination != null)
            {
                if(trim($pagination) == "")
                {
                    $pagination = (($this->pagination->Limit != null) && ($this->pagination->Limit != 0) ? " LIMIT ? " : "").
                        (($this->pagination->Offset != null) && ($this->pagination->Offset != 0) ? "OFFSET ? " : "");

                    if(($this->pagination->Limit != null) && ($this->pagination->Limit != 0))
                    {
                        $spanArgs[] = $this->pagination->Limit;
                        $spanTypes .= "i";
                    }
                    if(($this->pagination->Offset != null) && ($this->pagination->Offset != 0))
                    {
                        $spanArgs[] = $this->pagination->Offset;
                        $spanTypes .= "i";
                    }
                }
            }
            if($this->order != null)
            {
                if(trim($order) == "")
                {
                    //preprocess fields name in case of table joins
                    $jPrep = explode(".", $this->order->Field);

                    if((count($this->joins) > 0) && ((count($jPrep) < 2) || (($jPrep[0] != $this->db->tableName) && (!in_array($jPrep[0], $this->joined_tables)))))
                    {
                        $this->order->Field = $this->db->tableName.".".$this->order->Field;
                    }
                    $order = $this->order->getQuery();
                }
            }

            $this->query .= $order.$pagination;

            $this->args = array_merge($this->args, $spanArgs);
            $this->argTypes .= $spanTypes;
        }

        private function buildFieldSelection(): string
        {
            $ret = "";
            $joins = "";

            $this->joined_tables = [];

            for($i = 0; $i < count($this->joins); $i++)
            {
                if($this->joins[$i]['map'] instanceof ObjectMap)
                {
                    $n = (strtolower($this->joins[$i]['field_1']) != $this->db->tableName) ? strtolower($this->joins[$i]['field_1']) : "_".strtolower($this->joins[$i]['field_1']);

                    while (in_array($n, $this->joined_tables))
                    {
                        $n = "_".$n;
                    }
                    $this->joined_tables[] = $n;

                    for($j = 0; $j < count($this->joins[$i]['map']->PublicProperties); $j++)
                    {
                        $joins .= (($joins != "") ? ", " : ""). $n.".".strtolower($this->joins[$i]['map']->PublicProperties[$j]->baseName)." AS ".$n."_".strtolower($this->joins[$i]['map']->PublicProperties[$j]->baseName);
                    }
                    for($j = 0; $j < count($this->joins[$i]['map']->HiddenProperties); $j++)
                    {
                        $joins .= (($joins != "") ? ", " : ""). $n.".".strtolower($this->joins[$i]['map']->HiddenProperties[$j]->baseName)." AS ".$n."_".strtolower($this->joins[$i]['map']->HiddenProperties[$j]->baseName);
                    }
                }
                else
                {
                    return "*";
                }
            }

            if(count($this->db->fields) > 0)
            {
                for($i = 0; $i < count($this->db->fields); $i++)
                {
                    $ret .= (($ret != "") ? ", " : ""). $this->db->tableName.".".strtolower($this->db->fields[$i]);
                }
            }
            $ret .= (($ret != "") ? ", ".$joins : "");

            return  trim(trim($ret != "" ? $ret : "*"), ',')." ";
        }

        private function prepInsert(array $data): array
        {
            $keys = array_keys($data);

            $ret = [
                "fields"=>"",
                "placeholder"=>""
            ];

            for($i = 0; $i < count($data); $i++)
            {
                $ret["fields"] .= ($ret["fields"] != "" ? "," : "") . $keys[$i];
                $ret["placeholder"] .= ($ret["placeholder"] != "" ? ", " : "") . " ?";

                $this->args[] = $data[$keys[$i]];
                $this->argTypes .= (is_string($data[$keys[$i]]) ? "s" : (is_float($data[$keys[$i]]) ? "d" : "i"));
            }
            return $ret;
        }

        private function prepUpdate(array $data): string
        {
            $keys = array_keys($data);

            $ret = "";

            for($i = 0; $i < count($data); $i++)
            {
                $ret .= ($ret != "" ? "," : "") . $keys[$i]."=?";

                $this->args[] = $data[$keys[$i]];
                $this->argTypes .= (is_string($data[$keys[$i]]) ? "s" : (is_float($data[$keys[$i]]) ? "d" : "i"));
            }
            return $ret;
        }

        private function preprocessOp($op)
        {
            if(count($this->joins) > 0)
            {
                if($op instanceof Filter)
                {
                    for($i = 0; $i < count($op->keys); $i++)
                    {
                        $jPrep = explode(".", $op->keys[$i]);

                        if((count($jPrep) < 2) || (($jPrep[0] != $this->db->tableName) && (!in_array($jPrep[0], $this->joined_tables))))
                        {
                            $op->keys[$i] = $this->db->tableName.".".$op->keys[$i];
                        }
                    }
                }
                else if($op instanceof FilterBuilder)
                {
                    for($i = 0; $i < count($op->filter); $i++)
                    {
                        $op->filter[$i] = $this->preprocessOp($op->filter[$i]);
                    }
                }
                else if($op instanceof Search)
                {
                    for($i = 0; $i < count($op->fields); $i++)
                    {
                        $jPrep = explode(".", $op->fields[$i]);

                        if((count($jPrep) < 2) || (($jPrep[0] != $this->db->tableName) && (!in_array($jPrep[0], $this->joined_tables))))
                        {
                            $op->fields[$i] = $this->db->tableName.".".$op->fields[$i];
                        }
                    }
                }
                else if($op instanceof SearchBuilder)
                {
                    for($i = 0; $i < count($op->searches); $i++)
                    {
                        $op->searches[$i] = $this->preprocessOp($op->searches[$i]);
                    }
                }
                return $op;
            }
            else
            {
                return $op;
            }
        }

        private function prepDistinct()
        {
            $ret = "";

            if(count($this->distinct_on) > 0)
            {
                $ret .= " DISTINCT ";
                $fields = "";

                for($i = 0; $i < count($this->distinct_on); $i++)
                {
                    $fields .= ($fields != "" ? ", " : "").$this->distinct_on[$i];
                }
                $ret .= $fields.", ";
            }
            return $ret;
        }

        private function prepGroupBy()
        {
            if($this->group_by != "")
            {
                $this->query .= " GROUP BY ".$this->db->tableName.".".$this->group_by." ";
            }
        }
    }
