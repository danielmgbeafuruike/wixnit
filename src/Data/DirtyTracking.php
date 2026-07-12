<?php

    namespace Wixnit\Data;

    /**
     * Gives a Transactable the ability to answer "what's changed since this was loaded (or
     * since it was last saved)?" - used internally by Transactable itself (every model gets
     * this automatically), and by OptimisticLock to know what a property's value was when
     * this object was loaded, without needing its own separate tracking mechanism.
     *
     *   $order = Order::Get(new Filter(["id" => $id]))->list[0];
     *   $order->status = OrderStatus::SHIPPED;
     *
     *   $order->isDirty();            // true
     *   $order->isDirty("status");    // true
     *   $order->isDirty("total");     // false - untouched
     *   $order->getChanges();         // ["status" => ["old" => OrderStatus::PENDING, "new" => OrderStatus::SHIPPED]]
     *   $order->getOriginal("status"); // OrderStatus::PENDING (well, its serialized form - see getOriginal())
     *
     * The comparison is built entirely on top of toDBObject() - the exact same data that
     * actually gets written to the database - rather than reimplementing serialization
     * logic separately, so it can't drift out of sync with what a save() actually does.
     */
    trait DirtyTracking
    {
        /**
         * a DB-column-shaped snapshot of this object's state, taken right after it was loaded
         * or last saved - what isDirty()/getChanges()/getOriginal() compare against. Empty for
         * a brand new, unsaved object, so every property reads as "changed" until the first save.
         * @var array<string, mixed>
         */
        private array $originalState = [];

        /**
         * capture the current state as the new baseline for dirty tracking. Called
         * automatically after hydration (fromDBResult()) and after a successful save() -
         * only call this yourself if you're deliberately resetting the baseline without a
         * real load/save happening (e.g. in a test).
         * @return void
         */
        protected function captureOriginalState(): void
        {
            $this->originalState = $this->toDBObject();
        }

        /**
         * has this object changed since it was loaded (or since it was last saved)?
         * @param string|null $property a specific PHP property name to check, or every tracked property if omitted
         * @return bool
         */
        public function isDirty(?string $property = null): bool
        {
            if($property !== null)
            {
                return array_key_exists($property, $this->getChanges());
            }
            return count($this->getChanges()) > 0;
        }

        /**
         * the inverse of isDirty() - reads a little more naturally in some call sites
         * @param string|null $property
         * @return bool
         */
        public function isClean(?string $property = null): bool
        {
            return !$this->isDirty($property);
        }

        /**
         * get every property that's changed since load/last save, keyed by PHP property name
         * (not the raw db column name)
         * @return array<string, array{old: mixed, new: mixed}>
         */
        public function getChanges(): array
        {
            $current = $this->toDBObject();
            $columnToProperty = $this->columnToPropertyMap();

            $ret = [];
            $columns = array_keys($current);

            for($i = 0; $i < count($columns); $i++)
            {
                $column = $columns[$i];
                $oldValue = $this->originalState[$column] ?? null;
                $newValue = $current[$column];

                if($oldValue !== $newValue)
                {
                    $propertyName = $columnToProperty[$column] ?? $column;
                    $ret[$propertyName] = ["old" => $oldValue, "new" => $newValue];
                }
            }
            return $ret;
        }

        /**
         * get a property's original (as-loaded / as-last-saved) value, or every original value
         * keyed by PHP property name if omitted. Note this returns the same *serialized* shape
         * toDBObject() produces (e.g. an OptimisticLock property comes back as a plain int, an
         * enum as its backing value) - it's meant for comparison/diffing, not for handing back
         * a live object.
         * @param string|null $property
         * @return mixed
         */
        public function getOriginal(?string $property = null): mixed
        {
            $columnToProperty = $this->columnToPropertyMap();
            $named = [];

            $columns = array_keys($this->originalState);
            for($i = 0; $i < count($columns); $i++)
            {
                $propertyName = $columnToProperty[$columns[$i]] ?? $columns[$i];
                $named[$propertyName] = $this->originalState[$columns[$i]];
            }

            if($property !== null)
            {
                return $named[$property] ?? null;
            }
            return $named;
        }

        /**
         * build a map of db column name (a property's baseName) -> PHP property name, from
         * this object's map, so getChanges()/getOriginal() can be keyed by the friendlier
         * PHP property name instead of the raw column name
         * @return array<string, string>
         */
        private function columnToPropertyMap(): array
        {
            $ret = [];
            $props = $this->getMap()->publicProperties;

            for($i = 0; $i < count($props); $i++)
            {
                $ret[$props[$i]->baseName] = $props[$i]->name;
            }
            return $ret;
        }
    }
