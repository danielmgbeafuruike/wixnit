<?php

    namespace Wixnit\Exception;

    class StateMachineException extends WixnitException
    {
        public static function IllegalTransition(string $modelClass, \UnitEnum $from, \UnitEnum $to): self
        {
            return new self(
                "Illegal transition on $modelClass: '".$from->name."' -> '".$to->name."' is not ".
                "an allowed transition. Add it to the state machine's transitions() map if it should be.",
                ["model" => $modelClass, "from" => $from->name, "to" => $to->name]
            );
        }

        public static function InvalidPersistedState(string $enumClass, string $storedName): self
        {
            return new self(
                "Failed to restore a state machine: '$storedName' is not a case on $enumClass. ".
                "The stored data may be corrupt, or a case was renamed/removed since this row was saved.",
                ["enumClass" => $enumClass, "storedName" => $storedName]
            );
        }
    }
