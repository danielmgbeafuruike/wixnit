<?php

    namespace Wixnit\Events;

    use Wixnit\Queue\Job;

    /**
     * The Job Event::Dispatch() pushes onto the queue for any listener implementing
     * IShouldQueue. You won't construct this yourself - Dispatch() builds it automatically.
     *
     * Since this is a real Job, it's serialized to be stored (see docs/QUEUE_GUIDE.md) - which
     * means the event instance passed to a queued listener must itself be plain, serializable
     * data, same as any other job's constructor arguments.
     */
    class EventListenerJob extends Job
    {
        public function __construct(private string $listenerClass, private object $event)
        {
        }

        public function handle(): void
        {
            $listener = new $this->listenerClass();
            $listener->handle($this->event);
        }
    }
