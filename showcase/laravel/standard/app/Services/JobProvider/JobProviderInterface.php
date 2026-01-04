<?php

namespace App\Services\JobProvider;

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
     * @param array $options Optional filters (query, location, etc.)
     * @return array Array of normalized job data
     */
    public function fetchJobs(array $options = []): array;

    /**
     * Normalize a single job from API response to our format.
     *
     * @param array $rawJob Raw job data from API
     * @return array Normalized job data
     */
    public function normalizeJob(array $rawJob): array;
}
