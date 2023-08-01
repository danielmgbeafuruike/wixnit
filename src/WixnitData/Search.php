<?php

    namespace wixnit\Data;

    use wixnit\Utilities\Convert;

    class Search
    {
        public $Term = null;
        public array $fields = [];
        public $Minchar = null;
        public $Charposition = null;
        public $Searchposition = Search::AnyPosition;

        const StartPosition = 1;
        const EndPosition = 2;
        const AnyPosition = 0;

        function __construct($term, $fields=[], $position=Search::AnyPosition, $minchar=null)
        {
            $this->Term = $term;
            if(($position == Search::StartPosition) || ($position == Search::AnyPosition) || ($position == Search::EndPosition))
            {
                $this->Searchposition = $position;
            }
            $this->Minchar = $minchar != null ? Convert::ToInt($minchar) : null;

            $this->fields = [];
            if(is_array($fields))
            {
                for($i = 0; $i < count($fields); $i++)
                {
                    if(is_string($fields[$i]))
                    {
                        $this->fields[] = strval($fields[$i]);
                    }
                }
            }
        }

        public function getQuery($fs=[]): DBSQLPrep
        {
            $ret = new DBSQLPrep();
            $ret->Query = "(";
            $fields = ((count($this->fields) > 0) ? $this->fields : $fs);

            for($i = 0; $i < count($fields); $i++)
            {
                $ret->Query .= ((trim($ret->Query) != "(") ? " OR " : " ").strtolower($fields[$i])." LIKE ".
                    ((is_array($this->Term) && (count($this->Term) > 1)) ? " ? ? " : " ? ");

                if(is_array($this->Term) && (count($this->Term) > 1))
                {
                    $ret->Values[] = (((($this->Searchposition == Search::AnyPosition) || ($this->Searchposition == Search::EndPosition)) ? "%" :
                            (is_int($this->Charposition) ? $this->printUnderscore(Convert::ToInt($this->Charposition)) : "")).
                            $this->Term[0].
                            (is_int($this->Minchar) ? $this->printUnderscore(Convert::ToInt($this->Minchar)) : "").
                            ((($this->Searchposition == Search::AnyPosition) || ($this->Searchposition == Search::StartPosition)) ? "%" : ""));


                    $ret->Values[] = (((($this->Searchposition == Search::AnyPosition) || ($this->Searchposition == Search::EndPosition)) ? "%" :
                            (is_int($this->Charposition) ? $this->printUnderscore(Convert::ToInt($this->Charposition)) : "")).
                            $this->Term[1].
                            (is_int($this->Minchar) ? $this->printUnderscore(Convert::ToInt($this->Minchar)) : "").
                            ((($this->Searchposition == Search::AnyPosition) || ($this->Searchposition == Search::StartPosition)) ? "%" : ""));

                    $ret->Types[] = is_string($this->Term[0]) ? "s" : (is_float($this->Term[0]) ? "d" : "i");
                    $ret->Types[] = is_string($this->Term[1]) ? "s" : (is_float($this->Term[1]) ? "d" : "i");
                }
                else
                {
                    $ret->Values[] = (((($this->Searchposition == Search::AnyPosition) || ($this->Searchposition == Search::EndPosition)) ? "%" :
                                (is_int($this->Charposition) ? $this->printUnderscore(Convert::ToInt($this->Charposition)) : "")).
                            $this->Term.
                            (is_int($this->Minchar) ? $this->printUnderscore(Convert::ToInt($this->Minchar)) : "").
                            ((($this->Searchposition == Search::AnyPosition) || ($this->Searchposition == Search::StartPosition)) ? "%" : ""));

                    $ret->Types[] = is_string($this->Term) ? "s" : (is_float($this->Term) ? "d" : "i");
                }
            }
            $ret->Query .= ")";
            return  $ret;
        }

        public static function Builder(): SearchBuilder
        {
            $args = func_get_args();
            $builder = new SearchBuilder();

            for($i = 0; $i < count($args); $i++)
            {
                if(($args[$i] instanceof Search) ||($args[$i] instanceof SearchBuilder))
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

        private function printUnderscore($count=0): string
        {
            return str_repeat("_", ($count - 1));
        }
    }