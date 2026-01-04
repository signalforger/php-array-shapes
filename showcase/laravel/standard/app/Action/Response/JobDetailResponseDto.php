<?php

namespace App\Action\Response;

readonly class JobDetailResponseDto implements \JsonSerializable
{
    /**
     * @param array<string> $tags
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $companyName,
        public ?string $companyLogo,
        public string $location,
        public bool $remote,
        public string $jobType,
        public SalaryRangeDto $salary,
        public string $url,
        public array $tags,
        public string $source,
        public ?string $postedAt,
        public string $description,
        public ?string $fetchedAt,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company_name' => $this->companyName,
            'company_logo' => $this->companyLogo,
            'location' => $this->location,
            'remote' => $this->remote,
            'job_type' => $this->jobType,
            'salary' => $this->salary->jsonSerialize(),
            'url' => $this->url,
            'tags' => $this->tags,
            'source' => $this->source,
            'posted_at' => $this->postedAt,
            'description' => $this->description,
            'fetched_at' => $this->fetchedAt,
        ];
    }
}
