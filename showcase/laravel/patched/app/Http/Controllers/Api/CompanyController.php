<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JobListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * API Controller for companies.
 *
 * Provides endpoints to list and view companies.
 */
class CompanyController extends Controller
{
    /**
     * List companies with job counts.
     *
     * @param Request $request
     *   Query parameters:
     *   - q: string (search query)
     *   - industry: string (industry filter)
     *   - page: int (pagination page)
     *   - per_page: int (items per page)
     *
     * @return JsonResponse
     *   Response structure:
     *   {
     *     "data": [
     *       {
     *         "id": int,
     *         "name": string,
     *         "slug": string,
     *         "logo": string|null,
     *         "industry": string|null,
     *         "job_count": int
     *       }
     *     ],
     *     "meta": {...}
     *   }
     */
    public function index(Request $request): JsonResponse
    {
        // Get unique companies from job listings
        $query = JobListing::selectRaw('company_name, company_logo, COUNT(*) as job_count')
            ->groupBy('company_name', 'company_logo');

        if ($q = $request->input('q')) {
            $query->where('company_name', 'like', "%{$q}%");
        }

        $query->orderByDesc('job_count');

        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->paginate($perPage);

        $companies = collect($paginator->items())->map(function ($item, $index) use ($paginator) {
            return [
                'id' => ($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1,
                'name' => $item->company_name,
                'slug' => Str::slug($item->company_name),
                'logo' => $item->company_logo,
                'industry' => null, // Would need enrichment
                'job_count' => $item->job_count,
            ];
        });











































        return response()->json([
            'data' => $companies,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get company details with their jobs.
     *
     * @param string $slug Company slug (URL-friendly name)
     * @return JsonResponse
     *   Response structure:
     *   {
     *     "data": {
     *       "name": string,
     *       "slug": string,
     *       "logo": string|null,
     *       "job_count": int,
     *       "jobs": [...]
     *     }
     *   }
     */
    public function show(string $slug): JsonResponse
    {
        // Find company by matching slug
        $jobs = JobListing::all()->filter(function ($job) use ($slug) {
            return Str::slug($job->company_name) === $slug;
        });

        if ($jobs->isEmpty()) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $firstJob = $jobs->first();

        return response()->json([
            'data' => [
                'name' => $firstJob->company_name,
                'slug' => $slug,
                'logo' => $firstJob->company_logo,
                'job_count' => $jobs->count(),
                'jobs' => $jobs->map(fn($job) => [
                    'id' => $job->id,
                    'title' => $job->title,
                    'location' => $job->location,
                    'remote' => $job->remote,
                    'job_type' => $job->job_type,
                    'salary' => $job->getSalaryRange(),
                    'posted_at' => $job->posted_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }
}
