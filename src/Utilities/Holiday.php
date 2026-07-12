<?php

    namespace Wixnit\Utilities;

    /**
     * A single concrete holiday occurrence - a date paired with its name. Returned by
     * BusinessCalendar's holiday-query methods (getHoliday(), nextHoliday(), holidaysBetween()).
     * For a recurring holiday rule (e.g. "4th Thursday of November, every year"), each Holiday
     * instance represents one specific year's occurrence of it, not the rule itself.
     */
    class Holiday
    {
        public function __construct(
            public readonly Date $date,
            public readonly string $name,
        )
        {
        }
    }
