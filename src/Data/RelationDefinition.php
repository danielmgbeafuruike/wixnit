<?php

    namespace Wixnit\Data;

    /**
     * Resolved metadata for one declared relation on a model, built once per class by
     * RelationMap and reused from then on. Covers all three relation kinds - which
     * fields are populated depends on $relationType:
     *
     *   "hasMany"        - $related, $foreignKey, $localKey
     *   "belongsToMany"  - $related, $pivotTable, $pivotLocalKey, $pivotRelatedKey
     *   "hasManyThrough" - $related, $throughClass, $throughLocalKey, $throughRelatedKey
     *
     * $kind ("array" or "collection") applies to hasMany and belongsToMany - it's the
     * property's own declared type (array = eager by default, a *Collection class =
     * lazy by default). hasManyThrough is always "array" (eager, batched) for now.
     */
    class RelationDefinition
    {
        public string $propertyName;
        public string $related;
        public string $kind;
        public string $relationType;

        //hasMany
        public ?string $foreignKey = null;
        public ?string $localKey = null;

        //belongsToMany
        public ?string $pivotTable = null;
        public ?string $pivotLocalKey = null;
        public ?string $pivotRelatedKey = null;

        //hasManyThrough
        public ?string $throughClass = null;
        public ?string $throughLocalKey = null;
        public ?string $throughRelatedKey = null;
    }
