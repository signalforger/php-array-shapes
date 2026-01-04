<?php

namespace App\Action;

use App\Action\Response\JobStatsResponse;
use App\Repository\JobListingRepository;

/**
 * Action to get job statistics.
 *
 * @api GET /api/jobs/stats
 */
class GetJobStatsAction implements ActionInterface
{
    private ?JobStatsResponse $result = null;

    public function __construct(
        private readonly JobListingRepository $repository,
    ) {}

    public function execute(): void
    {
        $stats = $this->repository->getStats();

        $this->result = [
            'total_jobs' => $stats['total_jobs'] ?? 0,
            'by_source' => $stats['by_source'] ?? [],
            'by_type' => $stats['by_type'] ?? [],
            'remote_jobs' => $stats['remote_jobs'] ?? 0,
            'with_salary' => $stats['with_salary'] ?? 0,
            'last_fetched_at' => $stats['last_fetched_at'] ?? null,
        ];
    }

    public function result(): JobStatsResponse
    {
        if ($this->result === null) {
            throw new \RuntimeException('Action not executed');
        }
        return $this->result;
    }
}
