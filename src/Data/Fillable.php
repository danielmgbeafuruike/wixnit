<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Marks a property as allowed to be set via Model::fill()/$instance->fill() mass
     * assignment. If any property on a class carries #[Fillable], the class is in
     * allow-list mode - only #[Fillable] properties can be mass-assigned, everything
     * else is silently skipped. See Guarded for the inverse (deny-list) strategy - a
     * class should use one or the other, not both.
     *
     * Usage:
     *   #[Fillable]
     *   public string $name = "";
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Fillable
    {
    }
