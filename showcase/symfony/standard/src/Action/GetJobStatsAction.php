<?php

namespace App\Action;

use App\Action\Response\JobStatsResponseDto;
use App\Repository\JobListingRepository;

/**
 * Action to get job statistics.
 *
 * @api GET /api/jobs/stats
 */
class GetJobStatsAction implements ActionInterface
{
    private ?JobStatsResponseDto $result = null;

    public function __construct(
        private readonly JobListingRepository $repository,
    ) {}

    public function execute(): void
    {
        $stats = $this->repository->getStats();

        $this->result = new JobStatsResponseDto(
            totalJobs: $stats['total_jobs'] ?? 0,
            bySource: $stats['by_source'] ?? [],
            byType: $stats['by_type'] ?? [],
            remoteJobs: $stats['remote_jobs'] ?? 0,
            withSalary: $stats['with_salary'] ?? 0,
            lastFetchedAt: $stats['last_fetched_at'] ?? null,
        );
    }

    /**
     * @return JobStatsResponseDto
     */
    public function result(): JobStatsResponseDto
    {
        if ($this->result === null) {
            throw new \RuntimeException('Action not executed');
        }
        return $this->result;
    }
}
