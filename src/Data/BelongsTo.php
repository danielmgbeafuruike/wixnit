<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Declares that a property is the foreign-key side of a #[HasMany] relation declared
     * on another model. The property must be a plain string (the same VARCHAR(64) shape
     * every model's own {table}id business id already uses), and is created, and indexed
     * (but never made unique - many children legitimately share one parent), by DBMigrator
     * the same way any other column on the model already is.
     *
     * Usage:
     *
     *   class Order extends Model
     *   {
     *       #[BelongsTo(User::class)]
     *       public string $userid = "";
     *   }
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class BelongsTo
    {
        /**
         * @param string $related fully-qualified class name of the parent Transactable model this column points to
         */
        function __construct(
            public string $related,
        ) {}
    }
