<?php

    namespace Wixnit\Events;

    use Closure;
    use Throwable;
    use Wixnit\Exception\EventException;
    use Wixnit\Interfaces\IShouldQueue;
    use Wixnit\Queue\Queue;
    use Wixnit\Utilities\Logger;

    /**
     * A lightweight publish/subscribe event system. Decouples "something happened" from
     * "here's everything that should react to it" - instead of a controller manually calling
     * five different services after placing an order, it dispatches one event and each
     * concern (email, inventory, analytics) listens for itself.
     *
     *   class OrderPlaced
     *   {
     *       public function __construct(public Order $order) {}
     *   }
     *
     *   Event::Listen(OrderPlaced::class, function(OrderPlaced $event) {
     *       Logger::Info("Order placed", ["orderId" => $event->order->id]);
     *   });
     *
     *   Event::Listen(OrderPlaced::class, SendOrderConfirmation::class); // a class with handle()
     *
     *   Event::Dispatch(new OrderPlaced($order));
     *
     * Listeners can be closures, an already-built object with a handle() method, or - most
     * usefully - a class name, constructed fresh per dispatch. A class-name listener that
     * implements IShouldQueue runs through Wixnit\Queue in the background instead of inline.
     */
    class Event
    {
        /**
         * @var array<string, array<int, array{listener: mixed, priority: int}>>
         */
        private static array $listeners = [];

        /**
         * register a listener for an event class. Listeners registered against a parent class
         * or interface also run for any event that extends/implements it - register against
         * a shared interface to listen for a whole family of events at once.
         * @param string $eventClass the event's class name (doesn't need to exist yet)
         * @param callable|string|object $listener a closure, an object with handle(), or a class name (constructed fresh per dispatch)
         * @param int $priority higher runs first; listeners with equal priority run in registration order
         * @return void
         */
        public static function Listen(string $eventClass, callable | string | object $listener, int $priority = 0): void
        {
            if(!isset(Event::$listeners[$eventClass]))
            {
                Event::$listeners[$eventClass] = [];
            }
            Event::$listeners[$eventClass][] = ["listener" => $listener, "priority" => $priority];
        }

        /**
         * dispatch an event to every registered listener (its own class, plus any registered
         * against its parent classes or interfaces), highest priority first. If the event
         * implements IStoppable, a listener calling $event->stopPropagation() prevents any
         * remaining listeners from running.
         *
         * @param object $event
         * @param bool $catchExceptions when true, a listener that throws is logged (via Logger::Exception)
         *   and dispatching continues to the next listener, instead of the exception propagating
         *   immediately and stopping the whole dispatch. Off by default - a listener failing is
         *   treated like any other error in this framework, loudly, unless you opt out.
         * @return object the same event instance, for convenience (e.g. read a total a listener computed)
         */
        public static function Dispatch(object $event, bool $catchExceptions = false): object
        {
            $eventClass = get_class($event);
            $entries = Event::resolveListeners($eventClass);

            for($i = 0; $i < count($entries); $i++)
            {
                if(($event instanceof IStoppable) && $event->isPropagationStopped())
                {
                    break;
                }

                if($catchExceptions)
                {
                    try
                    {
                        Event::invoke($entries[$i]["listener"], $event, $eventClass);
                    }
                    catch(Throwable $exception)
                    {
                        Logger::Exception($exception, ["event" => $eventClass]);
                    }
                }
                else
                {
                    Event::invoke($entries[$i]["listener"], $event, $eventClass);
                }
            }
            return $event;
        }

        /**
         * remove listener(s) for an event class
         * @param string $eventClass
         * @param callable|string|object|null $listener remove only this specific listener; removes every listener for the event if omitted
         * @return void
         */
        public static function Forget(string $eventClass, callable | string | object | null $listener = null): void
        {
            if($listener === null)
            {
                unset(Event::$listeners[$eventClass]);
                return;
            }

            if(!isset(Event::$listeners[$eventClass]))
            {
                return;
            }

            $remaining = [];
            $entries = Event::$listeners[$eventClass];

            for($i = 0; $i < count($entries); $i++)
            {
                if($entries[$i]["listener"] !== $listener)
                {
                    $remaining[] = $entries[$i];
                }
            }
            Event::$listeners[$eventClass] = $remaining;
        }

        /**
         * does an event class have at least one registered listener (including via a parent
         * class or interface)?
         * @param string $eventClass
         * @return bool
         */
        public static function HasListeners(string $eventClass): bool
        {
            return count(Event::resolveListeners($eventClass)) > 0;
        }

        /**
         * get the raw listeners registered directly against an event class (does not include
         * listeners inherited via a parent class/interface - mainly useful for introspection/tests)
         * @param string $eventClass
         * @return array
         */
        public static function Listeners(string $eventClass): array
        {
            $entries = Event::$listeners[$eventClass] ?? [];
            return array_map(fn($entry) => $entry["listener"], $entries);
        }

        /**
         * remove every registered listener for every event. Mainly useful between tests.
         * @return void
         */
        public static function Flush(): void
        {
            Event::$listeners = [];
        }


        #region private helpers

        /**
         * gather every listener that should run for an event class: listeners registered
         * against the class itself, any of its parent classes, and any interface it implements -
         * sorted by priority (highest first), preserving registration order for ties.
         * @param string $eventClass
         * @return array<int, array{listener: mixed, priority: int}>
         */
        private static function resolveListeners(string $eventClass): array
        {
            $keys = [$eventClass];

            if(class_exists($eventClass))
            {
                $keys = array_merge($keys, array_values(class_parents($eventClass) ?: []), array_values(class_implements($eventClass) ?: []));
            }

            $all = [];
            for($i = 0; $i < count($keys); $i++)
            {
                if(isset(Event::$listeners[$keys[$i]]))
                {
                    $all = array_merge($all, Event::$listeners[$keys[$i]]);
                }
            }

            usort($all, fn($a, $b) => $b["priority"] <=> $a["priority"]);
            return $all;
        }

        /**
         * run a single listener against an event, dispatching class-name listeners that
         * implement IShouldQueue onto the queue instead of running them inline
         * @param mixed $listener
         * @param object $event
         * @param string $eventClass
         * @return void
         * @throws EventException if the listener isn't callable and isn't a valid handle()-having class/object
         */
        private static function invoke(mixed $listener, object $event, string $eventClass): void
        {
            if($listener instanceof Closure)
            {
                $listener($event);
                return;
            }

            if(is_string($listener))
            {
                if(!class_exists($listener) || !method_exists($listener, "handle"))
                {
                    throw EventException::InvalidListener($eventClass, $listener);
                }

                if(is_subclass_of($listener, IShouldQueue::class))
                {
                    Queue::Push(new EventListenerJob($listener, $event));
                    return;
                }

                (new $listener())->handle($event);
                return;
            }

            if(is_object($listener))
            {
                if($listener instanceof IShouldQueue)
                {
                    Queue::Push(new EventListenerJob(get_class($listener), $event));
                    return;
                }

                if(method_exists($listener, "handle"))
                {
                    $listener->handle($event);
                    return;
                }

                if(is_callable($listener))
                {
                    $listener($event);
                    return;
                }
            }

            if(is_callable($listener))
            {
                $listener($event);
                return;
            }

            throw EventException::InvalidListener($eventClass, $listener);
        }
        #endregion
    }
