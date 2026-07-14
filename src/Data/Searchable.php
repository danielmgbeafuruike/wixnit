<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Narrows Search's default field list. When no explicit fields are passed to
     * Search, it currently defaults to every public property on the model - if any
     * property on a class carries #[Searchable], that default narrows to just the
     * marked properties instead. An explicit field list passed to Search directly still
     * overrides this either way.
     *
     * Usage:
     *   #[Searchable]
     *   public string $name = "";
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Searchable
    {
    }
