<?php

namespace App\Command;

use App\Entity\JobListing;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Benchmark command for patched PHP (WITH typed arrays and array shapes).
 *
 * Creates entities in memory and processes arrays to measure performance.
 * Uses native PHP typed arrays and shape aliases for runtime validation.
 *
 * Shape Inheritance:
 * - JobDetailResponseShape extends JobResponseShape (adds description)
 * - Uses ::shape syntax for shape name references
 */

// ============================================
// Base Shapes
// ============================================

// Salary information (reusable component)
shape SalaryShape = array{
    min: ?int,
    max: ?int,
    currency: ?string,
    formatted: string
};

// Pagination metadata (reusable)
shape PaginationMetaShape = array{
    current_page: int,
    per_page: int,
    total: int,
    last_page: int
};

// ============================================
// Job Shapes with Inheritance
// ============================================

// Base job response for list views
shape JobResponseShape = array{
    id: ?int,
    title: string,
    company_name: string,
    company_logo: ?string,
    location: string,
    remote: bool,
    job_type: string,
    salary: SalaryShape,
    url: string,
    tags: array<string>,
    source: string
};

// Detailed job response - extends base with description
shape JobDetailResponseShape extends JobResponseShape = array{
    description: string,
    posted_at: ?string
};

// Normalized job shape for internal processing
shape NormalizedJobShape = array{
    external_id: string,
    source: string,
    title: string,
    company_name: string,
    company_logo: ?string,
    location: string,
    remote: bool,
    job_type: string,
    salary_min: ?int,
    salary_max: ?int,
    salary_currency: ?string,
    description: string,
    url: string,
    tags: array<string>,
    posted_at: ?string
};

// Paginated response using normalized jobs
shape PaginatedResponseShape = array{
    data: array<NormalizedJobShape>,
    meta: PaginationMetaShape
};

