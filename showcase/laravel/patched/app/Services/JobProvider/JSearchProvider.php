<?php

namespace App\Services\JobProvider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * JSearch (RapidAPI) job provider.
 *
 * Requires RapidAPI key.
 * Free tier: 500 requests/month
 *
 * This version uses PHP's native typed arrays and array shapes.
 */
class JSearchProvider implements JobProviderInterface
{
    private const API_URL = 'https://jsearch.p.rapidapi.com/search';

    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.jsearch.api_key');
    }

    public function getName(): string
    {
        return 'jsearch';
    }

    /**
     * Fetch jobs from JSearch API.
     * Returns a typed array of NormalizedJob shapes.
     */
    public function fetchJobs(array $options = []): array<NormalizedJob>
    {
        if (empty($this->apiKey)) {
            Log::warning('JSearch API key not configured');
            return [];
        }

        try {
            $query = [
                'query' => $options['query'] ?? 'developer',
                'page' => $options['page'] ?? 1,
                'num_pages' => $options['num_pages'] ?? 1,
            ];

            if (!empty($options['remote_only'])) {
                $query['remote_jobs_only'] = 'true';
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => 'jsearch.p.rapidapi.com',
                ])
                ->get(self::API_URL, $query);

            if (!$response->successful()) {
                Log::error('JSearch API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();
            $jobs = $data['data'] ?? [];

            return array_map(fn($job) => $this->normalizeJob($job), $jobs);
        } catch (\Exception $e) {
            Log::error('JSearch API exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Normalize JSearch job to NormalizedJob shape.
     * The return type is enforced at runtime.
     */
    public function normalizeJob(array $rawJob): NormalizedJob
    {
        return [
            'external_id' => $rawJob['job_id'] ?? uniqid(),
            'source' => $this->getName(),
            'title' => $rawJob['job_title'] ?? 'Unknown Title',
            'company_name' => $rawJob['employer_name'] ?? 'Unknown Company',
            'company_logo' => $rawJob['employer_logo'] ?? null,
            'location' => $this->formatLocation($rawJob),
            'remote' => $rawJob['job_is_remote'] ?? false,
            'job_type' => $this->normalizeJobType($rawJob['job_employment_type'] ?? ''),
            'salary_min' => $rawJob['job_min_salary'] ?? null,
            'salary_max' => $rawJob['job_max_salary'] ?? null,
            'salary_currency' => $rawJob['job_salary_currency'] ?? 'USD',
            'description' => $rawJob['job_description'] ?? '',
            'url' => $rawJob['job_apply_link'] ?? '',
            'tags' => $this->extractTags($rawJob),
            'posted_at' => $rawJob['job_posted_at_datetime_utc'] ?? null,
        ];
    }

    private function formatLocation(array $job): string
    {
        $parts = array_filter([
            $job['job_city'] ?? null,
            $job['job_state'] ?? null,
            $job['job_country'] ?? null,
        ]);

        return implode(', ', $parts) ?: 'Unknown';
    }

    private function normalizeJobType(string $type): string
    {
        $map = [
            'FULLTIME' => 'full-time',
            'PARTTIME' => 'part-time',
            'CONTRACTOR' => 'contract',
            'INTERN' => 'internship',
        ];

        return $map[strtoupper($type)] ?? 'full-time';
    }

    private function extractTags(array $job): array
    {
        $tags = [];
        if (!empty($job['job_required_skills'])) {
            $tags = is_array($job['job_required_skills'])
                ? $job['job_required_skills']
                : explode(',', $job['job_required_skills']);
        }
        return array_map('trim', $tags);
    }
}
