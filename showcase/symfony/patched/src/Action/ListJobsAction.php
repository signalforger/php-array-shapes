<?php

namespace App\Action;

use App\Action\Request\ListJobsRequest;
use App\Action\Response\JobListResponse;
use App\Action\Response\JobResponse;
use App\Action\Response\SalaryRange;
use App\Action\Response\PaginationMeta;
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
    private ?JobListResponse $result = null;

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

        $this->result = $this->buildResponse($jobs, $total, $lastPage);
    }

    public function result(): JobListResponse
    {
        if ($this->result === null) {
            throw new \RuntimeException('Action not executed');
        }
        return $this->result;
    }

    private function buildResponse(array $jobs, int $total, int $lastPage): JobListResponse
    {
        return [
            'data' => $jobs,
            'meta' => [
                'current_page' => $this->request->page,
                'per_page' => $this->request->perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    private function formatJob(JobListing $job): JobResponse
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
