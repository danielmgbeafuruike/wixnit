<?php

    namespace Wixnit\Interfaces;

    /**
     * Marker interface - implement this on a listener class to tell Event::Dispatch() to
     * push it onto the queue (via Wixnit\Queue) instead of running it immediately, inline,
     * as part of whatever triggered the event:
     *
     *   class SendWelcomeEmail implements IListener, IShouldQueue
     *   {
     *       public function handle(object $event): void
     *       {
     *           // ... send the email ...
     *       }
     *   }
     *
     * Because this runs through the Queue, the same rule applies as any other Job: the event
     * object passed to handle() is serialized to be stored, so keep it (and this listener) to
     * simple, serializable data - see docs/QUEUE_GUIDE.md.
     */
    interface IShouldQueue
    {
    }
