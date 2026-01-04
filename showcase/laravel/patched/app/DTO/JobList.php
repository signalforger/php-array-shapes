<?php

/**
 * JobList DTO
 *
 * Paginated list of jobs with metadata.
 * Provides collection-level operations.
 */

namespace App\DTO;

readonly class JobList
{
    /**
     * @param array<Job> $jobs
     */
    public function __construct(
        public array $jobs,
        public int $currentPage,
        public int $perPage,
        public int $total,
        public int $lastPage,
    ) {}

    /**
     * Create from shape-validated JobListData
     */
    public static function fromShape(array $data): self
    {
        $jobs = array_map(
            fn(array $job) => Job::fromShape($job),
            $data['data']
        );

        return new self(
            jobs: $jobs,
            currentPage: $data['meta']['current_page'],
            perPage: $data['meta']['per_page'],
            total: $data['meta']['total'],
            lastPage: $data['meta']['last_page'],
        );
    }

    /**
     * Create from Laravel paginator
     */
    public static function fromPaginator(\Illuminate\Pagination\LengthAwarePaginator $paginator): self
    {
        $jobs = array_map(
            fn($model) => Job::fromModel($model),
            $paginator->items()
        );

        return new self(
            jobs: $jobs,
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            lastPage: $paginator->lastPage(),
        );
    }

    /**
     * Check if list is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->jobs);
    }

    /**
     * Get count of jobs in current page
     */
    public function count(): int
    {
        return count($this->jobs);
    }

    /**
     * Check if there are more pages
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Check if on first page
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    /**
     * Get remote jobs only
     */
    public function remoteOnly(): array
    {
        return array_filter($this->jobs, fn(Job $job) => $job->remote);
    }

    /**
     * Get jobs with salary specified
     */
    public function withSalary(): array
    {
        return array_filter($this->jobs, fn(Job $job) => $job->salary->isSpecified());
    }

    /**
     * Get unique sources in this list
     */
    public function sources(): array
    {
        return array_unique(array_map(fn(Job $job) => $job->source, $this->jobs));
    }

    /**
     * Get average salary midpoint (where available)
     */
    public function averageSalary(): ?float
    {
        $salaries = array_filter(
            array_map(fn(Job $job) => $job->salary->midpoint(), $this->jobs)
        );

        if (empty($salaries)) {
            return null;
        }

        return array_sum($salaries) / count($salaries);
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(fn(Job $job) => $job->toArray(), $this->jobs),
            'meta' => [
                'current_page' => $this->currentPage,
                'per_page' => $this->perPage,
                'total' => $this->total,
                'last_page' => $this->lastPage,
            ],
        ];
    }
}
