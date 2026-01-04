<?php

namespace App\Action;

use App\Action\Request\ListJobsRequest;
use App\Action\Response\JobListResponse;
use App\Action\Response\JobResponse;
use App\Action\Response\SalaryRange;
use App\Action\Response\PaginationMeta;
use App\Models\JobListing;

/**
 * Action to list jobs with filtering and pagination.
 *
 * @api GET /api/jobs
 */
class ListJobsAction implements ActionInterface
{
    private ?JobListResponse $result = null;

    public function __construct(
        private readonly ListJobsRequest $request,
    ) {}

    public function execute(): void
    {
        $query = JobListing::query();

        // Apply filters
        if ($this->request->query !== null) {
            $q = $this->request->query;
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($this->request->remote !== null) {
            $query->where('remote', $this->request->remote);
        }

        if ($this->request->jobType !== null) {
            $query->where('job_type', $this->request->jobType);
        }

        if ($this->request->location !== null) {
            $query->where('location', 'like', "%{$this->request->location}%");
        }

        if ($this->request->source !== null) {
            $query->where('source', $this->request->source);
        }

        if ($this->request->minSalary !== null) {
            $query->where(function ($qb) {
                $qb->whereNull('salary_min')
                    ->orWhere('salary_min', '>=', $this->request->minSalary);
            });
        }

        if ($this->request->maxSalary !== null) {
            $query->where(function ($qb) {
                $qb->whereNull('salary_max')
                    ->orWhere('salary_max', '<=', $this->request->maxSalary);
            });
        }

        // Ordering
        $query->orderBy('posted_at', 'desc')->orderBy('created_at', 'desc');

        // Pagination
        $paginator = $query->paginate($this->request->perPage, ['*'], 'page', $this->request->page);

        $jobs = [];
        foreach ($paginator->items() as $job) {
            $jobs[] = $this->formatJob($job);
        }

        $this->result = $this->buildResponse($jobs, $paginator);
    }

    public function result(): JobListResponse
    {
        if ($this->result === null) {
            throw new \RuntimeException('Action not executed');
        }
        return $this->result;
    }

    private function buildResponse(array $jobs, $paginator): JobListResponse
    {
        return [
            'data' => $jobs,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

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
            'salary' => $this->formatSalary($job),
            'url' => $job->url,
            'tags' => $job->tags ?? [],
            'source' => $job->source,
            'posted_at' => $job->posted_at?->toIso8601String(),
        ];
    }

    private function formatSalary(JobListing $job): SalaryRange
    {
        $min = $job->salary_min;
        $max = $job->salary_max;
        $currency = $job->salary_currency ?? 'USD';

        $formatted = 'Not specified';
        if ($min !== null && $max !== null) {
            $formatted = sprintf('%s %s - %s', $currency, number_format($min), number_format($max));
        } elseif ($min !== null) {
            $formatted = sprintf('%s %s+', $currency, number_format($min));
        } elseif ($max !== null) {
            $formatted = sprintf('Up to %s %s', $currency, number_format($max));
        }

        return [
            'min' => $min,
            'max' => $max,
            'currency' => $min !== null || $max !== null ? $currency : null,
            'formatted' => $formatted,
        ];
    }
}
