<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Benchmark command specifically for comparing typed array overhead.
 *
 * Compares three approaches:
 * 1. Plain arrays (no validation) - baseline
 * 2. Array shapes (native type checking)
 * 3. Object arrays (array<ClassName>) - simulating ORM results
 */

// Simple shape for user data
shape UserShape = array{
    id: string,
    name: string,
    email: string,
    age: int,
    active: bool
};

// Shape with nested structure
shape AddressShape = array{
    street: string,
    city: string,
    country: string
};

shape UserWithAddressShape = array{
    id: string,
    name: string,
    email: string,
    age: int,
    active: bool,
    address: AddressShape
};

class TypedArrayBenchmarkCommand extends Command
{
    protected $signature = 'app:typed-array-benchmark {--count=10000 : Number of items} {--iterations=5 : Number of iterations}';
    protected $description = 'Benchmark typed array overhead: plain vs shapes vs object arrays';

    public function handle(): int
    {
        $count = (int) $this->option('count');
        $iterations = (int) $this->option('iterations');

        $this->info("==============================================");
        $this->info("  Typed Array Overhead Benchmark");
        $this->info("==============================================");
        $this->info("Comparing: Plain arrays vs Array shapes vs Object arrays");
        $this->info("PHP Version: " . PHP_VERSION);
        $this->info("Items per iteration: " . number_format($count));
        $this->info("Iterations: " . $iterations);
        $this->newLine();

        // Generate source data once (not part of benchmark)
        $this->info("Generating source data...");
        $sourceData = $this->generateSourceData($count);
        $this->line("Generated " . count($sourceData) . " source records");

        $results = [
            'plain' => [],
            'shape' => [],
            'shape_nested' => [],
            'object' => [],
            'object_array_typed' => [],
            'object_array_passthrough' => [],
            'wrapper_collection' => [],
            'wrapper_passthrough' => [],
        ];

        // Pre-create objects for passthrough test (simulates ORM scenario)
        $preCreatedObjects = [];
        foreach ($sourceData as $item) {
            $preCreatedObjects[] = new BenchmarkUser(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
            );
        }
        $this->line("Pre-created " . count($preCreatedObjects) . " User objects for passthrough test");

        // Pre-create wrapper collection for passthrough test
        $preCreatedCollection = new BenchmarkUserCollection($preCreatedObjects);
        $this->line("Pre-created BenchmarkUserCollection wrapper for passthrough test");

        $this->newLine();
        $this->info("Running benchmarks...");

        for ($i = 0; $i < $iterations; $i++) {
            $this->line("  Iteration " . ($i + 1) . "/" . $iterations);

            // Benchmark 1: Plain arrays (no type checking) - BASELINE
            $start = hrtime(true);
            $plainResult = $this->processPlainArrays($sourceData);
            $results['plain'][] = (hrtime(true) - $start) / 1e6;

            // Benchmark 2: Array shapes (simple)
            $start = hrtime(true);
            $shapeResult = $this->processWithShapes($sourceData);
            $results['shape'][] = (hrtime(true) - $start) / 1e6;

            // Benchmark 3: Array shapes (with nested shapes)
            $start = hrtime(true);
            $nestedResult = $this->processWithNestedShapes($sourceData);
            $results['shape_nested'][] = (hrtime(true) - $start) / 1e6;

            // Benchmark 4: Individual objects (not in typed array)
            $start = hrtime(true);
            $objectResult = $this->processWithObjects($sourceData);
            $results['object'][] = (hrtime(true) - $start) / 1e6;

            // Benchmark 5: Typed object array (array<User>) - THE CONCERN
            $start = hrtime(true);
            $typedObjectArrayResult = $this->processWithTypedObjectArray($sourceData);
            $results['object_array_typed'][] = (hrtime(true) - $start) / 1e6;

            // Benchmark 6: Typed object array passthrough (ORM scenario)
            $start = hrtime(true);
            $passthroughResult = $this->returnTypedUserArray($preCreatedObjects);
            $results['object_array_passthrough'][] = (hrtime(true) - $start) / 1e6;

            // Benchmark 7: Wrapper collection class (create + wrap)
            $start = hrtime(true);
            $wrapperResult = $this->processWithWrapperCollection($sourceData);
            $results['wrapper_collection'][] = (hrtime(true) - $start) / 1e6;

            // Benchmark 8: Wrapper collection passthrough (already wrapped)
            $start = hrtime(true);
            $wrapperPassthroughResult = $this->returnUserCollection($preCreatedCollection);
            $results['wrapper_passthrough'][] = (hrtime(true) - $start) / 1e6;
        }

        // Calculate averages and overhead
        $averages = [];
        foreach ($results as $key => $times) {
            sort($times);
            if (count($times) >= 5) {
                array_shift($times);
                array_pop($times);
            }
            $averages[$key] = array_sum($times) / count($times);
        }

        $baseline = $averages['plain'];

        // Display results
        $this->newLine();
        $this->info("==============================================");
        $this->info("  RESULTS");
        $this->info("==============================================");

        $labels = [
            'plain' => 'Plain arrays (no validation)',
            'shape' => 'Array shapes (simple)',
            'shape_nested' => 'Array shapes (nested)',
            'object' => 'Objects (individual creation)',
            'object_array_typed' => 'Typed array (create + validate)',
            'object_array_passthrough' => 'Typed array (validate only)',
            'wrapper_collection' => 'Wrapper class (create + wrap)',
            'wrapper_passthrough' => 'Wrapper class (passthrough)',
        ];

        $tableData = [];
        foreach ($averages as $key => $avg) {
            $overhead = (($avg - $baseline) / $baseline) * 100;
            $tableData[] = [
                $labels[$key],
                number_format($avg, 2) . ' ms',
                $key === 'plain' ? 'baseline' : sprintf('%+.1f%%', $overhead),
            ];
        }

        $this->table(['Approach', 'Avg Time', 'Overhead'], $tableData);

        $this->newLine();
        $this->line("Memory peak: " . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB");

        // JSON output
        $this->newLine();
        $this->line("JSON Output:");
        $this->line(json_encode([
            'count' => $count,
            'iterations' => $iterations,
            'results_ms' => $averages,
            'overhead_percent' => array_map(
                fn($avg) => round((($avg - $baseline) / $baseline) * 100, 2),
                $averages
            ),
            'memory_mb' => memory_get_peak_usage(true) / 1024 / 1024,
        ], JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function generateSourceData(int $count): array
    {
        $data = [];
        for ($i = 0; $i < $count; $i++) {
            $data[] = [
                'id' => 'user_' . $i,
                'name' => 'User ' . $i,
                'email' => 'user' . $i . '@example.com',
                'age' => 20 + ($i % 50),
                'active' => $i % 2 === 0,
                'address' => [
                    'street' => $i . ' Main Street',
                    'city' => 'City ' . ($i % 100),
                    'country' => 'Country ' . ($i % 20),
                ],
            ];
        }
        return $data;
    }

    private function processPlainArrays(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'email' => $item['email'],
                'age' => $item['age'],
                'active' => $item['active'],
            ];
        }
        return $result;
    }

    private function processWithShapes(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = $this->createUserShape($item);
        }
        return $result;
    }

    private function createUserShape(array $item): UserShape
    {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'email' => $item['email'],
            'age' => $item['age'],
            'active' => $item['active'],
        ];
    }

