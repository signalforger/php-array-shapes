<?php

namespace App\Action;

use App\Action\Request\ListJobsRequest;
use App\Action\Response\JobListResponseDto;
use App\Action\Response\JobResponseDto;
use App\Action\Response\PaginationMetaDto;
use App\Action\Response\SalaryRangeDto;
use App\Models\JobListing;

/**
 * Action to list jobs with filtering and pagination.
 *
 * @api GET /api/jobs
 */
class ListJobsAction implements ActionInterface
{
    private ?JobListResponseDto $result = null;

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

        $this->result = new JobListResponseDto(
            data: $jobs,
            meta: new PaginationMetaDto(
                currentPage: $paginator->currentPage(),
                perPage: $paginator->perPage(),
                total: $paginator->total(),
                lastPage: $paginator->lastPage(),
            ),
        );
    }

    /**
     * @return JobListResponseDto
     */
    public function result(): JobListResponseDto
    {
        if ($this->result === null) {
            throw new \RuntimeException('Action not executed');
        }
        return $this->result;
    }

    private function formatJob(JobListing $job): JobResponseDto
    {
        return new JobResponseDto(
            id: $job->id,
            title: $job->title,
            companyName: $job->company_name,
            companyLogo: $job->company_logo,
            location: $job->location,
            remote: $job->remote,
            jobType: $job->job_type,
            salary: $this->formatSalary($job),
            url: $job->url,
            tags: $job->tags ?? [],
            source: $job->source,
            postedAt: $job->posted_at?->toIso8601String(),
        );
    }

    private function formatSalary(JobListing $job): SalaryRangeDto
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

        return new SalaryRangeDto(
            min: $min,
            max: $max,
            currency: $min !== null || $max !== null ? $currency : null,
            formatted: $formatted,
        );
    }
}
