<?php

namespace App\Service\JobProvider;

/**
 * Interface for job providers.
 *
 * Each provider fetches jobs from an external API and normalizes them.
 */
interface JobProviderInterface
{
    /**
     * Get the provider name/identifier.
     */
    public function getName(): string;

    /**
     * Fetch jobs from the external API.
     *
     * @param array $options Provider-specific options
     * @return array Array of normalized jobs
     */
    public function fetchJobs(array $options = []): array;

    /**
     * Normalize a single job from API response to our format.
     *
     * @param array $rawJob Raw job from API
     * @return array Normalized job data with keys:
     *   - external_id: string
     *   - source: string
     *   - title: string
     *   - company_name: string
     *   - company_logo: string|null
     *   - location: string
     *   - remote: bool
     *   - job_type: string
     *   - salary_min: int|null
     *   - salary_max: int|null
     *   - salary_currency: string|null
     *   - description: string
     *   - url: string
     *   - tags: string[]
     *   - posted_at: string|null
     */
    public function normalizeJob(array $rawJob): array;
}