#[AsCommand(
    name: 'app:benchmark',
    description: 'Benchmark entity creation and array processing (patched PHP with typed arrays)',
)]
class BenchmarkCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('iterations', 'i', InputOption::VALUE_OPTIONAL, 'Number of iterations', 10000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $iterations = (int) $input->getOption('iterations');

        $io->title('PHP Array Shapes Benchmark - PATCHED PHP');
        $io->text([
            '(with typed arrays and array shapes)',
            'PHP Version: ' . PHP_VERSION,
            'Iterations: ' . number_format($iterations),
        ]);

        // Show shape names using ::shape syntax
        $io->section('Shapes used (via ::shape syntax):');
        $io->listing([
            JobResponseShape::shape,
            JobDetailResponseShape::shape . ' (extends JobResponseShape)',
            NormalizedJobShape::shape,
            PaginatedResponseShape::shape,
        ]);

        $results = [];

        // Benchmark 1: Create JobListing entities (not saved)
        $io->section('Benchmark 1: Creating Doctrine entities in memory...');
        $start = hrtime(true);
        $entityCount = 0;
        foreach ($this->createEntities($iterations) as $entity) {
            $entityCount++;
        }
        $end = hrtime(true);
        $results['entity_creation'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['entity_creation'], 2) . ' ms',
            '  Entities created: ' . $entityCount,
        ]);

        // Benchmark 2: Transform entities to typed arrays (chained generators)
        $io->section('Benchmark 2: Transforming entities to typed API response arrays...');
        $start = hrtime(true);
        $responseCount = 0;
        foreach ($this->transformToResponses($this->createEntities($iterations)) as $response) {
            $responseCount++;
        }
        $end = hrtime(true);
        $results['array_transform'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['array_transform'], 2) . ' ms',
            '  Arrays created: ' . $responseCount,
        ]);

        // Benchmark 3: Create typed normalized job arrays
        $io->section('Benchmark 3: Creating typed normalized job arrays...');
        $start = hrtime(true);
        $normalizedCount = 0;
        foreach ($this->createNormalizedJobs($iterations) as $job) {
            $normalizedCount++;
        }
        $end = hrtime(true);
        $results['normalized_creation'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['normalized_creation'], 2) . ' ms',
            '  Normalized jobs: ' . $normalizedCount,
        ]);

        // Benchmark 4: Process typed arrays (generator consumed inline)
        $io->section('Benchmark 4: Processing and accessing typed array data...');
        $start = hrtime(true);
        $processed = $this->processArrayData($this->createNormalizedJobs($iterations));
        $end = hrtime(true);
        $results['array_processing'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['array_processing'], 2) . ' ms',
            '  Processed: ' . $processed,
        ]);

        // Benchmark 5: Nested typed array creation
        $io->section('Benchmark 5: Creating nested typed response structures...');
        $start = hrtime(true);
        $nestedCount = 0;
        foreach ($this->createNestedResponses($iterations) as $page) {
            $nestedCount++;
        }
        $end = hrtime(true);
        $results['nested_creation'] = ($end - $start) / 1e6;
        $io->text('  Time: ' . number_format($results['nested_creation'], 2) . ' ms');

        // Benchmark 6: Create detailed job responses (inherited shape)
        $io->section('Benchmark 6: Creating detailed job responses (inherited shape)...');
        $start = hrtime(true);
        $detailCount = 0;
        foreach ($this->createDetailedResponses($this->createEntities($iterations)) as $detail) {
            $detailCount++;
        }
        $end = hrtime(true);
        $results['inherited_shape'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['inherited_shape'], 2) . ' ms',
            '  Detailed responses: ' . $detailCount,
        ]);

        // Summary
        $io->title('SUMMARY');
        $total = array_sum($results);
        $io->text([
            'Total time: ' . number_format($total, 2) . ' ms',
            'Memory peak: ' . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
        ]);

        $table = new Table($output);
        $table->setHeaders(['Benchmark', 'Time (ms)']);
        foreach ($results as $name => $time) {
            $table->addRow([$name, number_format($time, 2)]);
        }
        $table->render();

        // Output JSON for comparison
        $io->newLine();
        $io->text('JSON Output (for comparison):');
        $io->text(json_encode([
            'variant' => 'patched',
            'framework' => 'symfony',
            'php_version' => PHP_VERSION,
            'iterations' => $iterations,
            'results' => $results,
            'total_ms' => $total,
            'memory_mb' => memory_get_peak_usage(true) / 1024 / 1024,
        ]));

        return Command::SUCCESS;
    }

    /**
     * Create JobListing entities in memory (not persisted).
     * Uses generator for cooperative multitasking.
     */
    private function createEntities(int $count): \Generator
    {
        for ($i = 0; $i < $count; $i++) {
            $entity = new JobListing();
            $entity->setExternalId("job-{$i}")
                   ->setSource('benchmark')
                   ->setTitle("Software Engineer {$i}")
                   ->setCompanyName("Company {$i}")
                   ->setCompanyLogo("https://example.com/logo-{$i}.png")
                   ->setLocation("Remote")
                   ->setRemote(true)
                   ->setJobType('full-time')
                   ->setSalaryMin(80000 + ($i * 100))
                   ->setSalaryMax(120000 + ($i * 100))
                   ->setSalaryCurrency('USD')
                   ->setDescription("Job description for position {$i}")
                   ->setUrl("https://example.com/jobs/{$i}")
                   ->setTags(['php', 'symfony', 'remote']);
            yield $entity;
        }
    }

    /**
     * Transform entities to typed API response format.
     * Uses generator for cooperative multitasking.
     */
    private function transformToResponses(iterable $entities): \Generator
    {
        foreach ($entities as $entity) {
            yield $this->formatJobResponse($entity);
        }
    }

    /**
     * Format a job entity to response array.
     * Returns a JobResponseShape - runtime validated.
     */
    private function formatJobResponse(JobListing $job): JobResponseShape
    {
        return [
            'id' => $job->getId(),
            'title' => $job->getTitle(),
            'company_name' => $job->getCompanyName(),
            'company_logo' => $job->getCompanyLogo(),
            'location' => $job->getLocation(),
            'remote' => $job->isRemote(),
            'job_type' => $job->getJobType(),
            'salary' => $this->formatSalary($job),
            'url' => $job->getUrl(),
            'tags' => $job->getTags() ?? [],
            'source' => $job->getSource(),
        ];
    }

    /**
     * Format a job entity to detailed response array.
     * Returns a JobDetailResponseShape - inherits from JobResponseShape.
     */
    private function formatDetailedJobResponse(JobListing $job): JobDetailResponseShape
    {
        return [
            'id' => $job->getId(),
            'title' => $job->getTitle(),
            'company_name' => $job->getCompanyName(),
            'company_logo' => $job->getCompanyLogo(),
            'location' => $job->getLocation(),
            'remote' => $job->isRemote(),
            'job_type' => $job->getJobType(),
            'salary' => $this->formatSalary($job),
            'url' => $job->getUrl(),
            'tags' => $job->getTags() ?? [],
            'source' => $job->getSource(),
            'description' => $job->getDescription() ?? '',
            'posted_at' => null,
        ];
    }

    /**
     * Transform entities to detailed responses (using inherited shape).
     */
    private function createDetailedResponses(iterable $entities): \Generator
    {
        foreach ($entities as $entity) {
            yield $this->formatDetailedJobResponse($entity);
        }
    }

    /**
     * Format salary range.
     * Returns a SalaryShape - runtime validated.
     */
    private function formatSalary(JobListing $job): SalaryShape
    {
        $formatted = 'Not specified';
        if ($job->getSalaryMin() || $job->getSalaryMax()) {
            $currency = $job->getSalaryCurrency() ?? 'USD';
            if ($job->getSalaryMin() && $job->getSalaryMax()) {
                $formatted = sprintf('%s %s - %s', $currency, number_format($job->getSalaryMin()), number_format($job->getSalaryMax()));
            } elseif ($job->getSalaryMin()) {
                $formatted = sprintf('%s %s+', $currency, number_format($job->getSalaryMin()));
            } else {
                $formatted = sprintf('Up to %s %s', $currency, number_format($job->getSalaryMax()));
            }
        }

        return [
            'min' => $job->getSalaryMin(),
            'max' => $job->getSalaryMax(),
            'currency' => $job->getSalaryCurrency(),
            'formatted' => $formatted,
        ];
    }

    /**
     * Create normalized job arrays with type validation.
     * Uses generator for cooperative multitasking.
     */
    private function createNormalizedJobs(int $count): \Generator
    {
        for ($i = 0; $i < $count; $i++) {
            yield $this->createNormalizedJob($i);
        }
    }

    /**
     * Create a single normalized job array.
     * Returns a NormalizedJobShape - runtime validated.
     */
    private function createNormalizedJob(int $i): NormalizedJobShape
    {
        return [
            'external_id' => "ext-{$i}",
            'source' => 'benchmark',
            'title' => "Developer Position {$i}",
            'company_name' => "Tech Corp {$i}",
            'company_logo' => null,
            'location' => 'Worldwide',
            'remote' => true,
            'job_type' => 'full-time',
            'salary_min' => 50000 + ($i * 50),
            'salary_max' => 100000 + ($i * 50),
            'salary_currency' => 'USD',
            'description' => "Description for job {$i}",
            'url' => "https://jobs.example.com/{$i}",
            'tags' => ['php', 'symfony', 'docker'],
            'posted_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Process typed array data.
     * Accepts iterable for generator support.
     */
    private function processArrayData(iterable $jobs): int
    {
        $totalSalary = 0;
        $remoteCount = 0;

        foreach ($jobs as $job) {
            $totalSalary += ($job['salary_min'] ?? 0) + ($job['salary_max'] ?? 0);
            if ($job['remote'] === true) {
                $remoteCount++;
            }
            $titleLen = strlen($job['title']);
            $hasLogo = $job['company_logo'] !== null;
            $tagCount = count($job['tags']);
        }

        return $remoteCount;
    }

    /**
     * Create nested response structures with typed arrays.
     * Uses generator for cooperative multitasking.
     */
    private function createNestedResponses(int $count): \Generator
    {
        $pageSize = 20;
        $pages = (int) ceil($count / $pageSize);

        for ($page = 0; $page < $pages; $page++) {
            $data = [];
            $start = $page * $pageSize;
            $end = min($start + $pageSize, $count);

            for ($i = $start; $i < $end; $i++) {
                $data[] = $this->createNormalizedJob($i);
            }

            yield $this->buildPaginatedResponse($data, $page + 1, $pageSize, $count, $pages);
        }
    }

    /**
     * Build a paginated response with type validation.
     */
    private function buildPaginatedResponse(
        array<NormalizedJobShape> $data,
        int $currentPage,
        int $perPage,
        int $total,
        int $lastPage
    ): PaginatedResponseShape {
        return [
            'data' => $data,
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }
}
