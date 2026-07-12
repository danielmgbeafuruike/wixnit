<?php

    namespace Wixnit\Data;

    /**
     * Dispatched (via Wixnit\Events\Event) every time a StateMachine successfully performs a
     * transition. Listen for it generically, or check $property/get_class($model) to react
     * only to specific transitions:
     *
     *   Event::Listen(StateTransitioned::class, function(StateTransitioned $event) {
     *       if(($event->model instanceof Order) && ($event->to === OrderStatus::SHIPPED))
     *       {
     *           // ... notify the customer ...
     *       }
     *   });
     */
    class StateTransitioned
    {
        public function __construct(
            public object $model,
            public ?string $property,
            public \UnitEnum $from,
            public \UnitEnum $to,
        )
        {
        }
    }
