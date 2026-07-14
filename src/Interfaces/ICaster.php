<?php

    namespace Wixnit\Interfaces;

    /**
     * A lightweight, stateless transform applied to a plain scalar property via
     * #[Cast(SomeCaster::class)] - for cases that don't need a full value-object type
     * (Money, JsonDocument, etc.), just a small conversion on the way in and out.
     *
     * Usage:
     *   class LowercaseCast implements Caster
     *   {
     *       public static function castIn(mixed $raw): mixed { return strtolower((string)$raw); }
     *       public static function castOut(mixed $value): mixed { return strtolower((string)$value); }
     *   }
     *
     *   class User extends Model
     *   {
     *       #[Cast(LowercaseCast::class)]
     *       public string $email = "";
     *   }
     */
    interface ICaster
    {
        /**
         * @param mixed $raw the raw value read from the database column
         * @return mixed the value to actually populate the property with
         */
        public static function castIn(mixed $raw): mixed;

        /**
         * @param mixed $value the property's current value
         * @return mixed the value to actually write to the database column
         */
        public static function castOut(mixed $value): mixed;
    }
