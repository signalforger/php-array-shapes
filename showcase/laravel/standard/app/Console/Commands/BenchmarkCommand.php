<?php

namespace App\Console\Commands;

use App\Models\JobListing;
use Illuminate\Console\Command;

/**
 * Benchmark command for standard PHP (without typed arrays).
 *
 * Creates models in memory and processes arrays to measure performance.
 */
class BenchmarkCommand extends Command
{
    protected $signature = 'app:benchmark {--iterations=10000 : Number of iterations}';
    protected $description = 'Benchmark model creation and array processing (standard PHP)';

    public function handle(): int
    {
        $iterations = (int) $this->option('iterations');

        $this->info("==============================================");
        $this->info("  PHP Array Shapes Benchmark - STANDARD PHP");
        $this->info("==============================================");
        $this->info("PHP Version: " . PHP_VERSION);
        $this->info("Iterations: " . number_format($iterations));
        $this->newLine();

        $results = [];

        // Benchmark 1: Create JobListing models (not saved)
        $this->info("Benchmark 1: Creating Eloquent models in memory...");
        $start = hrtime(true);
        $modelCount = 0;
        foreach ($this->createModels($iterations) as $model) {
            $modelCount++;
        }
        $end = hrtime(true);
        $results['model_creation'] = ($end - $start) / 1e6; // Convert to ms
        $this->line("  Time: " . number_format($results['model_creation'], 2) . " ms");
        $this->line("  Models created: " . $modelCount);

        // Benchmark 2: Transform models to arrays (chained generators)
        $this->info("Benchmark 2: Transforming models to API response arrays...");
        $start = hrtime(true);
        $responseCount = 0;
        foreach ($this->transformToResponses($this->createModels($iterations)) as $response) {
            $responseCount++;
        }
        $end = hrtime(true);
        $results['array_transform'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['array_transform'], 2) . " ms");
        $this->line("  Arrays created: " . $responseCount);

