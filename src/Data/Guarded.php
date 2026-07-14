<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Marks a property as blocked from Model::fill()/$instance->fill() mass assignment
     * - the inverse of Fillable. A class using #[Guarded] anywhere is in deny-list mode:
     * everything is mass-assignable except properties marked #[Guarded]. A class should
     * use Fillable or Guarded, not both.
     *
     * Usage:
     *   #[Guarded]
     *   public bool $isAdmin = false;
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Guarded
    {
    }
