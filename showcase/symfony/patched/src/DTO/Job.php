<?php

/**
 * Job DTO
 *
 * Represents a job listing with business logic methods.
 * DTOs contain behavior (methods) unlike shapes which are pure data structures.
 */

namespace App\DTO;

use App\Entity\JobListing;

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
        public ?string $postedAt,
    ) {}

    /**
     * Create from a shape-validated array
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
            salary: Salary::fromShape($data['salary']),
            url: $data['url'],
            tags: $data['tags'],
            source: $data['source'],
            postedAt: $data['posted_at'],
        );
    }

    /**
     * Create from Doctrine entity
     */
    public static function fromEntity(JobListing $entity): self
    {
        return new self(
            id: $entity->getId(),
            title: $entity->getTitle(),
            companyName: $entity->getCompanyName(),
            companyLogo: $entity->getCompanyLogo(),
            location: $entity->getLocation(),
            remote: $entity->isRemote(),
            jobType: $entity->getJobType(),
            salary: new Salary(
                min: $entity->getSalaryMin(),
                max: $entity->getSalaryMax(),
                currency: $entity->getSalaryCurrency(),
            ),
            url: $entity->getUrl(),
            tags: $entity->getTags(),
            source: $entity->getSource(),
            postedAt: $entity->getPostedAt()?->format('c'),
        );
    }

    /**
     * Get formatted display title with company
     */
    public function displayTitle(): string
    {
        return sprintf('%s at %s', $this->title, $this->companyName);
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
        return count(array_intersect($normalizedTags, $normalizedSearch)) > 0;
    }

    /**
     * Check if job was posted within given days
     */
    public function isRecentlyPosted(int $days = 7): bool
    {
        if ($this->postedAt === null) {
            return false;
        }

        $postedDate = new \DateTimeImmutable($this->postedAt);
        $threshold = new \DateTimeImmutable("-{$days} days");
        return $postedDate >= $threshold;
    }

    /**
     * Get job age in days
     */
    public function ageInDays(): ?int
    {
        if ($this->postedAt === null) {
            return null;
        }

        $postedDate = new \DateTimeImmutable($this->postedAt);
        $now = new \DateTimeImmutable();
        return $postedDate->diff($now)->days;
    }

    /**
     * Convert to array for JSON serialization
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
            'posted_at' => $this->postedAt,
        ];
    }
}
