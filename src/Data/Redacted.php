<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Excludes a property from JSON output (json_encode($model)) while leaving it a
     * completely normal, directly-accessible property everywhere else - $user->apiKey
     * keeps working exactly as before, it just won't appear in JSON.
     *
     * Distinct from Mappable's older $hidden mechanism, which trades away direct
     * property access entirely in exchange for database storage - #[Redacted] doesn't
     * change how the property is accessed at all, only how it serializes to JSON.
     *
     * Usage:
     *   #[Redacted]
     *   public string $apiKey = "";
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Redacted
    {
    }
