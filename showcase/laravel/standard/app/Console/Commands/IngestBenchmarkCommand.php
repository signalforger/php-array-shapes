<?php

namespace App\Console\Commands;

use App\DTO\NormalizedUser;
use App\DTO\PaginatedUsers;
use App\DTO\UserResponse;
use App\DTO\UserStats;
use Illuminate\Console\Command;

/**
 * Benchmark command that ingests data from a remote API.
 * Standard PHP version - uses DTO classes for type safety (userland validation).
 *
 * Uses Random User API: https://randomuser.me
 */
class IngestBenchmarkCommand extends Command
{
    protected $signature = 'app:ingest-benchmark {--count=1000 : Number of users to fetch} {--file= : Read data from local JSON file instead of API}';
    protected $description = 'Benchmark API data ingestion and processing (standard PHP)';

    private const API_URL = 'https://randomuser.me/api/?results=%d&seed=benchmark';

    public function handle(): int
    {
        $count = (int) $this->option('count');

        $this->info("==============================================");
        $this->info("  API Ingestion Benchmark - STANDARD PHP");
        $this->info("  (using DTO classes for userland validation)");
        $this->info("==============================================");
        $this->info("PHP Version: " . PHP_VERSION);
        $this->info("Users to fetch: " . number_format($count));
        $this->newLine();

        // Fetch data from API or file (not benchmarked)
        $file = $this->option('file');
        if ($file !== null) {
            $this->info("Reading data from file...");
            $fetchStart = hrtime(true);
            $rawData = @file_get_contents($file);
            $fetchEnd = hrtime(true);
            $fetchTime = ($fetchEnd - $fetchStart) / 1e6;

            if ($rawData === false) {
                $this->error('Failed to read file: ' . $file);
                return Command::FAILURE;
            }
        } else {
            $this->info("Fetching data from Random User API...");
            $fetchStart = hrtime(true);
            $rawData = $this->fetchFromApi($count);
            $fetchEnd = hrtime(true);
            $fetchTime = ($fetchEnd - $fetchStart) / 1e6;

            if ($rawData === null) {
                $this->error('Failed to fetch data from API');
                return Command::FAILURE;
            }
        }

        $this->line("  Fetch time: " . number_format($fetchTime, 2) . " ms (not included in benchmark)");
        $this->line("  Raw data size: " . number_format(strlen($rawData) / 1024, 2) . " KB");
        $this->newLine();

        $results = [];

        // Benchmark 1: JSON Decode
        $this->info("Benchmark 1: Decoding JSON...");
        $start = hrtime(true);
        $decoded = json_decode($rawData, true);
        $end = hrtime(true);
        $results['json_decode'] = ($end - $start) / 1e6;
        $users = $decoded['results'] ?? [];
        $this->line("  Time: " . number_format($results['json_decode'], 2) . " ms");
        $this->line("  Users decoded: " . count($users));

        // Benchmark 2: Normalize user data (using DTO classes)
        $this->info("Benchmark 2: Normalizing user data (DTO classes)...");
        $start = hrtime(true);
        $normalizedCount = 0;
        foreach ($this->normalizeUsers($users) as $user) {
            $normalizedCount++;
        }
        $end = hrtime(true);
        $results['normalize'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['normalize'], 2) . " ms");
        $this->line("  Users normalized: " . $normalizedCount);

