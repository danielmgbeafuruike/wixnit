<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;
    use Wixnit\Exception\PropertyException;

    /**
     * A fixed vocabulary of on/off flags (permissions, feature toggles) stored as a
     * single BIGINT bitmask column - queryable and indexable at the database level,
     * unlike a JSON array of strings on the same row.
     *
     * Usage:
     *   class User extends Model
     *   {
     *       #[Flags('edit', 'publish', 'delete')]
     *       public FlagSet $permissions;
     *   }
     *
     *   $user->permissions->has('edit');
     *   $user->permissions->add('publish');
     *   $user->permissions->remove('edit');
     *
     * The #[Flags(...)] attribute is optional - without it, FlagSet still works using
     * raw bit positions (->has(1), ->add(2)) instead of names. Names are bound once,
     * automatically, by the framework right after construction - see
     * Transactable::bindSpecialProperties().
     *
     * Implements ISerializable, the same interface Date/Time/Duration/Color already
     * use - no new framework plumbing needed for the read/write path itself.
     */
    class FlagSet implements ISerializable, \JsonSerializable
    {
        private int $mask = 0;

        /** @var string[] bit position => flag name, set once via bindNames() */
        private array $names = [];

        function __construct()
        {
        }

        /**
         * Called once by the framework, right after construction, if the property
         * carries #[Flags(...)]. Not intended to be called from application code.
         * @param string[] $names
         * @return void
         */
        public function bindNames(array $names): void
        {
            if(count($names) > 63)
            {
                throw PropertyException::TooManyFlags(count($names));
            }
            $this->names = array_values($names);
        }

        /**
         * @param string|int $flag a name (if #[Flags] is bound) or a raw bit position
         * @return bool
         */
        public function has(string|int $flag): bool
        {
            $bit = $this->resolveBit($flag);
            return ($this->mask & (1 << $bit)) !== 0;
        }

        /**
         * @param string|int $flag
         * @return static
         */
        public function add(string|int $flag): static
        {
            $bit = $this->resolveBit($flag);
            $this->mask |= (1 << $bit);
            return $this;
        }

        /**
         * @param string|int $flag
         * @return static
         */
        public function remove(string|int $flag): static
        {
            $bit = $this->resolveBit($flag);
            $this->mask &= ~(1 << $bit);
            return $this;
        }

        /**
         * @return array active flag names (if #[Flags] is bound), or active raw bit positions otherwise
         */
        public function toArray(): array
        {
            $ret = [];

            for($bit = 0; $bit < 63; $bit++)
            {
                if(($this->mask & (1 << $bit)) !== 0)
                {
                    $ret[] = $this->names[$bit] ?? $bit;
                }
            }
            return $ret;
        }

        public function rawMask(): int
        {
            return $this->mask;
        }

        private function resolveBit(string|int $flag): int
        {
            if(is_int($flag))
            {
                return $flag;
            }

            $bit = array_search($flag, $this->names, true);

            if($bit === false)
            {
                throw PropertyException::UnknownFlag($flag, $this->names);
            }
            return $bit;
        }

        //#region ISerializable

        public function _dbType(): DBFieldType
        {
            return DBFieldType::BIG_INT;
        }

        public function _serialize()
        {
            return $this->mask;
        }

        public function _deserialize($data): void
        {
            $this->mask = (int)$data;
        }

        //#endregion

        /**
         * @return array same as toArray() - a frontend wants flag names, not a bitmask integer
         */
        public function jsonSerialize(): mixed
        {
            return $this->toArray();
        }
    }
