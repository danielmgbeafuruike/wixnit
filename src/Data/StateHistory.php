<?php

    namespace Wixnit\Data;

    use UnitEnum;
    use Wixnit\Utilities\DateTime;

    /**
     * A single recorded transition in a StateMachine's history. Immutable - constructed once
     * by StateMachine itself when a transition happens, never modified afterward.
     */
    class StateHistory
    {
        public function __construct(
            public readonly ?UnitEnum $from,
            public readonly UnitEnum $to,
            public readonly ?string $reason,
            public readonly array $metadata,
            public readonly DateTime $timestamp,
        )
        {
        }

        /**
         * was this entry recorded by StateMachine::undo() reverting a previous transition,
         * rather than a normal forward transitionTo()?
         * @return bool
         */
        public function isUndo(): bool
        {
            return $this->metadata["undo"] ?? false;
        }

        /**
         * convert to a plain array, suitable for JSON storage
         * @return array
         */
        public function toArray(): array
        {
            return [
                "from" => $this->from?->name,
                "to" => $this->to->name,
                "reason" => $this->reason,
                "metadata" => $this->metadata,
                "timestamp" => $this->timestamp->toEpochSeconds(),
            ];
        }

        /**
         * rebuild a StateHistory from the array produced by toArray()
         * @param array $data
         * @param class-string<UnitEnum> $enumClass the enum the "from"/"to" case names belong to
         * @return StateHistory
         */
        public static function FromArray(array $data, string $enumClass): StateHistory
        {
            $from = (($data["from"] ?? null) !== null) ? constant($enumClass."::".$data["from"]) : null;
            $to = constant($enumClass."::".$data["to"]);

            return new StateHistory(
                $from,
                $to,
                $data["reason"] ?? null,
                $data["metadata"] ?? [],
                new DateTime($data["timestamp"] ?? time())
            );
        }
    }
