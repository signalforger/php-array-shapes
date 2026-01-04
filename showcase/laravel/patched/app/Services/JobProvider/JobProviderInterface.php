<?php

namespace App\Services\JobProvider;

/**
 * Interface for job providers.
 *
 * Each provider fetches jobs from an external API and normalizes them.
 *
 * This version uses PHP's native typed arrays and array shapes for type safety.
 */

// Define a shape type alias for normalized job data
shape NormalizedJob = array{
    external_id: string,
    source: string,
    title: string,
    company_name: string,
    company_logo: ?string,
    location: string,
    remote: bool,
    job_type: string,
    salary_min: ?int,
    salary_max: ?int,
    salary_currency: ?string,
    description: string,
    url: string,
    tags: array<string>,
    posted_at: ?string
};

interface JobProviderInterface
{
    /**
     * Get the provider name/identifier.
     */
    public function getName(): string;

    /**
     * Fetch jobs from the external API.
     * Returns a typed array of NormalizedJob shapes.
     */
    public function fetchJobs(array $options = []): array<NormalizedJob>;

    /**
     * Normalize a single job from API response to our format.
     * Returns a NormalizedJob shape.
     */
    public function normalizeJob(array $rawJob): NormalizedJob;
}
