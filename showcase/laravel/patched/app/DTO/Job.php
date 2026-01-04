<?php

/**
 * Job DTO
 *
 * Represents a job listing for internal application use.
 * Contains business logic methods for job processing.
 *
 * This is the internal representation - created from shape-validated
 * boundary data or from database models.
 */

namespace App\DTO;

readonly class Job
{
    public function __construct(
        public int|string $id,
        public string $title,
        public string $companyName,
        public ?string $companyLogo,
        public string $location,
        public bool $remote,
        public string $jobType,
        public Salary $salary,
        public string $url,
        public array $tags,
        public string $source,
        public ?\DateTimeImmutable $postedAt,
    ) {}

    /**
     * Create from a shape-validated JobData array
     */
    public static function fromShape(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            companyName: $data['company_name'],
            companyLogo: $data['company_logo'],
            location: $data['location'],
            remote: $data['remote'],
            jobType: $data['job_type'],
            salary: new Salary(
                min: $data['salary']['min'] ?? null,
                max: $data['salary']['max'] ?? null,
                currency: $data['salary']['currency'] ?? null,
            ),
            url: $data['url'],
            tags: $data['tags'],
            source: $data['source'],
            postedAt: isset($data['posted_at'])
                ? new \DateTimeImmutable($data['posted_at'])
                : null,
        );
    }

    /**
     * Create from Eloquent model
     */
    public static function fromModel(\App\Models\JobListing $model): self
    {
        return new self(
            id: $model->id,
            title: $model->title,
            companyName: $model->company_name,
            companyLogo: $model->company_logo,
            location: $model->location,
            remote: $model->remote,
            jobType: $model->job_type,
            salary: new Salary(
                min: $model->salary_min,
                max: $model->salary_max,
                currency: $model->salary_currency,
            ),
            url: $model->url,
            tags: $model->tags ?? [],
            source: $model->source,
            postedAt: $model->posted_at,
        );
    }

    /**
     * Get display-friendly title with company
     */
    public function displayTitle(): string
    {
        return "{$this->title} at {$this->companyName}";
    }

    /**
     * Check if job matches search query
     */
    public function matchesQuery(string $query): bool
    {
        $query = strtolower($query);

        return str_contains(strtolower($this->title), $query)
            || str_contains(strtolower($this->companyName), $query)
            || str_contains(strtolower($this->location), $query);
    }

    /**
     * Check if job has any of the given tags
     */
    public function hasAnyTag(array $searchTags): bool
    {
        $normalizedTags = array_map('strtolower', $this->tags);
        $normalizedSearch = array_map('strtolower', $searchTags);

        return !empty(array_intersect($normalizedTags, $normalizedSearch));
    }

    /**
     * Check if job was posted within days
     */
    public function isRecentlyPosted(int $days = 7): bool
    {
        if ($this->postedAt === null) {
            return false;
        }

        $threshold = new \DateTimeImmutable("-{$days} days");
        return $this->postedAt >= $threshold;
    }

    /**
     * Get age in days
     */
    public function ageInDays(): ?int
    {
        if ($this->postedAt === null) {
            return null;
        }

        return (int) $this->postedAt->diff(new \DateTimeImmutable())->days;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company_name' => $this->companyName,
            'company_logo' => $this->companyLogo,
            'location' => $this->location,
            'remote' => $this->remote,
            'job_type' => $this->jobType,
            'salary' => $this->salary->toArray(),
            'url' => $this->url,
            'tags' => $this->tags,
            'source' => $this->source,
            'posted_at' => $this->postedAt?->format(\DateTimeInterface::ISO8601),
        ];
    }
}
