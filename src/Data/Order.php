<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\OrderDirection;

    class Order
    {
        function __construct(public string $field="id", public OrderDirection $direction = OrderDirection::ASCENDING){}

        function getQuery(): string
        {
            return " ORDER BY ".$this->field." ".$this->direction->value." ";
        }
    }