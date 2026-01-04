<?php

namespace App\Controller\Api;

use App\Repository\JobListingRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API Controller for job listings.
 *
 * Provides endpoints to list, search, and view job listings.
 */
#[Route('/api', name: 'api_')]
class JobController extends AbstractController
{
    public function __construct(
        private readonly JobListingRepository $jobRepository
    ) {}

    /**
     * List jobs with optional filters.
     *
     * Query parameters:
     *   - q: string (search query for title, company, description)
     *   - remote: bool (filter remote jobs)
     *   - job_type: string (full-time, part-time, contract)
     *   - location: string (location filter)
     *   - source: string (remotive, arbeitnow, jsearch)
     *   - min_salary: int (minimum salary)
     *   - max_salary: int (maximum salary)
     *   - page: int (pagination page)
     *   - per_page: int (items per page, max 100)
     */
    #[Route('/jobs', name: 'jobs_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $qb = $this->jobRepository->createQueryBuilder('j');

        // Search filter
        if ($q = $request->query->get('q')) {
            $qb->andWhere('j.title LIKE :q OR j.companyName LIKE :q OR j.description LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        // Remote filter
        if ($request->query->has('remote')) {
            $qb->andWhere('j.remote = :remote')
               ->setParameter('remote', filter_var($request->query->get('remote'), FILTER_VALIDATE_BOOLEAN));
        }

        // Job type filter
        if ($jobType = $request->query->get('job_type')) {
            $qb->andWhere('j.jobType = :jobType')
               ->setParameter('jobType', $jobType);
        }

        // Location filter
        if ($location = $request->query->get('location')) {
            $qb->andWhere('j.location LIKE :location')
               ->setParameter('location', '%' . $location . '%');
        }

        // Source filter
        if ($source = $request->query->get('source')) {
            $qb->andWhere('j.source = :source')
               ->setParameter('source', $source);
        }

        // Salary filters
        if ($minSalary = $request->query->get('min_salary')) {
            $qb->andWhere('j.salaryMin IS NULL OR j.salaryMin >= :minSalary')
               ->setParameter('minSalary', (int) $minSalary);
        }

        if ($maxSalary = $request->query->get('max_salary')) {
            $qb->andWhere('j.salaryMax IS NULL OR j.salaryMax <= :maxSalary')
               ->setParameter('maxSalary', (int) $maxSalary);
        }

        // Ordering
        $qb->orderBy('j.postedAt', 'DESC')
           ->addOrderBy('j.createdAt', 'DESC');

        // Pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(max(1, (int) $request->query->get('per_page', 20)), 100);

        $qb->setFirstResult(($page - 1) * $perPage)
           ->setMaxResults($perPage);

        $paginator = new Paginator($qb);
        $total = count($paginator);

        $jobs = [];
        foreach ($paginator as $job) {
            $jobs[] = $this->formatJob($job);
        }

        return $this->json([
            'data' => $jobs,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Get a single job listing.
     */
    #[Route('/jobs/{id}', name: 'jobs_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $job = $this->jobRepository->find($id);

        if (!$job) {
            return $this->json(['error' => 'Job not found'], 404);
        }

        return $this->json([
            'data' => $this->formatJobDetail($job),
        ]);
    }

    /**
     * Get job statistics.
     */
    #[Route('/jobs/stats', name: 'jobs_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->json([
            'data' => $this->jobRepository->getStats(),
        ]);
    }

    /**
     * Format a job for list API response.
     */
    private function formatJob($job): array
    {
        return [
            'id' => $job->getId(),
            'title' => $job->getTitle(),
            'company_name' => $job->getCompanyName(),
            'company_logo' => $job->getCompanyLogo(),
            'location' => $job->getLocation(),
            'remote' => $job->isRemote(),
            'job_type' => $job->getJobType(),
            'salary' => $job->getSalaryRange(),
            'url' => $job->getUrl(),
            'tags' => $job->getTags() ?? [],
            'source' => $job->getSource(),
            'posted_at' => $job->getPostedAt()?->format(\DateTimeInterface::ISO8601),
        ];
    }

    /**
     * Format a job with full details.
     */
    private function formatJobDetail($job): array
    {
        return [
            'id' => $job->getId(),
            'title' => $job->getTitle(),
            'company_name' => $job->getCompanyName(),
            'company_logo' => $job->getCompanyLogo(),
            'location' => $job->getLocation(),
            'remote' => $job->isRemote(),
            'job_type' => $job->getJobType(),
            'salary' => $job->getSalaryRange(),
            'description' => $job->getDescription(),
            'url' => $job->getUrl(),
            'tags' => $job->getTags() ?? [],
            'source' => $job->getSource(),
            'posted_at' => $job->getPostedAt()?->format(\DateTimeInterface::ISO8601),
            'fetched_at' => $job->getFetchedAt()?->format(\DateTimeInterface::ISO8601),
        ];
    }
}
