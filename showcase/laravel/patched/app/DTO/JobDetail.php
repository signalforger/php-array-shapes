<?php

/**
 * JobDetail DTO
 *
 * Extended job information including full description.
 * Used for single job view pages.
 */

namespace App\DTO;

readonly class JobDetail
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
        public string $description,
        public ?\DateTimeImmutable $fetchedAt,
        public ?string $applicationUrl = null,
        public ?string $companyUrl = null,
    ) {}

    /**
     * Create from a shape-validated JobDetailData array
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
            description: $data['description'],
            fetchedAt: isset($data['fetched_at'])
                ? new \DateTimeImmutable($data['fetched_at'])
                : null,
            applicationUrl: $data['application_url'] ?? null,
            companyUrl: $data['company_url'] ?? null,
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
            description: $model->description ?? '',
            fetchedAt: $model->fetched_at,
            applicationUrl: $model->application_url ?? null,
            companyUrl: $model->company_url ?? null,
        );
    }

    /**
     * Get the base Job DTO (for list views)
     */
    public function toJob(): Job
    {
        return new Job(
            id: $this->id,
            title: $this->title,
            companyName: $this->companyName,
            companyLogo: $this->companyLogo,
            location: $this->location,
            remote: $this->remote,
            jobType: $this->jobType,
            salary: $this->salary,
            url: $this->url,
            tags: $this->tags,
            source: $this->source,
            postedAt: $this->postedAt,
        );
    }

    /**
     * Get description excerpt
     */
    public function excerpt(int $length = 200): string
    {
        $stripped = strip_tags($this->description);

        if (strlen($stripped) <= $length) {
            return $stripped;
        }

        return substr($stripped, 0, $length) . '...';
    }

    /**
     * Get word count of description
     */
    public function descriptionWordCount(): int
    {
        return str_word_count(strip_tags($this->description));
    }

    /**
     * Estimate reading time in minutes
     */
    public function readingTimeMinutes(): int
    {
        $wordsPerMinute = 200;
        return max(1, (int) ceil($this->descriptionWordCount() / $wordsPerMinute));
    }

    /**
     * Check if description contains keyword
     */
    public function descriptionContains(string $keyword): bool
    {
        return str_contains(strtolower($this->description), strtolower($keyword));
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
            'description' => $this->description,
            'fetched_at' => $this->fetchedAt?->format(\DateTimeInterface::ISO8601),
            'application_url' => $this->applicationUrl,
            'company_url' => $this->companyUrl,
        ];
    }
}
