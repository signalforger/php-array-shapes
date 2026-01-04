<?php

/**
 * JobList DTO
 *
 * Represents a paginated list of jobs with collection methods.
 * DTOs contain behavior (methods) unlike shapes which are pure data structures.
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
     * Create from shape-validated array
     */
    public static function fromShape(array $data): self
    {
        return new self(
            jobs: array_map(
                fn(array $job) => Job::fromShape($job),
                $data['data']
            ),
            currentPage: $data['meta']['current_page'],
            perPage: $data['meta']['per_page'],
            total: $data['meta']['total'],
            lastPage: $data['meta']['last_page'],
        );
    }

    /**
     * Get count of jobs in this page
     */
    public function count(): int
    {
        return count($this->jobs);
    }

    /**
     * Check if list is empty
     */
    public function isEmpty(): bool
    {
        return count($this->jobs) === 0;
    }

    /**
     * Check if there are more pages
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Filter to remote jobs only
     */
    public function remoteOnly(): self
    {
        return new self(
            jobs: array_values(array_filter($this->jobs, fn(Job $job) => $job->remote)),
            currentPage: $this->currentPage,
            perPage: $this->perPage,
            total: $this->total,
            lastPage: $this->lastPage,
        );
    }

    /**
     * Filter to jobs with salary info
     */
    public function withSalary(): self
    {
        return new self(
            jobs: array_values(array_filter($this->jobs, fn(Job $job) => $job->salary->isSpecified())),
            currentPage: $this->currentPage,
            perPage: $this->perPage,
            total: $this->total,
            lastPage: $this->lastPage,
        );
    }

    /**
     * Get unique sources in this list
     */
    public function sources(): array
    {
        return array_unique(array_map(fn(Job $job) => $job->source, $this->jobs));
    }

    /**
     * Get average salary midpoint for jobs with salary
     */
    public function averageSalary(): ?int
    {
        $salaries = array_filter(
            array_map(fn(Job $job) => $job->salary->midpoint(), $this->jobs),
            fn(?int $salary) => $salary !== null
        );

        if (count($salaries) === 0) {
            return null;
        }

        return (int) (array_sum($salaries) / count($salaries));
    }

    /**
     * Group jobs by source
     */
    public function groupBySource(): array
    {
        $grouped = [];
        foreach ($this->jobs as $job) {
            $grouped[$job->source][] = $job;
        }
        return $grouped;
    }

    /**
     * Convert to array for JSON serialization
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
