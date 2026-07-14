<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Applies a lightweight, stateless transform to a plain scalar property on the way
     * in and out of the database - see Caster for the interface it points at.
     *
     * Usage:
     *   #[Cast(LowercaseCast::class)]
     *   public string $email = "";
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Cast
    {
        /**
         * @param string $caster fully-qualified class name implementing Caster
         */
        function __construct(
            public string $caster,
        ) {}
    }
