<?php

namespace App\Action;

use App\Action\Request\GetJobRequest;
use App\Action\Response\JobDetailResponseDto;
use App\Action\Response\SalaryRangeDto;
use App\Entity\JobListing;
use App\Repository\JobListingRepository;

/**
 * Action to get a single job by ID.
 *
 * @api GET /api/jobs/{id}
 */
class GetJobAction implements ActionInterface
{
    private ?JobDetailResponseDto $result = null;
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

    /**
     * @return JobDetailResponseDto
     */
    public function result(): JobDetailResponseDto
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

    private function formatJobDetail(JobListing $job): JobDetailResponseDto
    {
        return new JobDetailResponseDto(
            id: $job->getId(),
            title: $job->getTitle(),
            companyName: $job->getCompanyName(),
            companyLogo: $job->getCompanyLogo(),
            location: $job->getLocation(),
            remote: $job->isRemote(),
            jobType: $job->getJobType(),
            salary: $this->formatSalary($job),
            url: $job->getUrl(),
            tags: $job->getTags() ?? [],
            source: $job->getSource(),
            postedAt: $job->getPostedAt()?->format(\DateTimeInterface::ISO8601),
            description: $job->getDescription() ?? '',
            fetchedAt: $job->getFetchedAt()?->format(\DateTimeInterface::ISO8601),
        );
    }

    private function formatSalary(JobListing $job): SalaryRangeDto
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

        return new SalaryRangeDto(
            min: $min,
            max: $max,
            currency: $min !== null || $max !== null ? $currency : null,
            formatted: $formatted,
        );
    }
}
