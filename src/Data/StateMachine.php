<?php

    namespace Wixnit\Data;

    use UnitEnum;
    use Wixnit\Enum\DBFieldType;
    use Wixnit\Events\Event;
    use Wixnit\Exception\StateMachineException;
    use Wixnit\Interfaces\ISerializable;
    use Wixnit\Utilities\DateTime;

    /**
     * A state machine that IS a model property, not a helper method that builds one. Extend
     * this once per state property, declaring the transition graph (and, optionally, guards
     * and enter/exit/transition side effects) as fixed, class-level configuration - the same
     * shape Morph uses for its types() map:
     *
     *   class OrderStatusMachine extends StateMachine
     *   {
     *       protected static function enumClass(): string { return OrderStatus::class; }
     *
     *       protected static function transitions(): array
     *       {
     *           return [
     *               OrderStatus::PENDING->name   => [OrderStatus::PAID, OrderStatus::CANCELLED],
     *               OrderStatus::PAID->name      => [OrderStatus::SHIPPED, OrderStatus::REFUNDED],
     *               OrderStatus::SHIPPED->name   => [OrderStatus::DELIVERED],
     *               OrderStatus::DELIVERED->name => [],
     *               OrderStatus::CANCELLED->name => [],
     *               OrderStatus::REFUNDED->name  => [],
     *           ];
     *       }
     *
     *       // optional: extra conditions that must also pass, beyond the transitions() graph
     *       protected static function guards(): array
     *       {
     *           return [
     *               "PAID->SHIPPED" => fn($model, $from, $to) => $model->paymentConfirmed,
     *           ];
     *       }
     *
     *       // optional: side effects run around a transition
     *       protected static function actions(): array
     *       {
     *           return [
     *               "enter:SHIPPED" => function($model, $from, $to) {
     *                   Logger::Info("Order shipped", ["orderId" => $model->id]);
     *               },
     *           ];
     *       }
     *   }
     *
     *   class Order extends Model
     *   {
     *       public OrderStatusMachine $status;
     *   }
     *
     * Usage - note the model is passed in at call time rather than stored on the object. A
     * persisted value type holding a permanent reference back to its owner would be awkward
     * to serialize() (for Queue jobs) and clone (for Mappable's map cache), so it isn't one:
     *
     *   $order->status->current();                              // OrderStatus::PENDING
     *   $order->status->can(OrderStatus::PAID, $order);          // true
     *   $order->status->transitionTo(OrderStatus::PAID, $order); // validates, saves, dispatches StateTransitioned
     *   $order->status->getHistory();                            // StateHistory[]
     *   $order->status->undo($order);                            // reverts the last transition
     *
     * History is persisted alongside the current state, packed as JSON into the same column
     * (see trackHistory() below) - simplest fit for a single self-contained property, but
     * it does mean the column grows with every transition. For an entity that transitions
     * very frequently and needs a long-lived audit trail, a dedicated history table (with its
     * own HasMany relation) is a better fit than this - trackHistory() lets you opt out here
     * and build that separately if so.
     */
    abstract class StateMachine implements ISerializable
    {
        protected UnitEnum $state;

        /**
         * @var StateHistory[]
         */
        protected array $history = [];

        public function __construct(?UnitEnum $initial = null)
        {
            $this->state = $initial ?? static::initial();
        }


        #region configuration - override these per subclass

        /**
         * the enum class this machine's states belong to
         * @return class-string<UnitEnum>
         */
        abstract protected static function enumClass(): string;

        /**
         * the transition graph: a state's case name => the enum cases it's legal to move to
         * from there. A state with no entry (or an empty array) has no legal outgoing
         * transitions.
         * @return array<string, UnitEnum[]>
         */
        abstract protected static function transitions(): array;

        /**
         * the state a freshly-constructed machine starts in, when none is given explicitly.
         * Defaults to the enum's first declared case - override if that's not the right start state.
         * @return UnitEnum
         */
        protected static function initial(): UnitEnum
        {
            $enumClass = static::enumClass();
            return $enumClass::cases()[0];
        }

        /**
         * extra conditions that must also pass for a transition to be legal, on top of (not
         * instead of) the transitions() graph - keyed by "FromName->ToName"
         * @return array<string, callable(object $model, UnitEnum $from, UnitEnum $to): bool>
         */
        protected static function guards(): array
        {
            return [];
        }

        /**
         * side effects to run around a transition, keyed by "enter:StateName",
         * "exit:StateName", or "transition:FromName->ToName". Every callback receives
         * (object $model, ?UnitEnum $from, UnitEnum $to) regardless of which key it's
         * registered under, so it can use whichever it needs.
         * @return array<string, callable>
         */
        protected static function actions(): array
        {
            return [];
        }

        /**
         * should this machine keep a history of every transition? True by default. See the
         * class docblock for the tradeoff - set false to opt out for a lighter-weight column.
         * @return bool
         */
        protected static function trackHistory(): bool
        {
            return true;
        }
        #endregion


        #region querying

        /**
         * the current state
         * @return UnitEnum
         */
        public function current(): UnitEnum
        {
            return $this->state;
        }

        /**
         * is the current state $state?
         * @param UnitEnum $state
         * @return bool
         */
        public function is(UnitEnum $state): bool
        {
            return $this->state === $state;
        }

        /**
         * would moving to $target be a legal transition right now? Checks both the
         * transitions() graph and any matching guard.
         * @param UnitEnum $target
         * @param object $model the model this machine belongs to (guards may need to inspect it)
         * @return bool
         */
        public function can(UnitEnum $target, object $model): bool
        {
            $allowed = static::transitions()[$this->state->name] ?? [];

            if(!in_array($target, $allowed, true))
            {
                return false;
            }

            $guard = static::guards()[$this->state->name."->".$target->name] ?? null;
            return ($guard === null) || (bool) $guard($model, $this->state, $target);
        }

        /**
         * every state it's currently legal to transition to (graph + guards, evaluated now)
         * @param object $model
         * @return UnitEnum[]
         */
        public function allowedTransitions(object $model): array
        {
            $candidates = static::transitions()[$this->state->name] ?? [];
            return array_values(array_filter($candidates, fn($target) => $this->can($target, $model)));
        }

        /**
         * every recorded transition, oldest first
         * @return StateHistory[]
         */
        public function getHistory(): array
        {
            return $this->history;
        }

        /**
         * the most recent recorded transition, or null if none yet
         * @return StateHistory|null
         */
        public function getLastTransition(): ?StateHistory
        {
            return (count($this->history) > 0) ? $this->history[count($this->history) - 1] : null;
        }
        #endregion


        #region mutating

        /**
         * attempt to transition to $target: validates it, runs exit/enter/transition actions,
         * records history, then (by default) saves the model and dispatches StateTransitioned.
         * @param UnitEnum $target
         * @param object $model the model this machine belongs to
         * @param string|null $reason optional human-readable reason, kept in the history entry
         * @param array $metadata optional extra data, kept in the history entry
         * @param bool $save call $model->save() after a successful transition
         * @param bool $dispatchEvent dispatch StateTransitioned (via Wixnit\Events\Event) after a successful transition
         * @return static this machine, for chaining
         * @throws StateMachineException if the transition isn't allowed from the current state
         */
        public function transitionTo(UnitEnum $target, object $model, ?string $reason = null, array $metadata = [], bool $save = true, bool $dispatchEvent = true): static
        {
            if(!$this->can($target, $model))
            {
                throw StateMachineException::IllegalTransition(get_class($model), $this->state, $target);
            }

            $from = $this->state;

            $this->runAction("exit:".$from->name, $model, $from, $target);

            $this->state = $target;
            $this->recordHistory($from, $target, $reason, $metadata);

            $this->runAction("enter:".$target->name, $model, $from, $target);
            $this->runAction("transition:".$from->name."->".$target->name, $model, $from, $target);

            if($save && method_exists($model, "save"))
            {
                $model->save();
            }

            if($dispatchEvent)
            {
                Event::Dispatch(new StateTransitioned($model, $this->propertyNameOn($model), $from, $target));
            }

            return $this;
        }

        /**
         * revert the last recorded transition, moving back to its "from" state. Unlike
         * transitionTo(), this does not check the transitions() graph or any guard - undo is
         * a deliberate reversal, not a new forward move, so the forward rules don't govern it.
         * Records the reversal itself as a new history entry (tagged "undo") rather than
         * erasing what happened, so the history stays a complete, honest record.
         * @param object $model the model this machine belongs to
         * @param string|null $reason optional human-readable reason for the undo
         * @param bool $save call $model->save() after a successful undo
         * @return bool false if there was no history to undo, or the earliest entry (with no "from") was reached
         */
        public function undo(object $model, ?string $reason = null, bool $save = true): bool
        {
            if(count($this->history) === 0)
            {
                return false;
            }

            $last = array_pop($this->history);

            if($last->from === null)
            {
                $this->history[] = $last; // nothing to revert to - put it back and refuse
                return false;
            }

            $current = $this->state;

            $this->runAction("exit:".$current->name, $model, $current, $last->from);

            $this->state = $last->from;
            $this->recordHistory($current, $last->from, $reason ?? "undo", ["undo" => true, "originalReason" => $last->reason]);

            $this->runAction("enter:".$last->from->name, $model, $current, $last->from);

            if($save && method_exists($model, "save"))
            {
                $model->save();
            }
            return true;
        }
        #endregion


        #region private helpers

        private function recordHistory(?UnitEnum $from, UnitEnum $to, ?string $reason, array $metadata): void
        {
            if(!static::trackHistory())
            {
                return;
            }
            $this->history[] = new StateHistory($from, $to, $reason, $metadata, new DateTime(time()));
        }

        private function runAction(string $key, object $model, ?UnitEnum $from, UnitEnum $to): void
        {
            $action = static::actions()[$key] ?? null;

            if($action !== null)
            {
                $action($model, $from, $to);
            }
        }

        /**
         * find the name of the public property on $model this machine instance is assigned
         * to, so StateTransitioned can report which property changed - useful when a model
         * has more than one state machine on it
         * @param object $model
         * @return string|null
         */
        private function propertyNameOn(object $model): ?string
        {
            $found = array_search($this, get_object_vars($model), true);
            return ($found !== false) ? $found : null;
        }
        #endregion


        #region ISerializable

        public function _dbType(): DBFieldType
        {
            return DBFieldType::TEXT;
        }

        public function _serialize(): string
        {
            return json_encode([
                "state" => $this->state->name,
                "history" => array_map(fn(StateHistory $entry) => $entry->toArray(), $this->history),
            ]);
        }

        public function _deserialize($data): void
        {
            $decoded = json_decode((string) $data, true) ?: [];
            $enumClass = static::enumClass();

            try
            {
                $this->state = isset($decoded["state"]) ? constant($enumClass."::".$decoded["state"]) : static::initial();
            }
            catch(\Throwable $exception)
            {
                throw StateMachineException::InvalidPersistedState($enumClass, (string) ($decoded["state"] ?? ""));
            }

            $this->history = [];
            $entries = $decoded["history"] ?? [];

            for($i = 0; $i < count($entries); $i++)
            {
                $this->history[] = StateHistory::FromArray($entries[$i], $enumClass);
            }
        }
        #endregion
    }
