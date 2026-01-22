<?php

    namespace Wixnit\Routing;

    use Wixnit\Data\Filter;
    use Wixnit\Data\Order;
    use Wixnit\Data\Pagination;
    use Wixnit\Data\Search;
    use Wixnit\Enum\FilterOperation;
    use Wixnit\Enum\OrderDirection;
    use Wixnit\Utilities\Convert;
    use Wixnit\Utilities\Range;
    use Wixnit\Utilities\Span;
    use Wixnit\Utilities\Timespan;

    class RequestMeta
    {
        public Pagination | null $pagination = null;
        public Order | null $order = null;
        public array $filters = [];
        public Search | null $search = null;
        public Timespan | null $timespan = null;
        public Span | null $span = null;
        public Range | null $range = null;


        function __construct(Request $req)
        {
            $this->search = isset($req['search']) ? ((trim($req['search']) == "") ? null : new Search($req['search'])) : null;

            if(isset($req['page']))
            {
                if(isset($req['perpage']))
                {
                    $this->pagination = new Pagination(Convert::ToInt($req['page']), Convert::ToInt($req['perpage']));
                }
                else
                {
                    $this->pagination = new Pagination(Convert::ToInt($req['page']));
                }
            }

            if(isset($req['order_by']))
            {
                $direction = OrderDirection::ASCENDING;

                if(isset($req['order_direction']))
                {
                    $dir = strtolower(trim($req['order_direction']));

                    if(($dir == "desc") || ($dir == "descending") || ($dir == "0"))
                    {
                        $direction = OrderDirection::DESCENDING;
                    }
                }
                $this->order = new Order($req['order_by'], $direction);
            }


            if(isset($req['filters']))
            {
                $fts = explode("~", $req['filters']);

                for($i = 0; $i < count($fts); $i++)
                {
                    if($fts[$i] != "")
                    {
                        $fp = explode(":", $fts[$i]);

                        $this->filters[] = new Filter([$fp[0]=> explode(",", $fp[1])], FilterOperation::OR);
                    }
                }
            }
        }
    }