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
 */
class JobController extends Controller
{
    /**
     * List jobs with optional filters.
     *
     * @param Request $request
     *   Query parameters:
     *   - q: string (search query for title, company, description)
     *   - remote: bool (filter remote jobs)
     *   - job_type: string (full-time, part-time, contract)
     *   - location: string (location filter)
     *   - source: string (remotive, arbeitnow, jsearch)
     *   - min_salary: int (minimum salary)
     *   - max_salary: int (maximum salary)
     *   - page: int (pagination page)
     *   - per_page: int (items per page, max 100)
     *
     * @return JsonResponse
     *   Response structure:
     *   {
     *     "data": [
     *       {
     *         "id": int,
     *         "title": string,
     *         "company_name": string,
     *         "company_logo": string|null,
     *         "location": string,
     *         "remote": bool,
     *         "job_type": string,
     *         "salary": {"min": int|null, "max": int|null, "currency": string|null, "formatted": string},
     *         "url": string,
     *         "tags": string[],
     *         "source": string,
     *         "posted_at": string|null
     *       }
     *     ],
     *     "meta": {
     *       "current_page": int,
     *       "per_page": int,
     *       "total": int,
     *       "last_page": int
     *     }
     *   }
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

        return response()->json([
            'data' => collect($paginator->items())->map(fn($job) => $this->formatJob($job))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single job listing.
     *
     * @param int $id Job ID
     * @return JsonResponse
     *   Response structure:
     *   {
     *     "data": {
     *       "id": int,
     *       "title": string,
     *       "company_name": string,
     *       "company_logo": string|null,
     *       "location": string,
     *       "remote": bool,
     *       "job_type": string,
     *       "salary": {"min": int|null, "max": int|null, "currency": string|null, "formatted": string},
     *       "description": string,
     *       "url": string,
     *       "tags": string[],
     *       "source": string,
     *       "posted_at": string|null,
     *       "fetched_at": string
     *     }
     *   }
     */
    public function show(int $id): JsonResponse
    {
        $job = JobListing::findOrFail($id);

        return response()->json([
            'data' => $this->formatJob($job, true),
        ]);
    }

    /**
     * Get job statistics.
     *
     * @return JsonResponse
     *   Response structure:
     *   {
     *     "data": {
     *       "total_jobs": int,
     *       "by_source": {"remotive": int, "arbeitnow": int, ...},
     *       "by_type": {"full-time": int, "part-time": int, ...},
     *       "remote_jobs": int,
     *       "with_salary": int,
     *       "last_fetched_at": string|null
     *     }
     *   }
     */
    public function stats(): JsonResponse
    {
        $stats = [
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

        return response()->json(['data' => $stats]);
    }

    /**
     * Format a job for API response.
     *
     * @param JobListing $job The job listing
     * @param bool $includeDescription Whether to include full description
     * @return array Formatted job data
     */
    private function formatJob(JobListing $job, bool $includeDescription = false): array
    {
        $data = [
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

        if ($includeDescription) {
            $data['description'] = $job->description;
            $data['fetched_at'] = $job->fetched_at?->toIso8601String();
        }

        return $data;
    }
}
