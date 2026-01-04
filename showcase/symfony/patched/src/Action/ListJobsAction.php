<?php

/**
 * List Jobs Action
 *
 * Pure action class that accepts shape-validated input
 * and returns a DTO. No interface or parent class.
 *
 * Pattern:
 * - Shape validates data at boundary (controller)
 * - Action receives validated shape
 * - Action returns DTO with business logic methods
 *
 * @api GET /api/jobs
 */

namespace App\Action;

use App\DTO\Job;
use App\DTO\JobList;
use App\Entity\JobListing;
use App\Repository\JobListingRepository;
use App\Shapes\ListJobsRequest;
use Doctrine\ORM\Tools\Pagination\Paginator;

class ListJobsAction
{
    public function __construct(
        private readonly JobListingRepository $repository,
    ) {}

    /**
     * Execute the action with shape-validated request data
     *
     * @param ListJobsRequest $request Shape-validated request data
     * @return JobList DTO with collection methods
     */
    public function execute(ListJobsRequest $request): JobList
    {
        $page = $request['page'] ?? 1;
        $perPage = $request['per_page'] ?? 20;

        $qb = $this->repository->createQueryBuilder('j');

        // Apply filters from shape-validated input
        if (isset($request['q'])) {
            $qb->andWhere('j.title LIKE :q OR j.companyName LIKE :q OR j.description LIKE :q')
               ->setParameter('q', '%' . $request['q'] . '%');
        }

        if (isset($request['remote'])) {
            $qb->andWhere('j.remote = :remote')
               ->setParameter('remote', $request['remote']);
        }

        if (isset($request['job_type'])) {
            $qb->andWhere('j.jobType = :jobType')
               ->setParameter('jobType', $request['job_type']);
        }

        if (isset($request['location'])) {
            $qb->andWhere('j.location LIKE :location')
               ->setParameter('location', '%' . $request['location'] . '%');
        }

        if (isset($request['source'])) {
            $qb->andWhere('j.source = :source')
               ->setParameter('source', $request['source']);
        }

        if (isset($request['min_salary'])) {
            $qb->andWhere('j.salaryMin IS NULL OR j.salaryMin >= :minSalary')
               ->setParameter('minSalary', $request['min_salary']);
        }

        if (isset($request['max_salary'])) {
            $qb->andWhere('j.salaryMax IS NULL OR j.salaryMax <= :maxSalary')
               ->setParameter('maxSalary', $request['max_salary']);
        }

        // Ordering
        $qb->orderBy('j.postedAt', 'DESC')
           ->addOrderBy('j.createdAt', 'DESC');

        // Pagination
        $qb->setFirstResult(($page - 1) * $perPage)
           ->setMaxResults($perPage);

        $paginator = new Paginator($qb);
        $total = count($paginator);
        $lastPage = (int) ceil($total / $perPage);

        // Convert entities to DTOs
        $jobs = [];
        foreach ($paginator as $job) {
            $jobs[] = Job::fromEntity($job);
        }

        return new JobList(
            jobs: $jobs,
            currentPage: $page,
            perPage: $perPage,
            total: $total,
            lastPage: $lastPage,
        );
    }
}
