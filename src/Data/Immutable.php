<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Marks a property as settable up through the object's first save(), then never
     * again - checked at save() time against the value the property had when this
     * object was originally loaded from the database, since PHP can't intercept a plain
     * property assignment without the same magic-method architecture #[Exclude]'s
     * docblock explains Transactable deliberately avoids.
     *
     * Usage:
     *   #[Immutable]
     *   public string $createdBy = "";
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Immutable
    {
    }
