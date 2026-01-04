<?php

namespace App\Action;

use App\Action\Response\JobStatsResponseDto;
use App\Models\JobListing;

/**
 * Action to get job statistics.
 *
 * @api GET /api/jobs/stats
 */
class GetJobStatsAction implements ActionInterface
{
    private ?JobStatsResponseDto $result = null;

    public function execute(): void
    {
        $this->result = new JobStatsResponseDto(
            totalJobs: JobListing::count(),
            bySource: JobListing::selectRaw('source, COUNT(*) as count')
                ->groupBy('source')
                ->pluck('count', 'source')
                ->toArray(),
            byType: JobListing::selectRaw('job_type, COUNT(*) as count')
                ->groupBy('job_type')
                ->pluck('count', 'job_type')
                ->toArray(),
            remoteJobs: JobListing::where('remote', true)->count(),
            withSalary: JobListing::whereNotNull('salary_min')
                ->orWhereNotNull('salary_max')
                ->count(),
            lastFetchedAt: JobListing::max('fetched_at'),
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
