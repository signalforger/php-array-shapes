<?php

namespace App\Services;

use App\Models\JobListing;
use App\Services\JobProvider\JobProviderInterface;
use App\Services\JobProvider\RemotiveProvider;
use App\Services\JobProvider\ArbeitnowProvider;
use App\Services\JobProvider\JSearchProvider;
use Illuminate\Support\Facades\Log;

/**
 * Service that aggregates jobs from multiple providers.
 */
class JobAggregatorService
{
    /**
     * @var JobProviderInterface[]
     */
    private array $providers;

    public function __construct()
    {
        $this->providers = [
            new RemotiveProvider(),
            new ArbeitnowProvider(),
            new JSearchProvider(),
        ];
    }

    /**
     * Get all registered providers.
     *
     * @return JobProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Fetch jobs from all providers and save to database.
     *
     * @param array $options Options to pass to providers
     * @return array{fetched: int, saved: int, errors: int, by_provider: array}
     */
    public function fetchAndSaveAll(array $options = []): array
    {
        $stats = [
            'fetched' => 0,
            'saved' => 0,
            'errors' => 0,
            'by_provider' => [],
        ];

        foreach ($this->providers as $provider) {
            $providerName = $provider->getName();
            $providerStats = ['fetched' => 0, 'saved' => 0, 'errors' => 0];

            try {
                Log::info("Fetching jobs from {$providerName}");
                $jobs = $provider->fetchJobs($options);
                $providerStats['fetched'] = count($jobs);
                $stats['fetched'] += count($jobs);

                foreach ($jobs as $jobData) {
                    try {
                        $this->saveJob($jobData);
                        $providerStats['saved']++;
                        $stats['saved']++;
                    } catch (\Exception $e) {
                        Log::error("Error saving job from {$providerName}", [
                            'error' => $e->getMessage(),
                            'job' => $jobData['external_id'] ?? 'unknown',
                        ]);
                        $providerStats['errors']++;
                        $stats['errors']++;
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error fetching from {$providerName}", ['error' => $e->getMessage()]);
                $providerStats['errors']++;
                $stats['errors']++;
            }

            $stats['by_provider'][$providerName] = $providerStats;
        }

        return $stats;
    }

    /**
     * Fetch from a specific provider.
     *
     * @param string $providerName Provider identifier
     * @param array $options Provider-specific options
     * @return array{fetched: int, saved: int, errors: int}
     */
    public function fetchFromProvider(string $providerName, array $options = []): array
    {
        $provider = $this->getProvider($providerName);
        if (!$provider) {
            return ['fetched' => 0, 'saved' => 0, 'errors' => 1];
        }

        $stats = ['fetched' => 0, 'saved' => 0, 'errors' => 0];

        try {
            $jobs = $provider->fetchJobs($options);
            $stats['fetched'] = count($jobs);

            foreach ($jobs as $jobData) {
                try {
                    $this->saveJob($jobData);
                    $stats['saved']++;
                } catch (\Exception $e) {
                    Log::error("Error saving job", ['error' => $e->getMessage()]);
                    $stats['errors']++;
                }
            }
        } catch (\Exception $e) {
            Log::error("Error fetching from {$providerName}", ['error' => $e->getMessage()]);
            $stats['errors']++;
        }

        return $stats;
    }

    /**
     * Save or update a job listing.
     *
     * @param array $jobData Normalized job data
     * @return JobListing
     */
    public function saveJob(array $jobData): JobListing
    {
        return JobListing::updateOrCreate(
            [
                'external_id' => $jobData['external_id'],
                'source' => $jobData['source'],
            ],
            [
                'title' => $jobData['title'],
                'company_name' => $jobData['company_name'],
                'company_logo' => $jobData['company_logo'],
                'location' => $jobData['location'],
                'remote' => $jobData['remote'],
                'job_type' => $jobData['job_type'],
                'salary_min' => $jobData['salary_min'],
                'salary_max' => $jobData['salary_max'],
                'salary_currency' => $jobData['salary_currency'],
                'description' => $jobData['description'],
                'url' => $jobData['url'],
                'tags' => $jobData['tags'],
                'posted_at' => $jobData['posted_at'],
                'fetched_at' => now(),
            ]
        );
    }

    /**
     * Get a specific provider by name.
     *
     * @param string $name Provider identifier
     * @return JobProviderInterface|null
     */
    private function getProvider(string $name): ?JobProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->getName() === $name) {
                return $provider;
            }
        }
        return null;
    }
}
