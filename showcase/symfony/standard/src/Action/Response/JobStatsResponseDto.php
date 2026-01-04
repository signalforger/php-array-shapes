<?php

namespace App\Action\Response;

/**
 * Job statistics.
 */
readonly class JobStatsResponseDto implements \JsonSerializable
{
    /**
     * @param array<string, int> $bySource
     * @param array<string, int> $byType
     */
    public function __construct(
        public int $totalJobs,
        public array $bySource,
        public array $byType,
        public int $remoteJobs,
        public int $withSalary,
        public ?string $lastFetchedAt,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'total_jobs' => $this->totalJobs,
            'by_source' => $this->bySource,
            'by_type' => $this->byType,
            'remote_jobs' => $this->remoteJobs,
            'with_salary' => $this->withSalary,
            'last_fetched_at' => $this->lastFetchedAt,
        ];
    }
}
