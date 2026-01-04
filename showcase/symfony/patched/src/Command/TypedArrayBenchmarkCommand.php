<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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

#[AsCommand(
    name: 'app:typed-array-benchmark',
    description: 'Benchmark typed array overhead: plain vs shapes vs object arrays',
)]
class TypedArrayBenchmarkCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Number of items to process', 10000);
        $this->addOption('iterations', 'i', InputOption::VALUE_OPTIONAL, 'Number of iterations', 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');
        $iterations = (int) $input->getOption('iterations');

        $io->title('Typed Array Overhead Benchmark');
        $io->text([
            'Comparing: Plain arrays vs Array shapes vs Object arrays',
            'PHP Version: ' . PHP_VERSION,
            'Items per iteration: ' . number_format($count),
            'Iterations: ' . $iterations,
        ]);

        // Generate source data once (not part of benchmark)
        $io->section('Generating source data...');
        $sourceData = $this->generateSourceData($count);
        $io->text('Generated ' . count($sourceData) . ' source records');

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
            $preCreatedObjects[] = new User(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
            );
        }
        $io->text('Pre-created ' . count($preCreatedObjects) . ' User objects for passthrough test');

        // Pre-create wrapper collection for passthrough test
        $preCreatedCollection = new UserCollection($preCreatedObjects);
        $io->text('Pre-created UserCollection wrapper for passthrough test');

        $io->section('Running benchmarks...');
        $io->progressStart($iterations * 8);

        for ($i = 0; $i < $iterations; $i++) {
            // Benchmark 1: Plain arrays (no type checking) - BASELINE
            $start = hrtime(true);
            $plainResult = $this->processPlainArrays($sourceData);
            $results['plain'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // Benchmark 2: Array shapes (simple)
            $start = hrtime(true);
            $shapeResult = $this->processWithShapes($sourceData);
            $results['shape'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // Benchmark 3: Array shapes (with nested shapes)
            $start = hrtime(true);
            $nestedResult = $this->processWithNestedShapes($sourceData);
            $results['shape_nested'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // Benchmark 4: Individual objects (not in typed array)
            $start = hrtime(true);
            $objectResult = $this->processWithObjects($sourceData);
            $results['object'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // Benchmark 5: Typed object array (array<User>) - THE CONCERN
            $start = hrtime(true);
            $typedObjectArrayResult = $this->processWithTypedObjectArray($sourceData);
            $results['object_array_typed'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // Benchmark 6: Typed object array passthrough (ORM scenario)
            // Objects already exist, just validate as array<User>
            $start = hrtime(true);
            $passthroughResult = $this->returnTypedUserArray($preCreatedObjects);
            $results['object_array_passthrough'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // Benchmark 7: Wrapper collection class (create + wrap)
            $start = hrtime(true);
            $wrapperResult = $this->processWithWrapperCollection($sourceData);
            $results['wrapper_collection'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // Benchmark 8: Wrapper collection passthrough (already wrapped)
            $start = hrtime(true);
            $wrapperPassthroughResult = $this->returnUserCollection($preCreatedCollection);
            $results['wrapper_passthrough'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();
        }

        $io->progressFinish();

        // Calculate averages and overhead
        $averages = [];
        foreach ($results as $key => $times) {
            sort($times);
            // Remove outliers (highest and lowest if enough iterations)
            if (count($times) >= 5) {
                array_shift($times);
                array_pop($times);
            }
            $averages[$key] = array_sum($times) / count($times);
        }

        $baseline = $averages['plain'];

        // Display results
        $io->title('RESULTS');

        $tableData = [];
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

        foreach ($averages as $key => $avg) {
            $overhead = (($avg - $baseline) / $baseline) * 100;
            $tableData[] = [
                $labels[$key],
                number_format($avg, 2) . ' ms',
                $key === 'plain' ? 'baseline' : sprintf('%+.1f%%', $overhead),
            ];
        }

        $table = new Table($output);
        $table->setHeaders(['Approach', 'Avg Time', 'Overhead']);
        $table->setRows($tableData);
        $table->render();

        // Memory info
        $io->newLine();
        $io->text('Memory peak: ' . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB');

        // JSON output for programmatic comparison
        $io->newLine();
        $io->text('JSON Output:');
        $io->text(json_encode([
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

    /**
     * Baseline: Plain arrays with no type validation
     */
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

    /**
     * Simple array shapes (flat structure)
     */
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

    /**
     * Nested array shapes
     */
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

    /**
     * Individual objects (not in a typed array)
     */
    private function processWithObjects(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = new User(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
            );
        }
        return $result;
    }

    /**
     * Typed object array - array<User>
     * This is the case the user is concerned about (229% overhead claim)
     */
    private function processWithTypedObjectArray(array $sourceData): array
    {
        $objects = [];
        foreach ($sourceData as $item) {
            $objects[] = new User(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
            );
        }
        // Now return it through a typed array return
        return $this->returnTypedUserArray($objects);
    }

    /**
     * Returns array<User> - triggers typed array validation on each element
     * @return array<User>
     */
    private function returnTypedUserArray(array $users): array<User>
    {
        return $users;
    }

    /**
     * Wrapper collection - creates objects and wraps in collection class
     */
    private function processWithWrapperCollection(array $sourceData): UserCollection
    {
        $objects = [];
        foreach ($sourceData as $item) {
            $objects[] = new User(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
            );
        }
        return new UserCollection($objects);
    }

    /**
     * Returns UserCollection - wrapper class passthrough
     */
    private function returnUserCollection(UserCollection $collection): UserCollection
    {
        return $collection;
    }
}

/**
 * Simple User class for object array benchmarks
 */
class User
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
class UserCollection
{
    /** @var array<User> */
    private array $users;

    public function __construct(array $users)
    {
        // Validate each element is a User
        foreach ($users as $user) {
            if (!$user instanceof User) {
                throw new \InvalidArgumentException('Expected User instance');
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