        // Benchmark 3: Create normalized job arrays (simulating API ingestion)
        $this->info("Benchmark 3: Creating normalized job arrays...");
        $start = hrtime(true);
        $normalizedCount = 0;
        foreach ($this->createNormalizedJobs($iterations) as $job) {
            $normalizedCount++;
        }
        $end = hrtime(true);
        $results['normalized_creation'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['normalized_creation'], 2) . " ms");
        $this->line("  Normalized jobs: " . $normalizedCount);

        // Benchmark 4: Validate and process arrays (generator consumed inline)
        $this->info("Benchmark 4: Processing and accessing array data...");
        $start = hrtime(true);
        $processed = $this->processArrayData($this->createNormalizedJobs($iterations));
        $end = hrtime(true);
        $results['array_processing'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['array_processing'], 2) . " ms");
        $this->line("  Processed: " . $processed);

        // Benchmark 5: Nested array creation
        $this->info("Benchmark 5: Creating nested response structures...");
        $start = hrtime(true);
        $nestedCount = 0;
        foreach ($this->createNestedResponses($iterations) as $page) {
            $nestedCount++;
        }
        $end = hrtime(true);
        $results['nested_creation'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['nested_creation'], 2) . " ms");

        // Summary
        $this->newLine();
        $this->info("==============================================");
        $this->info("  SUMMARY");
        $this->info("==============================================");
        $total = array_sum($results);
        $this->line("Total time: " . number_format($total, 2) . " ms");
        $this->line("Memory peak: " . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB");

        $this->newLine();
        $this->table(
            ['Benchmark', 'Time (ms)'],
            array_map(fn($k, $v) => [$k, number_format($v, 2)], array_keys($results), $results)
        );

        // Output JSON for comparison
        $this->newLine();
        $this->line("JSON Output (for comparison):");
        $this->line(json_encode([
            'variant' => 'standard',
            'framework' => 'laravel',
            'php_version' => PHP_VERSION,
            'iterations' => $iterations,
            'results' => $results,
            'total_ms' => $total,
            'memory_mb' => memory_get_peak_usage(true) / 1024 / 1024,
        ]));

        return Command::SUCCESS;
    }

    /**
     * Create JobListing models in memory (not persisted).
     * Uses generator for cooperative multitasking.
     */
    private function createModels(int $count): \Generator
    {
        for ($i = 0; $i < $count; $i++) {
            $model = new JobListing();
            $model->external_id = "job-{$i}";
            $model->source = 'benchmark';
            $model->title = "Software Engineer {$i}";
            $model->company_name = "Company {$i}";
            $model->company_logo = "https://example.com/logo-{$i}.png";
            $model->location = "Remote";
            $model->remote = true;
            $model->job_type = 'full-time';
            $model->salary_min = 80000 + ($i * 100);
            $model->salary_max = 120000 + ($i * 100);
            $model->salary_currency = 'USD';
            $model->description = "Job description for position {$i}";
            $model->url = "https://example.com/jobs/{$i}";
            $model->tags = ['php', 'laravel', 'remote'];
            yield $model;
        }
    }

    /**
     * Transform models to API response format.
     * Uses generator for cooperative multitasking.
     *
     * @param iterable $models
     * @return \Generator
     */
    private function transformToResponses(iterable $models): \Generator
    {
        foreach ($models as $model) {
            yield $this->formatJobResponse($model);
        }
    }

    /**
     * Format a job model to response array.
     * Standard PHP version - uses PHPDoc for documentation.
     *
     * @param JobListing $job
     * @return array{id: int|null, title: string, company_name: string, salary: array}
     */
    private function formatJobResponse(JobListing $job): array
    {
        return [
            'id' => $job->id,
            'title' => $job->title,
            'company_name' => $job->company_name,
            'company_logo' => $job->company_logo,
            'location' => $job->location,
            'remote' => $job->remote,
            'job_type' => $job->job_type,
            'salary' => $this->formatSalary($job),
            'url' => $job->url,
            'tags' => $job->tags ?? [],
            'source' => $job->source,
        ];
    }

    /**
     * Format salary range.
     *
     * @param JobListing $job
     * @return array{min: int|null, max: int|null, currency: string|null, formatted: string}
     */
    private function formatSalary(JobListing $job): array
    {
        $formatted = 'Not specified';
        if ($job->salary_min || $job->salary_max) {
            $currency = $job->salary_currency ?? 'USD';
            if ($job->salary_min && $job->salary_max) {
                $formatted = sprintf('%s %s - %s', $currency, number_format($job->salary_min), number_format($job->salary_max));
            } elseif ($job->salary_min) {
                $formatted = sprintf('%s %s+', $currency, number_format($job->salary_min));
            } else {
                $formatted = sprintf('Up to %s %s', $currency, number_format($job->salary_max));
            }
        }

        return [
            'min' => $job->salary_min,
            'max' => $job->salary_max,
            'currency' => $job->salary_currency,
            'formatted' => $formatted,
        ];
    }

    /**
     * Create normalized job arrays (simulating API ingestion).
     * Uses generator for cooperative multitasking.
     *
     * @param int $count
     * @return \Generator
     */
    private function createNormalizedJobs(int $count): \Generator
    {
        for ($i = 0; $i < $count; $i++) {
            yield $this->createNormalizedJob($i);
        }
    }

    /**
     * Create a single normalized job array.
     * Standard PHP version - no runtime type validation.
     *
     * @param int $i
     * @return array
     */
    private function createNormalizedJob(int $i): array
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
     * Process array data - access fields to simulate real work.
     * Accepts iterable for generator support.
     *
     * @param iterable $jobs
     * @return int
     */
    private function processArrayData(iterable $jobs): int
    {
        $totalSalary = 0;
        $remoteCount = 0;

        foreach ($jobs as $job) {
            // Access various fields to simulate real processing
            $totalSalary += ($job['salary_min'] ?? 0) + ($job['salary_max'] ?? 0);
            if ($job['remote'] === true) {
                $remoteCount++;
            }
            // String operations
            $titleLen = strlen($job['title']);
            $hasLogo = $job['company_logo'] !== null;
            $tagCount = count($job['tags']);
        }

        return $remoteCount;
    }

    /**
     * Create nested response structures.
     * Uses generator for cooperative multitasking.
     *
     * @param int $count
     * @return \Generator
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

            yield [
                'data' => $data,
                'meta' => [
                    'current_page' => $page + 1,
                    'per_page' => $pageSize,
                    'total' => $count,
                    'last_page' => $pages,
                ],
            ];
        }
    }
}
