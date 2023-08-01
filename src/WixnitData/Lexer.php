<?php

    namespace wixnit\Data;

    use mysqli;
    use ReflectionClass;

    class Lexer
    {
        public array $Properties = [];
        public array $Hidden = [];
        public array $Types = [];
        public array $Excludes = [];
        public array $Includes = [];

        public static function Reflection($class_or_object) : Lexer
        {
            $ret = new Lexer();
            $reflect = new ReflectionClass($class_or_object);
            $instance = $reflect->newInstance(new mysqli());
            return $instance->internal_object_reflection($ret);
        }
    }