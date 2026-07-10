<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Exception\DatabaseException;
    use Wixnit\Interfaces\ISerializable;

    /**
     * A polymorphic reference to one of several possible Transactable types - use this when
     * a property needs to point at "one of several different tables" (e.g. Order::$from
     * being either a Staff or a Customer), which the ORM's automatic join can't express on
     * its own (a join targets exactly one table, chosen from the property's declared type).
     *
     * Stored as a single VARCHAR column: "{typeKey}:{id}". Resolution is lazy - the related
     * row is only fetched when you call resolve(), not automatically when the owning object
     * is loaded, since a real SQL JOIN can't target two different tables based on a value
     * that's only known per-row.
     *
     * Usage: extend this once per relation, declaring which concrete classes it can point to:
     *
     *   class OrderFrom extends Morph
     *   {
     *       protected static function types(): array
     *       {
     *           return ["staff" => Staff::class, "customer" => Customer::class];
     *       }
     *   }
     *
     *   class Order extends Model
     *   {
     *       public OrderFrom $from;
     *   }
     *
     *   $order->from = OrderFrom::To($customer);
     *   $order->save();
     *
     *   $order = Order::Get(new Filter(["id" => $orderId]))->list[0];
     *   $who = $order->from->resolve();          // Staff|Customer|null
     *   if($order->from->is(Customer::class)) { ... }
     */
    abstract class Morph implements ISerializable
    {
        protected string $typeKey = "";
        protected string $id = "";

        private ?Transactable $resolved = null;

        /**
         * the set of classes this relation can point to, keyed by a short string stored
         * alongside the id (e.g. "staff", "customer") - every class referenced here must
         * be a Transactable/Model subclass
         * @return array<string, class-string<Transactable>>
         */
        abstract protected static function types(): array;

        /**
         * build a reference pointing at an already-loaded object, ready to assign to a
         * property and save
         * @param Transactable $object
         * @return static
         * @throws DatabaseException if $object's class isn't listed in types()
         */
        public static function To(Transactable $object): static
        {
            $morph = new static();
            $morph->typeKey = static::KeyFor(get_class($object));
            $morph->id = $object->id;
            $morph->resolved = $object;

            return $morph;
        }

        /**
         * build a MorphMany over $manyClass, finding every row whose $column (a property of
         * this same Morph subclass) points back at $owner. See MorphMany for details.
         *
         *   class Staff extends User
         *   {
         *       public function orders(): MorphMany
         *       {
         *           return OrderFrom::Many(Order::class, "from", $this);
         *       }
         *   }
         *
         * @param string $manyClass the Transactable/Model class to search, e.g. Order::class
         * @param string $column the property name on $manyClass holding the morph reference, e.g. "from"
         * @param Transactable $owner the object every matching row's morph reference should point at
         * @return MorphMany
         */
        public static function Many(string $manyClass, string $column, Transactable $owner): MorphMany
        {
            return new MorphMany($manyClass, static::class, $column, $owner);
        }

        /**
         * find the registered type key for a class, e.g. Staff::class -> "staff"
         * @param string $class
         * @return string
         * @throws DatabaseException if the class isn't registered
         */
        public static function KeyFor(string $class): string
        {
            $found = array_search($class, static::types(), true);

            if($found === false)
            {
                throw new DatabaseException(
                    "'$class' is not a registered type on ".static::class."::types() - ".
                    "add it to the array returned there before assigning it to this relation."
                );
            }
            return $found;
        }

        /**
         * does this reference point at an instance of the given class?
         * @param string $class
         * @return bool
         */
        public function is(string $class): bool
        {
            return ($this->typeKey !== "") && ((static::types()[$this->typeKey] ?? null) === $class);
        }

        /**
         * the id of the referenced row (without fetching it)
         * @return string
         */
        public function getId(): string
        {
            return $this->id;
        }

        /**
         * the fully qualified class name this reference points at (without fetching it), or
         * null if it isn't set / doesn't match a registered type
         * @return string|null
         */
        public function getTypeClass(): ?string
        {
            return static::types()[$this->typeKey] ?? null;
        }

        /**
         * fetch and return the referenced object. The result is cached on this instance, so
         * calling resolve() more than once only queries the database the first time.
         * @return Transactable|null null if this reference is empty, or points at an unregistered type
         */
        public function resolve(): ?Transactable
        {
            if($this->resolved !== null)
            {
                return $this->resolved;
            }

            if(($this->id === "") || ($this->typeKey === ""))
            {
                return null;
            }

            $class = $this->getTypeClass();
            if($class === null)
            {
                return null;
            }

            $results = $class::Get(new Filter(["id" => $this->id]));
            $this->resolved = $results->list[0] ?? null;

            return $this->resolved;
        }


        #region ISerializable

        public function _dbType(): DBFieldType
        {
            return DBFieldType::VARCHAR;
        }

        public function _serialize(): string
        {
            return $this->typeKey.":".$this->id;
        }

        public function _deserialize($data): void
        {
            $parts = explode(":", (string) $data, 2);

            $this->typeKey = $parts[0] ?? "";
            $this->id = $parts[1] ?? "";
            $this->resolved = null;
        }
        #endregion
    }
