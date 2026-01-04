<?php

namespace App\Service;

use App\Entity\JobListing;
use App\Repository\JobListingRepository;
use App\Service\JobProvider\JobProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service that aggregates jobs from multiple providers.
 */
class JobAggregatorService
{
    /** @var JobProviderInterface[] */
    private array $providers = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobListingRepository $jobRepository,
        private readonly LoggerInterface $logger
    ) {}

    public function addProvider(JobProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    /**
     * Fetch jobs from all providers and save to database.
     */
    public function fetchAllJobs(): array
    {
        $results = [];

        foreach ($this->providers as $name => $provider) {
            $this->logger->info("Fetching jobs from {$name}");

            try {
                $jobs = $provider->fetchJobs();
                $saved = 0;
                $updated = 0;

                foreach ($jobs as $jobData) {
                    $existing = $this->jobRepository->findByExternalIdAndSource(
                        $jobData['external_id'],
                        $jobData['source']
                    );

                    if ($existing) {
                        $this->updateJobFromData($existing, $jobData);
                        $updated++;
                    } else {
                        $job = $this->createJobFromData($jobData);
                        $this->entityManager->persist($job);
                        $saved++;
                    }
                }

                $this->entityManager->flush();

                $results[$name] = [
                    'fetched' => count($jobs),
                    'saved' => $saved,
                    'updated' => $updated,
                ];

                $this->logger->info("Completed {$name}", $results[$name]);
            } catch (\Exception $e) {
                $this->logger->error("Error fetching from {$name}", [
                    'error' => $e->getMessage(),
                ]);
                $results[$name] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    private function createJobFromData(array $data): JobListing
    {
        $job = new JobListing();
        return $this->updateJobFromData($job, $data);
    }

    private function updateJobFromData(JobListing $job, array $data): JobListing
    {
        $job->setExternalId($data['external_id'])
            ->setSource($data['source'])
            ->setTitle($data['title'])
            ->setCompanyName($data['company_name'])
            ->setCompanyLogo($data['company_logo'] ?? null)
            ->setLocation($data['location'])
            ->setRemote($data['remote'])
            ->setJobType($data['job_type'])
            ->setSalaryMin($data['salary_min'] ?? null)
            ->setSalaryMax($data['salary_max'] ?? null)
            ->setSalaryCurrency($data['salary_currency'] ?? null)
            ->setDescription($data['description'] ?? '')
            ->setUrl($data['url'])
            ->setTags($data['tags'] ?? [])
            ->setFetchedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime());

        if (!empty($data['posted_at'])) {
            try {
                $job->setPostedAt(new \DateTime($data['posted_at']));
            } catch (\Exception $e) {
                // Invalid date format, ignore
            }
        }

        return $job;
    }
}
