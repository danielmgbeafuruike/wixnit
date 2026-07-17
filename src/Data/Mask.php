<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Configures which Masker a Masked property uses. Optional - a Masked property
     * with no #[Mask(...)] attribute uses GenericMasker by default.
     *
     * Usage:
     *   #[Mask(EmailMasker::class)]
     *   public Masked $email;
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Mask
    {
        /**
         * @param string $masker fully-qualified class name implementing Masker
         */
        function __construct(
            public string $masker,
        ) {}
    }
