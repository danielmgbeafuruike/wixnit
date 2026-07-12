<?php

    namespace Wixnit\Interfaces;

    /**
     * The contract a class-based listener should implement. Not strictly required - Event::Listen()
     * also accepts plain closures - but implementing this makes a listener queueable (via
     * IShouldQueue) and gives it a stable, discoverable shape:
     *
     *   class SendWelcomeEmail implements IListener
     *   {
     *       public function handle(object $event): void
     *       {
     *           // ... send the email ...
     *       }
     *   }
     *
     *   Event::Listen(UserRegistered::class, SendWelcomeEmail::class);
     */
    interface IListener
    {
        /**
         * handle the event
         * @param object $event
         * @return void
         */
        public function handle(object $event): void;
    }
