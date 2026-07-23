<?php

    namespace Wixnit\Console;

    use Attribute;

    /**
     * Declares a public property on a Command as a `--flag`/`--name=value` option.
     *
     *   #[Option(shortcut: 'f', description: 'Drop all tables and re-migrate from scratch')]
     *   public bool $fresh = false;
     *
     * The property's own PHP type drives parsing the same way #[Argument] does:
     * `public bool $x` is a flag - present or not, never takes a value; anything else
     * typed (`string`/`int`/`float`) takes a value, via `--name=value` or
     * `--name value`; `public array $tags` is repeatable - `--tag=a --tag=b` collects
     * into `['a', 'b']`. `shortcut` is the single letter after a lone dash (`-f`
     * alongside `--fresh`) - short flags can be combined on the command line (`-fv`
     * means `-f -v`), see CommandMap for the duplicate-shortcut validation this feeds into.
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Option
    {
        function __construct(
            public string $description = "",
            public ?string $shortcut = null,
        ) {}
    }
