<?php

    namespace Wixnit\Enum;

    enum LogLevel : string
    {
        case DEBUG = 'debug';
        case INFO = 'info';
        case WARNING = 'warning';
        case ERROR = 'error';
        case CRITICAL = 'critical';

        /**
         * get this level's numeric priority, used to compare levels against a configured minimum
         * (higher = more severe)
         * @return int
         */
        public function priority(): int
        {
            return match($this)
            {
                LogLevel::DEBUG => 0,
                LogLevel::INFO => 1,
                LogLevel::WARNING => 2,
                LogLevel::ERROR => 3,
                LogLevel::CRITICAL => 4,
            };
        }
    }
