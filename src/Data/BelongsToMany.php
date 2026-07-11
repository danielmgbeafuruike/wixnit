<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Declares a many-to-many relation through a plain junction/pivot table - no extra
     * data on the association itself. The pivot table is synthesized automatically by
     * DBMigrator (two indexed VARCHAR(64) columns plus a composite primary key, so the
     * same pair can never be attached twice) - no pivot model class is needed.
     *
     * Declared on both sides, pointing at the same pivot table with the local/related
     * keys swapped:
     *
     *   class Post extends Model
     *   {
     *       #[BelongsToMany(Tag::class, pivot: 'post_tag', localKey: 'postid', relatedKey: 'tagid')]
     *       public BelongsToManyCollection $tags;
     *   }
     *
     *   class Tag extends Model
     *   {
     *       #[BelongsToMany(Post::class, pivot: 'post_tag', localKey: 'tagid', relatedKey: 'postid')]
     *       public BelongsToManyCollection $posts;
     *   }
     *
     * If the association needs its own data (a quantity, a timestamp, anything beyond
     * "these two rows are related"), use a real pivot model with #[HasMany]/#[BelongsTo]
     * plus #[HasManyThrough] instead - see HasManyThrough.
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class BelongsToMany
    {
        /**
         * @param string $related fully-qualified class name of the other side of the relation
         * @param string $pivot the junction table name, shared by both sides
         * @param string $localKey column on the pivot table pointing back to this model
         * @param string $relatedKey column on the pivot table pointing at the related model
         */
        function __construct(
            public string $related,
            public string $pivot,
            public string $localKey,
            public string $relatedKey,
        ) {}
    }
