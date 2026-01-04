<?php

namespace App\Action;

use App\Action\Request\GetJobRequest;
use App\Action\Response\JobDetailResponse;
use App\Action\Response\SalaryRange;
use App\Entity\JobListing;
use App\Repository\JobListingRepository;

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
        private readonly JobListingRepository $repository,
        private readonly GetJobRequest $request,
    ) {}

    public function execute(): void
    {
        $job = $this->repository->find($this->request->id);

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
            'id' => $job->getId(),
            'title' => $job->getTitle(),
            'company_name' => $job->getCompanyName(),
            'company_logo' => $job->getCompanyLogo(),
            'location' => $job->getLocation(),
            'remote' => $job->isRemote(),
            'job_type' => $job->getJobType(),
            'salary' => $this->formatSalary($job),
            'url' => $job->getUrl(),
            'tags' => $job->getTags() ?? [],
            'source' => $job->getSource(),
            'posted_at' => $job->getPostedAt()?->format(\DateTimeInterface::ISO8601),
            'description' => $job->getDescription() ?? '',
            'fetched_at' => $job->getFetchedAt()?->format(\DateTimeInterface::ISO8601),
        ];
    }

    private function formatSalary(JobListing $job): SalaryRange
    {
        $min = $job->getSalaryMin();
        $max = $job->getSalaryMax();
        $currency = $job->getSalaryCurrency() ?? 'USD';

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
