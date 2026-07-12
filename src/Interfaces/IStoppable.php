<?php

    namespace Wixnit\Interfaces;

    /**
     * Implement this (or just use the Stoppable trait, which implements it for you) on an
     * event class to let a listener halt the rest of the listener chain - e.g. a validation
     * listener that rejects the event and doesn't want subsequent listeners to act on it.
     */
    interface IStoppable
    {
        /**
         * has a listener already stopped this event's propagation?
         * @return bool
         */
        public function isPropagationStopped(): bool;
    }
