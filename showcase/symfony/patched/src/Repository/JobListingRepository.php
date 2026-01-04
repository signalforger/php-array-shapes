<?php

namespace App\Repository;

use App\Entity\JobListing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobListing>
 */
class JobListingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobListing::class);
    }

    public function save(JobListing $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByExternalIdAndSource(string $externalId, string $source): ?JobListing
    {
        return $this->findOneBy(['externalId' => $externalId, 'source' => $source]);
    }

    /**
     * Get statistics about jobs.
     */
    public function getStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $totalJobs = $this->count([]);
        $remoteJobs = $this->count(['remote' => true]);

        // By source
        $bySource = $conn->fetchAllKeyValue(
            'SELECT source, COUNT(*) as count FROM job_listings GROUP BY source'
        );

        // By type
        $byType = $conn->fetchAllKeyValue(
            'SELECT job_type, COUNT(*) as count FROM job_listings GROUP BY job_type'
        );

        // With salary
        $withSalary = $conn->fetchOne(
            'SELECT COUNT(*) FROM job_listings WHERE salary_min IS NOT NULL OR salary_max IS NOT NULL'
        );

        // Last fetched
        $lastFetched = $conn->fetchOne(
            'SELECT MAX(fetched_at) FROM job_listings'
        );

        return [
            'total_jobs' => $totalJobs,
            'by_source' => array_map('intval', $bySource),
            'by_type' => array_map('intval', $byType),
            'remote_jobs' => $remoteJobs,
            'with_salary' => (int) $withSalary,
            'last_fetched_at' => $lastFetched,
        ];
    }
}
