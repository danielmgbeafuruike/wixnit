<?php

    namespace Wixnit\Queue;

    /**
     * The minimum contract a job must fulfil to be queueable. In practice, you'll almost
     * always extend the `Job` base class instead of implementing this directly - it gives
     * you sensible defaults for retries, backoff, and failure handling for free.
     */
    interface IJob
    {
        /**
         * do the actual work. Throw an exception to signal failure - the Worker will
         * retry it (with backoff) up to the job's configured maximum attempts, then
         * give up and hand it to failed().
         * @return void
         */
        public function handle(): void;
    }
