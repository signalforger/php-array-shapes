<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API Controller for job listings.
 *
 * Provides endpoints to list, search, and view job listings.
 *
 * This version uses PHP's native array shapes for type-safe API responses.
 */

// Define shape aliases for API response types
shape SalaryRange = array{
    min: ?int,
    max: ?int,
    currency: ?string,
    formatted: string
};

shape JobResponse = array{
    id: int,
    title: string,
    company_name: string,
    company_logo: ?string,
    location: string,
    remote: bool,
    job_type: string,
    salary: SalaryRange,
    url: string,
    tags: array<string>,
    source: string,
    posted_at: ?string
};

shape JobDetailResponse = array{
    id: int,
    title: string,
    company_name: string,
    company_logo: ?string,
    location: string,
    remote: bool,
    job_type: string,
    salary: SalaryRange,
    description: string,
    url: string,
    tags: array<string>,
    source: string,
    posted_at: ?string,
    fetched_at: ?string
};

shape PaginationMeta = array{
    current_page: int,
    per_page: int,
    total: int,
    last_page: int
};

shape JobListResponse = array{
    data: array<JobResponse>,
    meta: PaginationMeta
};

shape JobStats = array{
    total_jobs: int,
    by_source: array<string, int>,
    by_type: array<string, int>,
    remote_jobs: int,
    with_salary: int,
    last_fetched_at: ?string
};

class JobController extends Controller
{
    /**
     * List jobs with optional filters.
     * Returns a strongly-typed JobListResponse.
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobListing::query();

        // Search filter
        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        // Remote filter
        if ($request->has('remote')) {
            $query->where('remote', filter_var($request->input('remote'), FILTER_VALIDATE_BOOLEAN));
        }

        // Job type filter
        if ($jobType = $request->input('job_type')) {
            $query->where('job_type', $jobType);
        }

        // Location filter
        if ($location = $request->input('location')) {
            $query->where('location', 'like', "%{$location}%");
        }

        // Source filter
        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        // Salary filters
        if ($minSalary = $request->input('min_salary')) {
            $query->where(function ($qb) use ($minSalary) {
                $qb->whereNull('salary_min')
                    ->orWhere('salary_min', '>=', (int) $minSalary);
            });
        }

        if ($maxSalary = $request->input('max_salary')) {
            $query->where(function ($qb) use ($maxSalary) {
                $qb->whereNull('salary_max')
                    ->orWhere('salary_max', '<=', (int) $maxSalary);
            });
        }

        // Ordering
        $query->orderBy('posted_at', 'desc')->orderBy('created_at', 'desc');

        // Pagination
        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->paginate($perPage);

        // Build type-safe response using our shape aliases
        return response()->json($this->buildJobListResponse(
            collect($paginator->items())
                ->map(fn($job): JobResponse => $this->formatJob($job))
                ->values()
                ->all(),
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->total(),
            $paginator->lastPage()
        ));
    }

    /**
     * Get a single job listing with full details.
     */
    public function show(int $id): JsonResponse
    {
        $job = JobListing::findOrFail($id);

        return response()->json([
            'data' => $this->formatJobDetail($job),
        ]);
    }

    /**
     * Get job statistics.
     * Returns a strongly-typed JobStats response.
     */
    public function stats(): JsonResponse
    {
        return response()->json(['data' => $this->buildJobStats()]);
    }

    /**
     * Build job statistics with type-checked return.
     */
    private function buildJobStats(): JobStats
    {
        return [
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

    /**
     * Format a job for list API response.
     * Returns a JobResponse shape - type checked at runtime.
     */
    private function formatJob(JobListing $job): JobResponse
    {
        return [
            'id' => $job->id,
            'title' => $job->title,
            'company_name' => $job->company_name,
            'company_logo' => $job->company_logo,
            'location' => $job->location,
            'remote' => $job->remote,
            'job_type' => $job->job_type,
            'salary' => $job->getSalaryRange(),
            'url' => $job->url,
            'tags' => $job->tags ?? [],
            'source' => $job->source,
            'posted_at' => $job->posted_at?->toIso8601String(),
        ];
    }

    /**
     * Format a job with full details.
     * Returns a JobDetailResponse shape - type checked at runtime.
     */
    private function formatJobDetail(JobListing $job): JobDetailResponse
    {
        return [
            'id' => $job->id,
            'title' => $job->title,
            'company_name' => $job->company_name,
            'company_logo' => $job->company_logo,
            'location' => $job->location,
            'remote' => $job->remote,
            'job_type' => $job->job_type,
            'salary' => $job->getSalaryRange(),
            'description' => $job->description,
            'url' => $job->url,
            'tags' => $job->tags ?? [],
            'source' => $job->source,
            'posted_at' => $job->posted_at?->toIso8601String(),
            'fetched_at' => $job->fetched_at?->toIso8601String(),
        ];
    }

    /**
     * Build paginated job list response with type-checked return.
     */
    private function buildJobListResponse(
        array<JobResponse> $data,
        int $currentPage,
        int $perPage,
        int $total,
        int $lastPage
    ): JobListResponse {
        return [
            'data' => $data,
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }
}
