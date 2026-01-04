<?php

namespace App\Action\Request;

/**
 * Request parameters for getting a single job.
 */
readonly class GetJobRequest
{
    public function __construct(
        public int $id,
    ) {}
}
