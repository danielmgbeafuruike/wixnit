<?php

    namespace Wixnit\Data;

    use Attribute;

    /**
     * Excludes a property from becoming a database column - a property-level
     * alternative to listing it in protected array $excludes = [...], purely additive.
     * Unlike the property's former name (#[Computed]) might have implied, this does not
     * recalculate a value automatically - PHP has no native property getters, and
     * Transactable's "public property = column, direct access" design deliberately
     * never intercepts plain property access with magic methods. Give the property a
     * value yourself (e.g. in onInitialized()) if it needs one; #[Exclude] only keeps
     * it out of the schema.
     *
     * Usage:
     *   #[Exclude]
     *   public string $fullName = "";
     *
     *   protected function onInitialized()
     *   {
     *       $this->fullName = $this->firstName . " " . $this->lastName;
     *   }
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Exclude
    {
    }
