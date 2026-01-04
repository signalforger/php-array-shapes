<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Benchmark command that ingests data from a remote API.
 * Patched PHP version - WITH typed arrays and array shapes.
 *
 * Uses Random User API: https://randomuser.me
 */

// Shape definitions for API data structures (prefixed with User to avoid conflicts)
shape UserCoordinates = array{
    latitude: float,
    longitude: float
};

shape UserLocation = array{
    street: string,
    city: string,
    state: string,
    country: string,
    postcode: string,
    coordinates: UserCoordinates,
    timezone: string
};

shape UserPicture = array{
    large: ?string,
    medium: ?string,
    thumbnail: ?string
};

shape NormalizedUser = array{
    id: string,
    username: string,
    email: string,
    first_name: string,
    last_name: string,
    full_name: string,
    gender: string,
    age: int,
    date_of_birth: ?string,
    phone: string,
    cell: string,
    nationality: string,
    picture: UserPicture,
    location: UserLocation,
    registered_at: ?string,
    registered_years: int
};

shape UserProfile = array{
    gender: string,
    age: int,
    nationality: string
};

shape UserContact = array{
    phone: string,
    cell: string
};

shape UserAddress = array{
    formatted: string,
    city: string,
    country: string
};

shape UserResponse = array{
    id: string,
    display_name: string,
    email: string,
    avatar: ?string,
    profile: UserProfile,
    contact: UserContact,
    address: UserAddress
};

shape UserStats = array{
    total: int,
    by_gender: array<string, int>,
    by_country: array<string, int>,
    by_age_group: array<string, int>,
    average_age: float
};

shape UserPaginationMeta = array{
    current_page: int,
    per_page: int,
    total: int,
    last_page: int,
    from: int,
    to: int
};

shape PaginatedUsers = array{
    data: array<UserResponse>,
    meta: UserPaginationMeta
};

class IngestBenchmarkCommand extends Command
{
    protected $signature = 'app:ingest-benchmark {--count=1000 : Number of users to fetch} {--file= : Read data from local JSON file instead of API}';
    protected $description = 'Benchmark API data ingestion and processing (patched PHP with typed arrays)';

    private const API_URL = 'https://randomuser.me/api/?results=%d&seed=benchmark';

    public function handle(): int
    {
        $count = (int) $this->option('count');

        $this->info("==============================================");
        $this->info("  API Ingestion Benchmark - PATCHED PHP");
        $this->info("  (with typed arrays and array shapes)");
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

        // Benchmark 2: Normalize user data (with typed shapes)
        $this->info("Benchmark 2: Normalizing user data (with typed shapes)...");
        $start = hrtime(true);
        $normalizedCount = 0;
        foreach ($this->normalizeUsers($users) as $user) {
            $normalizedCount++;
        }
        $end = hrtime(true);
        $results['normalize'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['normalize'], 2) . " ms");
        $this->line("  Users normalized: " . $normalizedCount);

