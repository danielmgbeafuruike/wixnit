<?php

    namespace Wixnit\Console;

    use Attribute;

    /**
     * Declares a public property on a Command as a positional argument - matched to
     * whatever's typed on the command line in declaration order, the same way SQL
     * columns are matched to a model's declared properties elsewhere in the framework.
     *
     *   #[Argument(description: 'Only migrate this model class', default: null)]
     *   public ?string $model = null;
     *
     * The property's own PHP type drives parsing: `public string $name` expects a
     * plain value; `public ?string $model = null` is optional (nullable, with a
     * default). `required` only needs to be set explicitly to force a required
     * argument that also happens to declare a default - CommandMap otherwise derives
     * it for you: an argument with no default and a non-nullable type is required,
     * everything else isn't. See CommandMap for the registration-time validation this
     * feeds into (argument order, mainly).
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Argument
    {
        function __construct(
            public string $description = "",
            public mixed $default = null,
            public bool $required = false,
        ) {}
    }
