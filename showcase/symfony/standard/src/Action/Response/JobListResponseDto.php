<?php

namespace App\Action\Response;

/**
 * Paginated list of jobs.
 */
readonly class JobListResponseDto implements \JsonSerializable
{
    /**
     * @param array<JobResponseDto> $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaDto $meta,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'data' => array_map(fn(JobResponseDto $job) => $job->jsonSerialize(), $this->data),
            'meta' => $this->meta->jsonSerialize(),
        ];
    }
}
