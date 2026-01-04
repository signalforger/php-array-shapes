<?php

namespace App\Action\Request;

/**
 * Request parameters for listing jobs.
 */
readonly class ListJobsRequest
{
    public function __construct(
        public ?string $query = null,
        public ?bool $remote = null,
        public ?string $jobType = null,
        public ?string $location = null,
        public ?string $source = null,
        public ?int $minSalary = null,
        public ?int $maxSalary = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            query: $data['q'] ?? null,
            remote: isset($data['remote']) ? filter_var($data['remote'], FILTER_VALIDATE_BOOLEAN) : null,
            jobType: $data['job_type'] ?? null,
            location: $data['location'] ?? null,
            source: $data['source'] ?? null,
            minSalary: isset($data['min_salary']) ? (int) $data['min_salary'] : null,
            maxSalary: isset($data['max_salary']) ? (int) $data['max_salary'] : null,
            page: max(1, (int) ($data['page'] ?? 1)),
            perPage: min(100, max(1, (int) ($data['per_page'] ?? 20))),
        );
    }
}
