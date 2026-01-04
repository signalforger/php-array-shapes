<?php

namespace App\Action;

use App\Action\Response\JobStatsResponse;
use App\Models\JobListing;

/**
 * Action to get job statistics.
 *
 * @api GET /api/jobs/stats
 */
class GetJobStatsAction implements ActionInterface
{
    private ?JobStatsResponse $result = null;

    public function execute(): void
    {
        $this->result = [
            'total_jobs' => JobListing::count(),
            'by_source' => JobListing::selectRaw('source, COUNT(*) as count')
                ->groupBy('source')
                ->pluck('count', 'source')
                ->toArray(),
            'by_type' => JobListing::selectRaw('job_type, COUNT(*) as count')
                ->groupBy('job_type')
                ->pluck('count', 'job_type')
                ->toArray(),
            'remote_jobs' => JobListing::where('remote', true)->count(),
            'with_salary' => JobListing::whereNotNull('salary_min')
                ->orWhereNotNull('salary_max')
                ->count(),
            'last_fetched_at' => JobListing::max('fetched_at'),
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
