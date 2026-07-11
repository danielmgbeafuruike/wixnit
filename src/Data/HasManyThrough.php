<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Declares a many-to-many relation read *through* a real pivot model - use this when
     * the association itself needs its own data (a quantity, a timestamp, anything beyond
     * "these two rows are related"). Unlike #[BelongsToMany], the pivot here is a normal
     * Transactable model with its own #[BelongsTo] columns on both sides, so its schema is
     * already handled by the existing #[BelongsTo] mechanism - this attribute only adds the
     * read-through convenience of fetching the final related objects directly.
     *
     *   class OrderLine extends Model
     *   {
     *       #[BelongsTo(Order::class)]
     *       public string $orderid = "";
     *
     *       #[BelongsTo(Product::class)]
     *       public string $productid = "";
     *
     *       public int $quantity = 1;
     *   }
     *
     *   class Order extends Model
     *   {
     *       #[HasMany(OrderLine::class, 'orderid')]
     *       public array $lines = [];                    // the pivot rows themselves, quantity and all
     *
     *       #[HasManyThrough(Product::class, through: OrderLine::class, throughLocalKey: 'orderid', throughRelatedKey: 'productid')]
     *       public array $products = [];                  // the actual Product objects, read-only
     *   }
     *
     * There's no attach()/detach() here - to add an association with its own data, build
     * and save a pivot model instance directly (`(new OrderLine())->productid = ...`),
     * the same as any other #[HasMany] child.
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class HasManyThrough
    {
        /**
         * @param string $related fully-qualified class name of the final related model
         * @param string $through fully-qualified class name of the pivot model
         * @param string $throughLocalKey property on the pivot model pointing back to this model
         * @param string $throughRelatedKey property on the pivot model pointing at the related model
         */
        function __construct(
            public string $related,
            public string $through,
            public string $throughLocalKey,
            public string $throughRelatedKey,
        ) {}
    }
