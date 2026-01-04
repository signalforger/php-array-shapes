<?php

namespace App\Services\JobProvider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Remotive.com job provider.
 *
 * Free API, no authentication required.
 * Docs: https://remotive.com/api-documentation
 */
class RemotiveProvider implements JobProviderInterface
{
    private const API_URL = 'https://remotive.com/api/remote-jobs';

    public function getName(): string
    {
        return 'remotive';
    }

    /**
     * Fetch jobs from Remotive API.
     *
     * @param array $options Filter options
     *   - category: string (e.g., 'software-dev', 'marketing')
     *   - search: string (keyword search)
     *   - limit: int (max jobs to return)
     * @return array Array of normalized jobs
     */
    public function fetchJobs(array $options = []): array
    {
        try {
            $query = [];
            if (!empty($options['category'])) {
                $query['category'] = $options['category'];
            }
            if (!empty($options['search'])) {
                $query['search'] = $options['search'];
            }
            if (!empty($options['limit'])) {
                $query['limit'] = $options['limit'];
            }

            $response = Http::timeout(30)->get(self::API_URL, $query);

            if (!$response->successful()) {
                Log::error('Remotive API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();
            $jobs = $data['jobs'] ?? [];

            return array_map(fn($job) => $this->normalizeJob($job), $jobs);
        } catch (\Exception $e) {
            Log::error('Remotive API exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Normalize Remotive job to standard format.
     *
     * @param array $rawJob Raw job from Remotive API
     * @return array Normalized job data
     */
    public function normalizeJob(array $rawJob): array
    {
        return [
            'external_id' => (string) ($rawJob['id'] ?? ''),
            'source' => $this->getName(),
            'title' => $rawJob['title'] ?? 'Unknown Title',
            'company_name' => $rawJob['company_name'] ?? 'Unknown Company',
            'company_logo' => $rawJob['company_logo'] ?? null,
            'location' => $rawJob['candidate_required_location'] ?? 'Remote',
            'remote' => true, // Remotive only has remote jobs
            'job_type' => $this->normalizeJobType($rawJob['job_type'] ?? ''),
            'salary_min' => $this->extractSalaryMin($rawJob['salary'] ?? ''),
            'salary_max' => $this->extractSalaryMax($rawJob['salary'] ?? ''),
            'salary_currency' => 'USD',
            'description' => $rawJob['description'] ?? '',
            'url' => $rawJob['url'] ?? '',
            'tags' => $rawJob['tags'] ?? [],
            'posted_at' => $rawJob['publication_date'] ?? null,
        ];
    }

    private function normalizeJobType(string $type): string
    {
        $map = [
            'full_time' => 'full-time',
            'part_time' => 'part-time',
            'contract' => 'contract',
            'freelance' => 'contract',
            'internship' => 'internship',
        ];

        return $map[strtolower($type)] ?? 'full-time';
    }

    private function extractSalaryMin(string $salary): ?int
    {
        if (empty($salary)) {
            return null;
        }
        // Try to extract numbers like "$80,000 - $120,000"
        if (preg_match('/\$?([\d,]+)\s*-/', $salary, $matches)) {
            return (int) str_replace(',', '', $matches[1]);
        }
        // Try single number
        if (preg_match('/\$?([\d,]+)/', $salary, $matches)) {
            return (int) str_replace(',', '', $matches[1]);
        }
        return null;
    }

    private function extractSalaryMax(string $salary): ?int
    {
        if (empty($salary)) {
            return null;
        }
        // Try to extract numbers like "$80,000 - $120,000"
        if (preg_match('/-\s*\$?([\d,]+)/', $salary, $matches)) {
            return (int) str_replace(',', '', $matches[1]);
        }
        return null;
    }
}
