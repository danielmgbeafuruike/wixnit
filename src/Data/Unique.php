<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Marks a property as unique - a property-level alternative to listing it in
     * protected array $unique = [...], purely additive: both keep working, forever.
     *
     * Usage:
     *   #[Unique]
     *   public string $email = "";
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Unique
    {
    }