        // Benchmark 3: Transform to API response format (with typed shapes)
        $this->info("Benchmark 3: Transforming to typed API response format...");
        $start = hrtime(true);
        $transformedCount = 0;
        foreach ($this->transformToResponse($users) as $response) {
            $transformedCount++;
        }
        $end = hrtime(true);
        $results['transform'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['transform'], 2) . " ms");
        $this->line("  Users transformed: " . $transformedCount);

        // Benchmark 4: Aggregate statistics (with typed return)
        $this->info("Benchmark 4: Aggregating statistics (with typed return)...");
        $start = hrtime(true);
        $stats = $this->aggregateStats($users);
        $end = hrtime(true);
        $results['aggregate'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['aggregate'], 2) . " ms");
        $this->line("  Countries: " . count($stats['by_country']));
        $this->line("  Genders: " . implode(', ', array_keys($stats['by_gender'])));

        // Benchmark 5: Filter and search
        $this->info("Benchmark 5: Filtering and searching...");
        $start = hrtime(true);
        $filtered = $this->filterUsers($users, ['gender' => 'female', 'min_age' => 25, 'max_age' => 45]);
        $end = hrtime(true);
        $results['filter'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['filter'], 2) . " ms");
        $this->line("  Matching users: " . count($filtered));

        // Benchmark 6: Build paginated response (with typed shapes)
        $this->info("Benchmark 6: Building typed paginated responses...");
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
            'variant' => 'patched',
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
     * Normalize raw API user data.
     * Yields NormalizedUserShape for each user.
     */
    private function normalizeUsers(array $users): \Generator
    {
        foreach ($users as $user) {
            yield $this->normalizeUser($user);
        }
    }

    /**
     * Normalize a single user.
     * Returns a NormalizedUserShape - runtime validated.
     */
    private function normalizeUser(array $user): NormalizedUser
    {
        return [
            'id' => $user['login']['uuid'] ?? '',
            'username' => $user['login']['username'] ?? '',
            'email' => $user['email'] ?? '',
            'first_name' => $user['name']['first'] ?? '',
            'last_name' => $user['name']['last'] ?? '',
            'full_name' => trim(($user['name']['first'] ?? '') . ' ' . ($user['name']['last'] ?? '')),
            'gender' => $user['gender'] ?? '',
            'age' => $user['dob']['age'] ?? 0,
            'date_of_birth' => $user['dob']['date'] ?? null,
            'phone' => $user['phone'] ?? '',
            'cell' => $user['cell'] ?? '',
            'nationality' => $user['nat'] ?? '',
            'picture' => $this->normalizePicture($user['picture'] ?? []),
            'location' => $this->normalizeLocation($user['location'] ?? []),
            'registered_at' => $user['registered']['date'] ?? null,
            'registered_years' => $user['registered']['age'] ?? 0,
        ];
    }

    /**
     * Normalize picture data.
     */
    private function normalizePicture(array $picture): UserPicture
    {
        return [
            'large' => $picture['large'] ?? null,
            'medium' => $picture['medium'] ?? null,
            'thumbnail' => $picture['thumbnail'] ?? null,
        ];
    }

    /**
     * Normalize location data.
     */
    private function normalizeLocation(array $location): UserLocation
    {
        return [
            'street' => trim(($location['street']['number'] ?? '') . ' ' . ($location['street']['name'] ?? '')),
            'city' => $location['city'] ?? '',
            'state' => $location['state'] ?? '',
            'country' => $location['country'] ?? '',
            'postcode' => (string) ($location['postcode'] ?? ''),
            'coordinates' => $this->normalizeCoordinates($location['coordinates'] ?? []),
            'timezone' => $location['timezone']['description'] ?? '',
        ];
    }

    /**
     * Normalize coordinates.
     */
    private function normalizeCoordinates(array $coords): UserCoordinates
    {
        return [
            'latitude' => (float) ($coords['latitude'] ?? 0),
            'longitude' => (float) ($coords['longitude'] ?? 0),
        ];
    }

    /**
     * Transform users to API response format.
     * Yields UserResponseShape for each user.
     */
    private function transformToResponse(array $users): \Generator
    {
        foreach ($users as $user) {
            yield $this->formatUserResponse($user);
        }
    }

    /**
     * Format a user for API response.
     * Returns a UserResponseShape - runtime validated.
     */
    private function formatUserResponse(array $user): UserResponse
    {
        $name = $user['name'] ?? [];
        $location = $user['location'] ?? [];

        return [
            'id' => $user['login']['uuid'] ?? '',
            'display_name' => trim(($name['title'] ?? '') . ' ' . ($name['first'] ?? '') . ' ' . ($name['last'] ?? '')),
            'email' => $user['email'] ?? '',
            'avatar' => $user['picture']['large'] ?? null,
            'profile' => $this->buildProfile($user),
            'contact' => $this->buildContact($user),
            'address' => $this->buildAddress($location),
        ];
    }

    /**
     * Build profile shape.
     */
    private function buildProfile(array $user): UserProfile
    {
        return [
            'gender' => $user['gender'] ?? '',
            'age' => $user['dob']['age'] ?? 0,
            'nationality' => $user['nat'] ?? '',
        ];
    }

    /**
     * Build contact shape.
     */
    private function buildContact(array $user): UserContact
    {
        return [
            'phone' => $user['phone'] ?? '',
            'cell' => $user['cell'] ?? '',
        ];
    }

    /**
     * Build address shape.
     */
    private function buildAddress(array $location): UserAddress
    {
        return [
            'formatted' => $this->formatAddress($location),
            'city' => $location['city'] ?? '',
            'country' => $location['country'] ?? '',
        ];
    }

    /**
     * Format address for display.
     */
    private function formatAddress(array $location): string
    {
        $parts = array_filter([
            trim(($location['street']['number'] ?? '') . ' ' . ($location['street']['name'] ?? '')),
            $location['city'] ?? '',
            $location['state'] ?? '',
            (string) ($location['postcode'] ?? ''),
            $location['country'] ?? '',
        ]);

        return implode(', ', $parts);
    }

    /**
     * Aggregate statistics from user data.
     * Returns a StatsShape - runtime validated.
     */
    private function aggregateStats(array $users): UserStats
    {
        $stats = [
            'total' => count($users),
            'by_gender' => [],
            'by_country' => [],
            'by_age_group' => [
                '18-25' => 0,
                '26-35' => 0,
                '36-45' => 0,
                '46-55' => 0,
                '56+' => 0,
            ],
            'average_age' => 0.0,
        ];

        $totalAge = 0;

        foreach ($users as $user) {
            // Gender
            $gender = $user['gender'] ?? 'unknown';
            $stats['by_gender'][$gender] = ($stats['by_gender'][$gender] ?? 0) + 1;

            // Country
            $country = $user['nat'] ?? 'unknown';
            $stats['by_country'][$country] = ($stats['by_country'][$country] ?? 0) + 1;

            // Age
            $age = $user['dob']['age'] ?? 0;
            $totalAge += $age;

            if ($age <= 25) {
                $stats['by_age_group']['18-25']++;
            } elseif ($age <= 35) {
                $stats['by_age_group']['26-35']++;
            } elseif ($age <= 45) {
                $stats['by_age_group']['36-45']++;
            } elseif ($age <= 55) {
                $stats['by_age_group']['46-55']++;
            } else {
                $stats['by_age_group']['56+']++;
            }
        }

        $stats['average_age'] = count($users) > 0 ? (float) ($totalAge / count($users)) : 0.0;

        return $stats;
    }

    /**
     * Filter users by criteria.
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
     * Build paginated responses.
     * Yields PaginatedUsersShape for each page.
     */
    private function buildPaginatedResponses(array $users, int $perPage): \Generator
    {
        $total = count($users);
        $pages = (int) ceil($total / $perPage);

        for ($page = 0; $page < $pages; $page++) {
            $offset = $page * $perPage;
            $pageUsers = array_slice($users, $offset, $perPage);

            yield $this->buildPaginatedResponse($pageUsers, $page + 1, $perPage, $total, $pages, $offset);
        }
    }

    /**
     * Build a single paginated response.
     */
    private function buildPaginatedResponse(
        array $pageUsers,
        int $currentPage,
        int $perPage,
        int $total,
        int $lastPage,
        int $offset
    ): PaginatedUsers {
        $data = [];
        foreach ($pageUsers as $user) {
            $data[] = $this->formatUserResponse($user);
        }

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }
}
