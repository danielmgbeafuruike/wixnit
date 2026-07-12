<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;

    /**
     * A version counter backing optimistic concurrency control. Add one of these as a
     * property on a Transactable/Model and save() automatically detects it: every update
     * only writes if the version in the database still matches what was loaded, and throws
     * ConcurrencyException if it doesn't (meaning someone else saved this row in between) -
     * instead of the classic silent lost-update, where the second write just overwrites the
     * first with no error and no trace.
     *
     *   class Order extends Model
     *   {
     *       public OptimisticLock $version;
     *   }
     *
     *   $order = Order::Get(new Filter(["id" => $id]))->list[0]; // version 3
     *
     *   // ...meanwhile, someone else loads and saves the same row first, moving it to version 4...
     *
     *   $order->status = OrderStatus::SHIPPED;
     *   $order->save(); // WHERE id = ? AND version = 3 matches 0 rows -> ConcurrencyException
     *
     * You never construct or assign this directly in normal use - it starts at 1 automatically
     * on a new object and save() manages incrementing it. There's a Next()/Value() pair for
     * the (uncommon) case where you need to inspect or manipulate it directly.
     */
    class OptimisticLock implements ISerializable
    {
        private int $version;

        public function __construct(int $startingAt = 1)
        {
            $this->version = $startingAt;
        }

        /**
         * the current version number
         * @return int
         */
        public function value(): int
        {
            return $this->version;
        }

        /**
         * get a new OptimisticLock one version ahead of this one - doesn't mutate this instance
         * @return static
         */
        public function next(): static
        {
            return new static($this->version + 1);
        }


        #region ISerializable

        public function _dbType(): DBFieldType
        {
            return DBFieldType::INT;
        }

        public function _serialize(): int
        {
            return $this->version;
        }

        public function _deserialize($data): void
        {
            $this->version = (int) $data;
        }
        #endregion
    }
