<?php

/**
 * JobStats DTO
 *
 * Represents job statistics with analytics methods.
 * DTOs contain behavior (methods) unlike shapes which are pure data structures.
 */

namespace App\DTO;

readonly class JobStats
{
    public function __construct(
        public int $totalJobs,
        public int $remoteJobs,
        public int $jobsWithSalary,
        public array $jobsBySource,
        public array $jobsByType,
        public \DateTimeImmutable $lastUpdated,
    ) {}

    /**
     * Get percentage of remote jobs
     */
    public function remotePercentage(): float
    {
        if ($this->totalJobs === 0) {
            return 0.0;
        }
        return round(($this->remoteJobs / $this->totalJobs) * 100, 1);
    }

    /**
     * Get percentage of jobs with salary info
     */
    public function salaryPercentage(): float
    {
        if ($this->totalJobs === 0) {
            return 0.0;
        }
        return round(($this->jobsWithSalary / $this->totalJobs) * 100, 1);
    }

    /**
     * Get top sources by job count
     */
    public function topSources(int $limit = 5): array
    {
        $sorted = $this->jobsBySource;
        arsort($sorted);
        return array_slice($sorted, 0, $limit, true);
    }

    /**
     * Check if stats are stale
     */
    public function isStale(int $maxAgeMinutes = 60): bool
    {
        $threshold = new \DateTimeImmutable("-{$maxAgeMinutes} minutes");
        return $this->lastUpdated < $threshold;
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'total_jobs' => $this->totalJobs,
            'remote_jobs' => $this->remoteJobs,
            'jobs_with_salary' => $this->jobsWithSalary,
            'jobs_by_source' => $this->jobsBySource,
            'jobs_by_type' => $this->jobsByType,
            'last_updated' => $this->lastUpdated->format('c'),
            'remote_percentage' => $this->remotePercentage(),
            'salary_percentage' => $this->salaryPercentage(),
        ];
    }
}
