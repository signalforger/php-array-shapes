<?php

namespace App\Action;

use App\Action\Request\GetJobRequest;
use App\Action\Response\JobDetailResponse;
use App\Action\Response\SalaryRange;
use App\Models\JobListing;

/**
 * Action to get a single job by ID.
 *
 * @api GET /api/jobs/{id}
 */
class GetJobAction implements ActionInterface
{
    private ?JobDetailResponse $result = null;
    private bool $notFound = false;

    public function __construct(
        private readonly GetJobRequest $request,
    ) {}

    public function execute(): void
    {
        $job = JobListing::find($this->request->id);

        if ($job === null) {
            $this->notFound = true;
            return;
        }

        $this->result = $this->formatJobDetail($job);
    }

    public function result(): JobDetailResponse
    {
        if ($this->result === null) {
            throw new \RuntimeException('Job not found or action not executed');
        }
        return $this->result;
    }

    public function isNotFound(): bool
    {
        return $this->notFound;
    }

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
            'salary' => $this->formatSalary($job),
            'url' => $job->url,
            'tags' => $job->tags ?? [],
            'source' => $job->source,
            'posted_at' => $job->posted_at?->toIso8601String(),
            'description' => $job->description ?? '',
            'fetched_at' => $job->fetched_at?->toIso8601String(),
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
