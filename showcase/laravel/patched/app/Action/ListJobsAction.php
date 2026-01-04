<?php

/**
 * ListJobsAction - List jobs with filtering and pagination
 *
 * Demonstrates the boundary pattern:
 * - Input: Shape-validated request data (ListJobsRequest shape)
 * - Output: DTO with collection methods (JobList)
 *
 * The shape validates user input at the boundary.
 * The DTO provides business logic for working with results.
 */

namespace App\Action;

use App\DTO\JobList;
use App\Models\JobListing;
use App\Shapes\ListJobsRequest;

class ListJobsAction
{
    /**
     * Execute the action
     *
     * @param ListJobsRequest $request Shape-validated filter/pagination data
     * @return JobList Paginated list of jobs as DTO
     */
    public function execute(ListJobsRequest $request): JobList
    {
        $query = JobListing::query();

        // Apply filters from shape-validated input
        if (isset($request['q'])) {
            $q = $request['q'];
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if (isset($request['remote'])) {
            $query->where('remote', $request['remote']);
        }

        if (isset($request['job_type'])) {
            $query->where('job_type', $request['job_type']);
        }

        if (isset($request['location'])) {
            $query->where('location', 'like', "%{$request['location']}%");
        }

        if (isset($request['source'])) {
            $query->where('source', $request['source']);
        }

        if (isset($request['min_salary'])) {
            $query->where(function ($qb) use ($request) {
                $qb->whereNull('salary_min')
                    ->orWhere('salary_min', '>=', $request['min_salary']);
            });
        }

        if (isset($request['max_salary'])) {
            $query->where(function ($qb) use ($request) {
                $qb->whereNull('salary_max')
                    ->orWhere('salary_max', '<=', $request['max_salary']);
            });
        }

        // Ordering
        $query->orderBy('posted_at', 'desc')->orderBy('created_at', 'desc');

        // Pagination with defaults
        $page = $request['page'] ?? 1;
        $perPage = $request['per_page'] ?? 20;
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Convert to DTO
        return JobList::fromPaginator($paginator);
    }
}
