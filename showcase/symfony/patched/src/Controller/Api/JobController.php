<?php

namespace App\Controller\Api;

use App\Entity\JobListing;
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
 *
 * This version uses PHP's native array shapes for type-safe API responses.
 */

// Define shape aliases for API response types
shape SalaryRange = array{
    min: ?int,
    max: ?int,
    currency: ?string,
    formatted: string
};

shape JobResponse = array{
    id: int,
    title: string,
    company_name: string,
    company_logo: ?string,
    location: string,
    remote: bool,
    job_type: string,
    salary: SalaryRange,
    url: string,
    tags: array<string>,
    source: string,
    posted_at: ?string
};

shape JobDetailResponse = array{
    id: int,
    title: string,
    company_name: string,
    company_logo: ?string,
    location: string,
    remote: bool,
    job_type: string,
    salary: SalaryRange,
    description: string,
    url: string,
    tags: array<string>,
    source: string,
    posted_at: ?string,
    fetched_at: ?string
};

shape PaginationMeta = array{
    current_page: int,
    per_page: int,
    total: int,
    last_page: int
};

shape JobListResponse = array{
    data: array<JobResponse>,
    meta: PaginationMeta
};

shape JobStats = array{
    total_jobs: int,
    by_source: array<string, int>,
    by_type: array<string, int>,
    remote_jobs: int,
    with_salary: int,
    last_fetched_at: ?string
};

#[Route('/api', name: 'api_')]
class JobController extends AbstractController
{
    public function __construct(
        private readonly JobListingRepository $jobRepository
    ) {}

    /**
     * List jobs with optional filters.
     * Returns a strongly-typed JobListResponse.
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

        // Build type-safe response using our shape aliases
        return $this->json($this->buildJobListResponse(
            $jobs,
            $page,
            $perPage,
            $total,
            (int) ceil($total / $perPage)
        ));
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
     * Returns a strongly-typed JobStats response.
     */
    #[Route('/jobs/stats', name: 'jobs_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->json(['data' => $this->buildJobStats()]);
    }

    /**
     * Build job statistics with type-checked return.
     */
    private function buildJobStats(): JobStats
    {
        return $this->jobRepository->getStats();
    }

    /**
     * Format a job for list API response.
     * Returns a JobResponse shape - type checked at runtime.
     */
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
            'salary' => $job->getSalaryRange(),
            'url' => $job->getUrl(),
            'tags' => $job->getTags() ?? [],
            'source' => $job->getSource(),
            'posted_at' => $job->getPostedAt()?->format(\DateTimeInterface::ISO8601),
        ];
    }

    /**
     * Format a job with full details.
     * Returns a JobDetailResponse shape - type checked at runtime.
     */
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
            'salary' => $job->getSalaryRange(),
            'description' => $job->getDescription(),
            'url' => $job->getUrl(),
            'tags' => $job->getTags() ?? [],
            'source' => $job->getSource(),
            'posted_at' => $job->getPostedAt()?->format(\DateTimeInterface::ISO8601),
            'fetched_at' => $job->getFetchedAt()?->format(\DateTimeInterface::ISO8601),
        ];
    }

    /**
     * Build paginated job list response with type-checked return.
     */
    private function buildJobListResponse(
        array<JobResponse> $data,
        int $currentPage,
        int $perPage,
        int $total,
        int $lastPage
    ): JobListResponse {
        return [
            'data' => $data,
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }
}
