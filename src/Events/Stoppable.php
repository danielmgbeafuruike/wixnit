<?php

    namespace Wixnit\Events;

    /**
     * Standard implementation of IStoppable - use this on an event class rather than
     * implementing isPropagationStopped() yourself:
     *
     *   class OrderPlaced implements IStoppable
     *   {
     *       use Stoppable;
     *
     *       public function __construct(public Order $order) {}
     *   }
     *
     * Inside a listener, call $event->stopPropagation() to prevent any listeners registered
     * after it from running for this dispatch.
     */
    trait Stoppable
    {
        private bool $propagationStopped = false;

        /**
         * prevent any remaining listeners from running for this dispatch
         * @return void
         */
        public function stopPropagation(): void
        {
            $this->propagationStopped = true;
        }

        /**
         * has a listener already stopped this event's propagation?
         * @return bool
         */
        public function isPropagationStopped(): bool
        {
            return $this->propagationStopped;
        }
    }