        // Benchmark 3: Transform to API response format (using DTO classes)
        $this->info("Benchmark 3: Transforming to API response (DTO classes)...");
        $start = hrtime(true);
        $transformedCount = 0;
        foreach ($this->transformToResponse($users) as $response) {
            $transformedCount++;
        }
        $end = hrtime(true);
        $results['transform'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['transform'], 2) . " ms");
        $this->line("  Users transformed: " . $transformedCount);

        // Benchmark 4: Aggregate statistics (using DTO class)
        $this->info("Benchmark 4: Aggregating statistics (DTO class)...");
        $start = hrtime(true);
        $stats = UserStats::fromUsers($users);
        $end = hrtime(true);
        $results['aggregate'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['aggregate'], 2) . " ms");
        $this->line("  Countries: " . count($stats->byCountry));
        $this->line("  Genders: " . implode(', ', array_keys($stats->byGender)));

        // Benchmark 5: Filter and search
        $this->info("Benchmark 5: Filtering and searching...");
        $start = hrtime(true);
        $filtered = $this->filterUsers($users, ['gender' => 'female', 'min_age' => 25, 'max_age' => 45]);
        $end = hrtime(true);
        $results['filter'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['filter'], 2) . " ms");
        $this->line("  Matching users: " . count($filtered));

        // Benchmark 6: Build paginated response (using DTO class)
        $this->info("Benchmark 6: Building paginated responses (DTO classes)...");
        $start = hrtime(true);
        $pageCount = 0;
        foreach ($this->buildPaginatedResponses($users, 50) as $page) {
            $pageCount++;
        }
        $end = hrtime(true);
        $results['paginate'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['paginate'], 2) . " ms");
        $this->line("  Pages created: " . $pageCount);

        // Summary
        $this->newLine();
        $this->info("==============================================");
        $this->info("  SUMMARY");
        $this->info("==============================================");
        $total = array_sum($results);
        $this->line("Total processing time: " . number_format($total, 2) . " ms");
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
            'user_count' => $count,
            'results' => $results,
            'total_ms' => $total,
            'memory_mb' => memory_get_peak_usage(true) / 1024 / 1024,
        ]));

        return Command::SUCCESS;
    }

    private function fetchFromApi(int $count): ?string
    {
        $url = sprintf(self::API_URL, min($count, 5000));
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'PHP Benchmark/1.0',
            ],
        ]);

        $data = @file_get_contents($url, false, $context);
        return $data !== false ? $data : null;
    }

    /**
     * Normalize raw API user data using DTO classes.
     *
     * @param array $users
     * @return \Generator<NormalizedUser>
     */
    private function normalizeUsers(array $users): \Generator
    {
        foreach ($users as $user) {
            yield NormalizedUser::fromArray($user);
        }
    }

    /**
     * Transform users to API response format using DTO classes.
     *
     * @param array $users
     * @return \Generator<UserResponse>
     */
    private function transformToResponse(array $users): \Generator
    {
        foreach ($users as $user) {
            yield UserResponse::fromArray($user);
        }
    }

    /**
     * Filter users by criteria.
     *
     * @param array $users
     * @param array $criteria
     * @return array
     */
    private function filterUsers(array $users, array $criteria): array
    {
        $results = [];

        foreach ($users as $user) {
            $matches = true;

            if (isset($criteria['gender']) && ($user['gender'] ?? '') !== $criteria['gender']) {
                $matches = false;
            }

            if ($matches && isset($criteria['min_age'])) {
                $age = $user['dob']['age'] ?? 0;
                if ($age < $criteria['min_age']) {
                    $matches = false;
                }
            }

            if ($matches && isset($criteria['max_age'])) {
                $age = $user['dob']['age'] ?? 0;
                if ($age > $criteria['max_age']) {
                    $matches = false;
                }
            }

            if ($matches && isset($criteria['country'])) {
                if (($user['nat'] ?? '') !== $criteria['country']) {
                    $matches = false;
                }
            }

            if ($matches) {
                $results[] = $user;
            }
        }

        return $results;
    }

    /**
     * Build paginated responses using DTO classes.
     *
     * @param array $users
     * @param int $perPage
     * @return \Generator<PaginatedUsers>
     */
    private function buildPaginatedResponses(array $users, int $perPage): \Generator
    {
        $total = count($users);
        $pages = (int) ceil($total / $perPage);

        for ($page = 0; $page < $pages; $page++) {
            $offset = $page * $perPage;
            $pageUsers = array_slice($users, $offset, $perPage);

            yield PaginatedUsers::create(
                $pageUsers,
                $page + 1,
                $perPage,
                $total,
                $pages,
                $offset
            );
        }
    }
}
