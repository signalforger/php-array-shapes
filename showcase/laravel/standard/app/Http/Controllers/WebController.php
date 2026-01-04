<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use App\Services\JobAggregatorService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web controller for frontend pages.
 */
class WebController extends Controller
{
    /**
     * Home page with job listings.
     */
    public function home(Request $request): View
    {
        $query = JobListing::query();

        // Apply filters
        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%");
            });
        }

        if ($request->input('remote') === '1') {
            $query->where('remote', true);
        }

        if ($jobType = $request->input('job_type')) {
            $query->where('job_type', $jobType);
        }

        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        // Stats
        $stats = [
            'total' => JobListing::count(),
            'remote' => JobListing::where('remote', true)->count(),
            'sources' => JobListing::selectRaw('source, COUNT(*) as count')
                ->groupBy('source')
                ->pluck('count', 'source')
                ->toArray(),
        ];

        $jobs = $query->orderBy('posted_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('home', [
            'jobs' => $jobs,
            'stats' => $stats,
            'filters' => $request->only(['q', 'remote', 'job_type', 'source']),
        ]);
    }

    /**
     * Job detail page.
     */
    public function job(int $id): View
    {
        $job = JobListing::findOrFail($id);

        $relatedJobs = JobListing::where('id', '!=', $job->id)
            ->where(function ($q) use ($job) {
                $q->where('company_name', $job->company_name)
                    ->orWhere('job_type', $job->job_type);
            })
            ->limit(5)
            ->get();

        return view('job', [
            'job' => $job,
            'relatedJobs' => $relatedJobs,
        ]);
    }

    /**
     * API documentation page.
     */
    public function apiDocs(): View
    {
        return view('api-docs');
    }

    /**
     * About page showing the PHP version and features.
     */
    public function about(): View
    {
        $phpVersion = phpversion();
        $hasArrayShapes = function_exists('array_is_shape'); // Check if our feature exists

        return view('about', [
            'phpVersion' => $phpVersion,
            'hasArrayShapes' => $hasArrayShapes,
        ]);
    }
}
