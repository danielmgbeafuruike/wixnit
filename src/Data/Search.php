<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\SearchPosition;
    use Wixnit\Utilities\Convert;

    class Search
    {
        public string | array $term = "";
        public array $fields = [];
        public $minchar = null;
        public $charPosition = null;
        public $searchPosition = SearchPosition::ANY;


        function __construct(string $term, array $fields=[], SearchPosition $position=SearchPosition::ANY, $minchar=null)
        {
            $this->term = $term;
            $this->searchPosition = $position;
            $this->minchar = $minchar != null ? Convert::ToInt($minchar) : null;

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

        /**
         * prep and return the search query
         * @param mixed $fs
         * @return DBSQLPrep
         */
        public function getQuery($fs=[]): DBSQLPrep
        {
            $ret = new DBSQLPrep();
            $ret->query = "(";
            $fields = ((count($this->fields) > 0) ? $this->fields : $fs);

            for($i = 0; $i < count($fields); $i++)
            {
                $ret->query .= ((trim($ret->query) != "(") ? " OR " : " ").strtolower($fields[$i])." LIKE ".
                    ((is_array($this->term) && (count($this->term) > 1)) ? " ? ? " : " ? ");

                if(is_array($this->term) && (count($this->term) > 1))
                {
                    $ret->values[] = (((($this->searchPosition == SearchPosition::ANY) || ($this->searchPosition == SearchPosition::END)) ? "%" :
                            (is_int($this->charPosition) ? $this->printUnderscore(Convert::ToInt($this->charPosition)) : "")).
                            $this->term[0].
                            (is_int($this->minchar) ? $this->printUnderscore(Convert::ToInt($this->minchar)) : "").
                            ((($this->searchPosition == SearchPosition::ANY) || ($this->searchPosition == SearchPosition::START)) ? "%" : ""));


                    $ret->values[] = (((($this->searchPosition == SearchPosition::ANY) || ($this->searchPosition == SearchPosition::END)) ? "%" :
                            (is_int($this->charPosition) ? $this->printUnderscore(Convert::ToInt($this->charPosition)) : "")).
                            $this->term[1].
                            (is_int($this->minchar) ? $this->printUnderscore(Convert::ToInt($this->minchar)) : "").
                            ((($this->searchPosition == SearchPosition::ANY) || ($this->searchPosition == SearchPosition::START)) ? "%" : ""));

                    $ret->types[] = is_string($this->term[0]) ? "s" : (is_float($this->term[0]) ? "d" : "i");
                    $ret->types[] = is_string($this->term[1]) ? "s" : (is_float($this->term[1]) ? "d" : "i");
                }
                else
                {
                    $ret->values[] = (((($this->searchPosition == SearchPosition::ANY) || ($this->searchPosition == SearchPosition::END)) ? "%" :
                                (is_int($this->charPosition) ? $this->printUnderscore(Convert::ToInt($this->charPosition)) : "")).
                            $this->term.
                            (is_int($this->minchar) ? $this->printUnderscore(Convert::ToInt($this->minchar)) : "").
                            ((($this->searchPosition == SearchPosition::ANY) || ($this->searchPosition == SearchPosition::START)) ? "%" : ""));

                    $ret->types[] = is_string($this->term) ? "s" : (is_float($this->term) ? "d" : "i");
                }
            }
            $ret->query .= ")";
            return  $ret;
        }

        #region static methods

        /**
         * Create a Search builder with search objects
         * @param array $
         * @return SearchBuilder
         */
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
        #endregion

        #region private methods

        /**
         * Returns a undercores joined together
         * @param mixed $count
         * @return string
         */
        private function printUnderscore($count=0): string
        {
            return str_repeat("_", ($count - 1));
        }
        #endregion
    }