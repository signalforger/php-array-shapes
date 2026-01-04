<?php

namespace App\Action;

use App\Action\Request\ListJobsRequest;
use App\Action\Response\JobListResponseDto;
use App\Action\Response\JobResponseDto;
use App\Action\Response\PaginationMetaDto;
use App\Action\Response\SalaryRangeDto;
use App\Entity\JobListing;
use App\Repository\JobListingRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Action to list jobs with filtering and pagination.
 *
 * @api GET /api/jobs
 */
class ListJobsAction implements ActionInterface
{
    private ?JobListResponseDto $result = null;

    public function __construct(
        private readonly JobListingRepository $repository,
        private readonly ListJobsRequest $request,
    ) {}

    public function execute(): void
    {
        $qb = $this->repository->createQueryBuilder('j');

        // Apply filters
        if ($this->request->query !== null) {
            $qb->andWhere('j.title LIKE :q OR j.companyName LIKE :q OR j.description LIKE :q')
               ->setParameter('q', '%' . $this->request->query . '%');
        }

        if ($this->request->remote !== null) {
            $qb->andWhere('j.remote = :remote')
               ->setParameter('remote', $this->request->remote);
        }

        if ($this->request->jobType !== null) {
            $qb->andWhere('j.jobType = :jobType')
               ->setParameter('jobType', $this->request->jobType);
        }

        if ($this->request->location !== null) {
            $qb->andWhere('j.location LIKE :location')
               ->setParameter('location', '%' . $this->request->location . '%');
        }

        if ($this->request->source !== null) {
            $qb->andWhere('j.source = :source')
               ->setParameter('source', $this->request->source);
        }

        if ($this->request->minSalary !== null) {
            $qb->andWhere('j.salaryMin IS NULL OR j.salaryMin >= :minSalary')
               ->setParameter('minSalary', $this->request->minSalary);
        }

        if ($this->request->maxSalary !== null) {
            $qb->andWhere('j.salaryMax IS NULL OR j.salaryMax <= :maxSalary')
               ->setParameter('maxSalary', $this->request->maxSalary);
        }

        // Ordering
        $qb->orderBy('j.postedAt', 'DESC')
           ->addOrderBy('j.createdAt', 'DESC');

        // Pagination
        $qb->setFirstResult(($this->request->page - 1) * $this->request->perPage)
           ->setMaxResults($this->request->perPage);

        $paginator = new Paginator($qb);
        $total = count($paginator);
        $lastPage = (int) ceil($total / $this->request->perPage);

        $jobs = [];
        foreach ($paginator as $job) {
            $jobs[] = $this->formatJob($job);
        }

        $this->result = new JobListResponseDto(
            data: $jobs,
            meta: new PaginationMetaDto(
                currentPage: $this->request->page,
                perPage: $this->request->perPage,
                total: $total,
                lastPage: $lastPage,
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
