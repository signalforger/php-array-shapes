<?php

namespace App\Service\JobProvider;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Remotive.com job provider.
 *
 * Free API, no authentication required.
 * Docs: https://remotive.com/api-documentation
 */
class RemotiveProvider implements JobProviderInterface
{
    private const API_URL = 'https://remotive.com/api/remote-jobs';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger
    ) {}

    public function getName(): string
    {
        return 'remotive';
    }

    /**
     * Fetch jobs from Remotive API.
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

            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => $query,
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Remotive API error', [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(false),
                ]);
                return [];
            }

            $data = $response->toArray();
            $jobs = $data['jobs'] ?? [];

            return array_map(fn($job) => $this->normalizeJob($job), $jobs);
        } catch (\Exception $e) {
            $this->logger->error('Remotive API exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Normalize Remotive job to standard format.
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
            'remote' => true,
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
        if (preg_match('/\$?([\d,]+)\s*-/', $salary, $matches)) {
            return (int) str_replace(',', '', $matches[1]);
        }
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
        if (preg_match('/-\s*\$?([\d,]+)/', $salary, $matches)) {
            return (int) str_replace(',', '', $matches[1]);
        }
        return null;
    }
}
