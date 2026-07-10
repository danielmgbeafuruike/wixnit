<?php

    namespace Wixnit\Data;

    /**
     * Resolved metadata for one #[HasMany] relation on a model, built once per class by
     * RelationMap and reused from then on - see RelationMap for how these get built and
     * validated.
     */
    class RelationDefinition
    {
        public string $propertyName;
        public string $related;
        public string $foreignKey;
        public ?string $localKey;

        /**
         * "array" - eager by default, plain array once loaded.
         * "collection" - lazy by default, HasManyCollection once loaded.
         */
        public string $kind;

        function __construct(string $propertyName, string $related, string $foreignKey, ?string $localKey, string $kind)
        {
            $this->propertyName = $propertyName;
            $this->related = $related;
            $this->foreignKey = $foreignKey;
            $this->localKey = $localKey;
            $this->kind = $kind;
        }
    }
