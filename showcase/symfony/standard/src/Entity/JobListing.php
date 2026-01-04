<?php

namespace App\Entity;

use App\Repository\JobListingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobListingRepository::class)]
#[ORM\Table(name: 'job_listings')]
#[ORM\Index(columns: ['source'], name: 'idx_source')]
#[ORM\Index(columns: ['job_type'], name: 'idx_job_type')]
#[ORM\Index(columns: ['remote'], name: 'idx_remote')]
#[ORM\Index(columns: ['posted_at'], name: 'idx_posted_at')]
#[ORM\UniqueConstraint(columns: ['external_id', 'source'])]
class JobListing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $externalId = null;

    #[ORM\Column(length: 50)]
    private ?string $source = null;

    #[ORM\Column(length: 500)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $companyName = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $companyLogo = null;

    #[ORM\Column(length: 255)]
    private ?string $location = null;

    #[ORM\Column]
    private bool $remote = false;

    #[ORM\Column(length: 50)]
    private ?string $jobType = 'full-time';

    #[ORM\Column(nullable: true)]
    private ?int $salaryMin = null;

    #[ORM\Column(nullable: true)]
    private ?int $salaryMax = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $salaryCurrency = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 1000)]
    private ?string $url = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tags = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $postedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fetchedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): static
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getCompanyLogo(): ?string
    {
        return $this->companyLogo;
    }

    public function setCompanyLogo(?string $companyLogo): static
    {
        $this->companyLogo = $companyLogo;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function isRemote(): bool
    {
        return $this->remote;
    }

    public function setRemote(bool $remote): static
    {
        $this->remote = $remote;
        return $this;
    }

    public function getJobType(): ?string
    {
        return $this->jobType;
    }

    public function setJobType(string $jobType): static
    {
        $this->jobType = $jobType;
        return $this;
    }

    public function getSalaryMin(): ?int
    {
        return $this->salaryMin;
    }

    public function setSalaryMin(?int $salaryMin): static
    {
        $this->salaryMin = $salaryMin;
        return $this;
    }

    public function getSalaryMax(): ?int
    {
        return $this->salaryMax;
    }

    public function setSalaryMax(?int $salaryMax): static
    {
        $this->salaryMax = $salaryMax;
        return $this;
    }

    public function getSalaryCurrency(): ?string
    {
        return $this->salaryCurrency;
    }

    public function setSalaryCurrency(?string $salaryCurrency): static
    {
        $this->salaryCurrency = $salaryCurrency;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    public function getPostedAt(): ?\DateTimeInterface
    {
        return $this->postedAt;
    }

    public function setPostedAt(?\DateTimeInterface $postedAt): static
    {
        $this->postedAt = $postedAt;
        return $this;
    }

    public function getFetchedAt(): ?\DateTimeInterface
    {
        return $this->fetchedAt;
    }

    public function setFetchedAt(?\DateTimeInterface $fetchedAt): static
    {
        $this->fetchedAt = $fetchedAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Get formatted salary range.
     *
     * @return array{min: int|null, max: int|null, currency: string|null, formatted: string}
     */
    public function getSalaryRange(): array
    {
        $formatted = 'Not specified';

        if ($this->salaryMin || $this->salaryMax) {
            $currency = $this->salaryCurrency ?? 'USD';
            if ($this->salaryMin && $this->salaryMax) {
                $formatted = sprintf('%s %s - %s', $currency, number_format($this->salaryMin), number_format($this->salaryMax));
            } elseif ($this->salaryMin) {
                $formatted = sprintf('%s %s+', $currency, number_format($this->salaryMin));
            } else {
                $formatted = sprintf('Up to %s %s', $currency, number_format($this->salaryMax));
            }
        }

        return [
            'min' => $this->salaryMin,
            'max' => $this->salaryMax,
            'currency' => $this->salaryCurrency,
            'formatted' => $formatted,
        ];
    }
}
