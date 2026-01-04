<?php

namespace App\Services\JobProvider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Arbeitnow job provider.
 *
 * Free API, no authentication required.
 * Docs: https://www.arbeitnow.com/api
 */
class ArbeitnowProvider implements JobProviderInterface
{
    private const API_URL = 'https://www.arbeitnow.com/api/job-board-api';

    public function getName(): string
    {
        return 'arbeitnow';
    }

    /**
     * Fetch jobs from Arbeitnow API.
     *
     * @param array $options Filter options
     *   - page: int (pagination)
     * @return array Array of normalized jobs
     */
    public function fetchJobs(array $options = []): array
    {
        try {
            $query = [];
            if (!empty($options['page'])) {
                $query['page'] = $options['page'];
            }

            $response = Http::timeout(30)->get(self::API_URL, $query);

            if (!$response->successful()) {
                Log::error('Arbeitnow API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();
            $jobs = $data['data'] ?? [];

            return array_map(fn($job) => $this->normalizeJob($job), $jobs);
        } catch (\Exception $e) {
            Log::error('Arbeitnow API exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Normalize Arbeitnow job to standard format.
     *
     * @param array $rawJob Raw job from Arbeitnow API
     * @return array Normalized job data
     */
    public function normalizeJob(array $rawJob): array
    {
        return [
            'external_id' => (string) ($rawJob['slug'] ?? uniqid()),
            'source' => $this->getName(),
            'title' => $rawJob['title'] ?? 'Unknown Title',
            'company_name' => $rawJob['company_name'] ?? 'Unknown Company',
            'company_logo' => null, // Arbeitnow doesn't provide logos in API
            'location' => $rawJob['location'] ?? 'Unknown',
            'remote' => $rawJob['remote'] ?? false,
            'job_type' => $this->normalizeJobType($rawJob['job_types'] ?? []),
            'salary_min' => null, // Arbeitnow doesn't provide salary data
            'salary_max' => null,
            'salary_currency' => null,
            'description' => $rawJob['description'] ?? '',
            'url' => $rawJob['url'] ?? '',
            'tags' => $rawJob['tags'] ?? [],
            'posted_at' => isset($rawJob['created_at']) ? date('Y-m-d H:i:s', $rawJob['created_at']) : null,
        ];
    }

    private function normalizeJobType(array $types): string
    {
        if (empty($types)) {
            return 'full-time';
        }

        $first = strtolower($types[0] ?? 'full-time');
        $map = [
            'full time' => 'full-time',
            'full-time' => 'full-time',
            'part time' => 'part-time',
            'part-time' => 'part-time',
            'contract' => 'contract',
            'freelance' => 'contract',
            'internship' => 'internship',
        ];

        return $map[$first] ?? 'full-time';
    }
}
