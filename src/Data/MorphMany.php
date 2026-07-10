<?php

    namespace Wixnit\Data;

    /**
     * The inverse side of a `Morph` relation: given an owner object, finds every row of
     * some other class whose Morph column points back at it. This is the polymorphic
     * equivalent of a normal one-to-many relation - where a normal one-to-many is "all Orders
     * whose customerId equals this Customer's id", a MorphMany is "all Orders whose `from`
     * column equals *this specific type and id*", letting the same `Order::$from` column be
     * pointed at by more than one kind of owner (`Staff` or `Customer`).
     *
     * You don't construct this directly - build one via the Morph subclass's static Many():
     *
     *   class OrderFrom extends Morph
     *   {
     *       protected static function types(): array
     *       {
     *           return ["staff" => Staff::class, "customer" => Customer::class];
     *       }
     *   }
     *
     *   class Staff extends User
     *   {
     *       public function orders(): MorphMany
     *       {
     *           return OrderFrom::Many(Order::class, "from", $this);
     *       }
     *   }
     *
     *   class Customer extends User
     *   {
     *       public function orders(): MorphMany
     *       {
     *           return OrderFrom::Many(Order::class, "from", $this);
     *       }
     *   }
     *
     *   $staffMember->orders()->get();                                  // every Order this staff member handled
     *   $customer->orders()->get(new Order("created", OrderDirection::DESC));  // newest first
     *   $customer->orders()->count();
     */
    class MorphMany
    {
        private string $manyClass;
        private string $column;
        private string $typeKey;
        private string $ownerId;

        private ?DBCollection $resolved = null;

        /**
         * @param string $manyClass the Transactable/Model class to search, e.g. Order::class
         * @param string $morphClass the Morph subclass used by $manyClass's relation property, e.g. OrderFrom::class
         * @param string $column the property name on $manyClass holding the morph reference, e.g. "from"
         * @param Transactable $owner the object every matching row's morph reference should point at
         */
        public function __construct(string $manyClass, string $morphClass, string $column, Transactable $owner)
        {
            $this->manyClass = $manyClass;
            $this->column = strtolower($column);
            $this->typeKey = $morphClass::KeyFor(get_class($owner));
            $this->ownerId = $owner->id;
        }

        /**
         * fetch the matching rows. Accepts the same query objects `Model::Get()` does
         * (Filter, FilterBuilder, Order, Search, SearchBuilder, Pagination, Timespan, ...) -
         * they're combined with the ownership match, not a replacement for it.
         *
         * The result is cached on this instance, so calling get() more than once (with the
         * same extra arguments, or none) only queries the database on the first call.
         * @param mixed ...$extra
         * @return DBCollection
         */
        public function get(...$extra): DBCollection
        {
            if($this->resolved !== null)
            {
                return $this->resolved;
            }

            $class = $this->manyClass;
            $this->resolved = $class::Get($this->ownershipFilter(), ...$extra);

            return $this->resolved;
        }

        /**
         * count the matching rows, without fetching them. Accepts the same extra query
         * objects as get(). Always queries fresh - unlike get(), this does not cache.
         * @param mixed ...$extra
         * @return int
         */
        public function count(...$extra): int
        {
            $class = $this->manyClass;
            return $class::Count($this->ownershipFilter(), ...$extra);
        }

        /**
         * fetch just the first matching row, or null if there isn't one
         * @param mixed ...$extra
         * @return Transactable|null
         */
        public function first(...$extra): ?Transactable
        {
            return $this->get(...$extra)->list[0] ?? null;
        }

        /**
         * build the Filter that constrains a query to rows pointing back at the owner
         * @return Filter
         */
        private function ownershipFilter(): Filter
        {
            return new Filter([$this->column => $this->typeKey.":".$this->ownerId]);
        }
    }
