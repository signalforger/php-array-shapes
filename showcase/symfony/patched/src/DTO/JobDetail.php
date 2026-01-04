<?php

/**
 * JobDetail DTO
 *
 * Extended job information including full description.
 * DTOs contain behavior (methods) unlike shapes which are pure data structures.
 */

namespace App\DTO;

use App\Entity\JobListing;

readonly class JobDetail extends Job
{
    public function __construct(
        int|string $id,
        string $title,
        string $companyName,
        ?string $companyLogo,
        string $location,
        bool $remote,
        string $jobType,
        Salary $salary,
        string $url,
        array $tags,
        string $source,
        ?string $postedAt,
        public string $description,
        public ?string $fetchedAt,
        public ?string $applicationUrl = null,
        public ?string $companyUrl = null,
    ) {
        parent::__construct(
            id: $id,
            title: $title,
            companyName: $companyName,
            companyLogo: $companyLogo,
            location: $location,
            remote: $remote,
            jobType: $jobType,
            salary: $salary,
            url: $url,
            tags: $tags,
            source: $source,
            postedAt: $postedAt,
        );
    }

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
            description: $data['description'],
            fetchedAt: $data['fetched_at'] ?? null,
            applicationUrl: $data['application_url'] ?? null,
            companyUrl: $data['company_url'] ?? null,
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
            description: $entity->getDescription() ?? '',
            fetchedAt: $entity->getFetchedAt()?->format('c'),
            applicationUrl: $entity->getApplicationUrl(),
            companyUrl: $entity->getCompanyUrl(),
        );
    }

    /**
     * Get truncated description excerpt
     */
    public function excerpt(int $maxLength = 200): string
    {
        $plainText = strip_tags($this->description);
        if (strlen($plainText) <= $maxLength) {
            return $plainText;
        }
        return substr($plainText, 0, $maxLength) . '...';
    }

    /**
     * Get description word count
     */
    public function descriptionWordCount(): int
    {
        return str_word_count(strip_tags($this->description));
    }

    /**
     * Estimate reading time in minutes
     */
    public function readingTimeMinutes(int $wordsPerMinute = 200): int
    {
        return max(1, (int) ceil($this->descriptionWordCount() / $wordsPerMinute));
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'description' => $this->description,
            'fetched_at' => $this->fetchedAt,
            'application_url' => $this->applicationUrl,
            'company_url' => $this->companyUrl,
            'excerpt' => $this->excerpt(),
            'reading_time_minutes' => $this->readingTimeMinutes(),
        ]);
    }
}
