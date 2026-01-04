<?php

/**
 * Get Job Stats Action
 *
 * Pure action class that returns a DTO.
 * No interface or parent class.
 *
 * Pattern:
 * - No input shape needed (no parameters)
 * - Action returns DTO with analytics methods
 *
 * @api GET /api/jobs/stats
 */

namespace App\Action;

use App\DTO\JobStats;
use App\Repository\JobListingRepository;

class GetJobStatsAction
{
    public function __construct(
        private readonly JobListingRepository $repository,
    ) {}

    /**
     * Execute the action
     *
     * @return JobStats DTO with analytics methods
     */
    public function execute(): JobStats
    {
        $stats = $this->repository->getStats();

        return new JobStats(
            totalJobs: $stats['total_jobs'] ?? 0,
            remoteJobs: $stats['remote_jobs'] ?? 0,
            jobsWithSalary: $stats['with_salary'] ?? 0,
            jobsBySource: $stats['by_source'] ?? [],
            jobsByType: $stats['by_type'] ?? [],
            lastUpdated: $stats['last_fetched_at']
                ? new \DateTimeImmutable($stats['last_fetched_at'])
                : new \DateTimeImmutable(),
        );
    }
}
