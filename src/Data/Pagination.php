<?php

    namespace Wixnit\Data;

    use Wixnit\Utilities\Range;
    use Wixnit\Utilities\Span;

    class Pagination extends Span
    {
        public $offset = null;
        public $limit = null;

        function __construct($page = 1, $perpage = 25)
        {
            $start = (($page - 1) * $perpage);
            $stop = (($start + $perpage) - 1);

            $this->limit = $perpage;
            $this->offset = $start;

            parent::__construct($start, $stop);
        }

        /**
         * Create a Pagination object from a Span object
         * @param \Wixnit\Utilities\Span $span
         * @return Pagination
         */
        public static function FromSpan(Span $span): Pagination
        {
            $range = new Range($span);

            $pgn = new Pagination();
            $pgn->start = $range->start;
            $pgn->stop = $range->stop;
            $pgn->limit = (($pgn->stop - $pgn->start) + 1);
            $pgn->offset = $pgn->start;

            return $pgn;
        }
    }