    private function processWithNestedShapes(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = $this->createUserWithAddressShape($item);
        }
        return $result;
    }

    private function createUserWithAddressShape(array $item): UserWithAddressShape
    {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'email' => $item['email'],
            'age' => $item['age'],
            'active' => $item['active'],
            'address' => $this->createAddressShape($item['address']),
        ];
    }

    private function createAddressShape(array $addr): AddressShape
    {
        return [
            'street' => $addr['street'],
            'city' => $addr['city'],
            'country' => $addr['country'],
        ];
    }

    private function processWithObjects(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = new BenchmarkUser(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
            );
        }
        return $result;
    }

    private function processWithTypedObjectArray(array $sourceData): array
    {
        $objects = [];
        foreach ($sourceData as $item) {
            $objects[] = new BenchmarkUser(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
            );
        }
        return $this->returnTypedUserArray($objects);
    }

    /**
     * @return array<BenchmarkUser>
     */
    private function returnTypedUserArray(array $users): array<BenchmarkUser>
    {
        return $users;
    }

    /**
     * Wrapper collection - creates objects and wraps in collection class
     */
    private function processWithWrapperCollection(array $sourceData): BenchmarkUserCollection
    {
        $objects = [];
        foreach ($sourceData as $item) {
            $objects[] = new BenchmarkUser(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
            );
        }
        return new BenchmarkUserCollection($objects);
    }

    /**
     * Returns BenchmarkUserCollection - wrapper class passthrough
     */
    private function returnUserCollection(BenchmarkUserCollection $collection): BenchmarkUserCollection
    {
        return $collection;
    }
}

class BenchmarkUser
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly int $age,
        public readonly bool $active,
    ) {}
}

/**
 * Wrapper collection class - traditional approach to typed collections
 * This is what developers often create to ensure type safety without native typed arrays
 */
class BenchmarkUserCollection
{
    /** @var array<BenchmarkUser> */
    private array $users;

    public function __construct(array $users)
    {
        // Validate each element is a BenchmarkUser
        foreach ($users as $user) {
            if (!$user instanceof BenchmarkUser) {
                throw new \InvalidArgumentException('Expected BenchmarkUser instance');
            }
        }
        $this->users = $users;
    }

    public function getUsers(): array
    {
        return $this->users;
    }

    public function count(): int
    {
        return count($this->users);
    }
}
