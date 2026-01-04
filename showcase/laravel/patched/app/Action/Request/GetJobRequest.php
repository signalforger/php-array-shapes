<?php

namespace App\Action\Request;

readonly class GetJobRequest
{
    public function __construct(
        public int $id,
    ) {}
}
