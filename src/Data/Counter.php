<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;
    use Wixnit\Exception\PropertyException;

    /**
     * A friendlier face on the Increment()/Decrement() static methods that already
     * exist - not new database behavior, just reachable from the property itself
     * instead of a separate static call.
     *
     * Usage:
     *   class Post extends Model { public Counter $views; }
     *
     *   $post->views->increment();   // atomic UPDATE, right now
     *   echo $post->views;            // current value
     *
     * IMPORTANT: increment()/decrement() persist IMMEDIATELY, not on the next save() -
     * a deliberate exception to how every other property on a model works. This has to
     * work this way: the entire reason Increment() exists is to be a single atomic
     * `UPDATE ... SET views = views + 1` that can't lose a concurrent update the way a
     * save() on a whole loaded object could. Deferring it to save() would silently
     * reintroduce the exact race condition Counter exists to prevent. Incrementing a
     * counter and then choosing not to call save() on the rest of the object does not
     * "cancel" the increment - it already happened.
     *
     * Implements ISerializable for the column type + read path (same interface
     * Date/Time/Duration/Color already use), but is also bound to its parent object
     * after construction - like HasManyCollection - since increment()/decrement() need
     * to call back into the parent's own model class.
     */
    class Counter implements ISerializable, \JsonSerializable
    {
        private int $value = 0;

        private ?Transactable $parent = null;
        private ?string $field = null;

        function __construct()
        {
        }

        /**
         * Called once by the framework, right after construction. Not intended to be
         * called from application code.
         * @param Transactable $parent
         * @param string $field
         * @return void
         */
        public function bind(Transactable $parent, string $field): void
        {
            $this->parent = $parent;
            $this->field = $field;
        }

        public function value(): int
        {
            return $this->value;
        }

        /**
         * @param int $by
         * @return static
         */
        public function increment(int $by = 1): static
        {
            return $this->apply($by);
        }

        /**
         * @param int $by
         * @return static
         */
        public function decrement(int $by = 1): static
        {
            return $this->apply(-$by);
        }

        private function apply(int $by): static
        {
            if(($this->parent === null) || ($this->parent->id === ""))
            {
                throw PropertyException::CounterOnUnsavedObject($this->parent ? get_class($this->parent) : "?", $this->field ?? "?");
            }

            $class = get_class($this->parent);
            $idColumn = $this->parent->getIdColumn();

            if($by >= 0)
            {
                $class::Increment($this->field, $by, $this->parent->getConnection(), new Filter([$idColumn => $this->parent->id]));
            }
            else
            {
                $class::Decrement($this->field, -$by, $this->parent->getConnection(), new Filter([$idColumn => $this->parent->id]));
            }
            $this->value += $by;
            return $this;
        }

        public function __toString(): string
        {
            return (string)$this->value;
        }

        //#region ISerializable

        public function _dbType(): DBFieldType
        {
            return DBFieldType::BIG_INT;
        }

        public function _serialize()
        {
            return $this->value;
        }

        public function _deserialize($data): void
        {
            $this->value = (int)$data;
        }

        //#endregion

        public function jsonSerialize(): mixed
        {
            return $this->value;
        }
    }
