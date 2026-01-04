<?php

namespace App\Command;

use App\DTO\NormalizedUser;
use App\DTO\PaginatedUsers;
use App\DTO\UserResponse;
use App\DTO\UserStats;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Benchmark command that ingests data from a remote API.
 * Standard PHP version - uses DTO classes for type safety (userland validation).
 *
 * Uses Random User API: https://randomuser.me
 */
#[AsCommand(
    name: 'app:ingest-benchmark',
    description: 'Benchmark API data ingestion and processing (standard PHP)',
)]
class IngestBenchmarkCommand extends Command
{
    private const API_URL = 'https://randomuser.me/api/?results=%d&seed=benchmark';

    protected function configure(): void
    {
        $this->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Number of users to fetch', 1000);
        $this->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Read data from local JSON file instead of API');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');

        $io->title('API Ingestion Benchmark - STANDARD PHP (DTO Classes)');
        $io->text([
            '(using DTO classes for userland type validation)',
            'PHP Version: ' . PHP_VERSION,
            'Users to fetch: ' . number_format($count),
        ]);

        // Fetch data from API or file (not benchmarked)
        $file = $input->getOption('file');
        if ($file !== null) {
            $io->section('Reading data from file...');
            $fetchStart = hrtime(true);
            $rawData = @file_get_contents($file);
            $fetchEnd = hrtime(true);
            $fetchTime = ($fetchEnd - $fetchStart) / 1e6;

            if ($rawData === false) {
                $io->error('Failed to read file: ' . $file);
                return Command::FAILURE;
            }
        } else {
            $io->section('Fetching data from Random User API...');
            $fetchStart = hrtime(true);
            $rawData = $this->fetchFromApi($count);
            $fetchEnd = hrtime(true);
            $fetchTime = ($fetchEnd - $fetchStart) / 1e6;

            if ($rawData === null) {
                $io->error('Failed to fetch data from API');
                return Command::FAILURE;
            }
        }

        $io->text([
            '  Fetch time: ' . number_format($fetchTime, 2) . ' ms (not included in benchmark)',
            '  Raw data size: ' . number_format(strlen($rawData) / 1024, 2) . ' KB',
        ]);

        $results = [];

        // Benchmark 1: JSON Decode
        $io->section('Benchmark 1: Decoding JSON...');
        $start = hrtime(true);
        $decoded = json_decode($rawData, true);
        $end = hrtime(true);
        $results['json_decode'] = ($end - $start) / 1e6;
        $users = $decoded['results'] ?? [];
        $io->text([
            '  Time: ' . number_format($results['json_decode'], 2) . ' ms',
            '  Users decoded: ' . count($users),
        ]);

        // Benchmark 2: Normalize user data (using DTO classes)
        $io->section('Benchmark 2: Normalizing user data (DTO classes)...');
        $start = hrtime(true);
        $normalizedCount = 0;
        foreach ($this->normalizeUsers($users) as $user) {
            $normalizedCount++;
        }
        $end = hrtime(true);
        $results['normalize'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['normalize'], 2) . ' ms',
            '  Users normalized: ' . $normalizedCount,
        ]);

        // Benchmark 3: Transform to API response format (using DTO classes)
        $io->section('Benchmark 3: Transforming to API response (DTO classes)...');
        $start = hrtime(true);
        $transformedCount = 0;
        foreach ($this->transformToResponse($users) as $response) {
            $transformedCount++;
        }
        $end = hrtime(true);
        $results['transform'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['transform'], 2) . ' ms',
            '  Users transformed: ' . $transformedCount,
        ]);

        // Benchmark 4: Aggregate statistics (using DTO class)
        $io->section('Benchmark 4: Aggregating statistics (DTO class)...');
        $start = hrtime(true);
        $stats = UserStats::fromUsers($users);
        $end = hrtime(true);
        $results['aggregate'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['aggregate'], 2) . ' ms',
            '  Countries: ' . count($stats->byCountry),
            '  Genders: ' . implode(', ', array_keys($stats->byGender)),
        ]);

        // Benchmark 5: Filter and search
        $io->section('Benchmark 5: Filtering and searching...');
        $start = hrtime(true);
        $filtered = $this->filterUsers($users, ['gender' => 'female', 'min_age' => 25, 'max_age' => 45]);
        $end = hrtime(true);
        $results['filter'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['filter'], 2) . ' ms',
            '  Matching users: ' . count($filtered),
        ]);

        // Benchmark 6: Build paginated response (using DTO class)
        $io->section('Benchmark 6: Building paginated responses (DTO classes)...');
        $start = hrtime(true);
        $pageCount = 0;
        foreach ($this->buildPaginatedResponses($users, 50) as $page) {
            $pageCount++;
        }
        $end = hrtime(true);
        $results['paginate'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['paginate'], 2) . ' ms',
            '  Pages created: ' . $pageCount,
        ]);

        // Summary
        $io->title('SUMMARY');
        $total = array_sum($results);
        $io->text([
            'Total processing time: ' . number_format($total, 2) . ' ms',
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
            'variant' => 'standard',
            'framework' => 'symfony',
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
