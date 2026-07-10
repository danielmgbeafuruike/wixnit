<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Declares a one-to-many relation on a property, backed by a real foreign key column
     * on the related (child) table - as opposed to the legacy JSON-id-list behavior an
     * array property gets when this attribute is absent.
     *
     * Usage - two flavors, chosen by the property's own type:
     *
     *   #[HasMany(Order::class, 'userid')]
     *   public array $orders = [];              // eager by default, batched across a page
     *
     *   #[HasMany(Review::class, 'userid')]
     *   public HasManyCollection $reviews;       // lazy by default, for large relations
     *
     * $foreignKey must match a property on the related class carrying #[BelongsTo], both
     * by name and by the database column it maps to.
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class HasMany
    {
        /**
         * @param string $related fully-qualified class name of the related (child) Transactable model
         * @param string $foreignKey column/property on the related model pointing back to this one
         * @param string|null $localKey column on this model being matched against (defaults to this model's own {table}id business id)
         */
        function __construct(
            public string $related,
            public string $foreignKey,
            public ?string $localKey = null,
        ) {}
    }
