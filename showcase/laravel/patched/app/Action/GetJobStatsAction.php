<?php

/**
 * GetJobStatsAction - Retrieve job statistics
 *
 * This action has no input shape (parameterless).
 * Returns a DTO with analytical methods.
 */

namespace App\Action;

use App\DTO\JobStats;
use App\Models\JobListing;

class GetJobStatsAction
{
    /**
     * Execute the action
     *
     * @return JobStats Statistics DTO with analytical methods
     */
    public function execute(): JobStats
    {
        $lastFetched = JobListing::max('fetched_at');

        return new JobStats(
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
            lastFetchedAt: $lastFetched ? new \DateTimeImmutable($lastFetched) : null,
        );
    }
}
