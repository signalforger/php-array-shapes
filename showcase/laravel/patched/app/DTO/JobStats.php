<?php

/**
 * JobStats DTO
 *
 * Statistics about job listings.
 * Provides analytical methods for job data.
 */

namespace App\DTO;

readonly class JobStats
{
    /**
     * @param array<string, int> $bySource Jobs count by source
     * @param array<string, int> $byType Jobs count by type
     */
    public function __construct(
        public int $totalJobs,
        public array $bySource,
        public array $byType,
        public int $remoteJobs,
        public int $withSalary,
        public ?\DateTimeImmutable $lastFetchedAt,
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
     * Get percentage of jobs with salary
     */
    public function salaryPercentage(): float
    {
        if ($this->totalJobs === 0) {
            return 0.0;
        }

        return round(($this->withSalary / $this->totalJobs) * 100, 1);
    }

    /**
     * Get top sources by job count
     */
    public function topSources(int $limit = 5): array
    {
        arsort($this->bySource);
        return array_slice($this->bySource, 0, $limit, true);
    }

    /**
     * Get most common job type
     */
    public function mostCommonType(): ?string
    {
        if (empty($this->byType)) {
            return null;
        }

        arsort($this->byType);
        return array_key_first($this->byType);
    }

    /**
     * Check if data is stale (not fetched in given hours)
     */
    public function isStale(int $hours = 24): bool
    {
        if ($this->lastFetchedAt === null) {
            return true;
        }

        $threshold = new \DateTimeImmutable("-{$hours} hours");
        return $this->lastFetchedAt < $threshold;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'total_jobs' => $this->totalJobs,
            'by_source' => $this->bySource,
            'by_type' => $this->byType,
            'remote_jobs' => $this->remoteJobs,
            'remote_percentage' => $this->remotePercentage(),
            'with_salary' => $this->withSalary,
            'salary_percentage' => $this->salaryPercentage(),
            'last_fetched_at' => $this->lastFetchedAt?->format(\DateTimeInterface::ISO8601),
        ];
    }
}
