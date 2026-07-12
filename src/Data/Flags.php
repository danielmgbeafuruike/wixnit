<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Optional configuration for a FlagSet property, giving string names to bit
     * positions so ->has('edit') reads better than ->has(1). Purely a naming
     * convenience - a FlagSet works fine without this attribute, using raw bit
     * positions instead (->has(1), ->add(2)).
     *
     * Usage:
     *   #[Flags('edit', 'publish', 'delete')]
     *   public FlagSet $permissions;
     *
     * 'edit' is bit position 0, 'publish' is bit position 1, and so on - up to 63
     * names, the width of the BIGINT column a FlagSet is stored in.
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Flags
    {
        public array $names;

        function __construct(string ...$names)
        {
            $this->names = $names;
        }
    }
