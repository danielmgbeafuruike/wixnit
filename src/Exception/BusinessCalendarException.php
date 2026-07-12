<?php

    namespace Wixnit\Exception;

    class BusinessCalendarException extends WixnitException
    {
        public static function NoBusinessDaysFound(): self
        {
            return new self(
                "Searched an unreasonable number of days without finding a business day - ".
                "this calendar likely has no business days configured (check setBusinessDays())."
            );
        }
    }
