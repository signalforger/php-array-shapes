<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Benchmark comparing three validation approaches:
 * 1. No validation (plain arrays)
 * 2. Userland validation (DTO classes)
 * 3. Native validation (array shapes)
 */

// Native array shapes (prefixed to avoid conflicts)
shape VPersonShape = array{
    id: string,
    name: string,
    email: string,
    age: int,
    active: bool
};

shape VAddressShape = array{
    street: string,
    city: string,
    country: string,
    postal_code: string
};

shape VPersonWithAddressShape = array{
    id: string,
    name: string,
    email: string,
    age: int,
    active: bool,
    address: VAddressShape
};

class ValidationComparisonCommand extends Command
{
    protected $signature = 'app:validation-comparison {--count=50000} {--iterations=10}';
    protected $description = 'Compare: No validation vs DTO classes vs Array shapes';

    public function handle(): int
    {
        $count = (int) $this->option('count');
        $iterations = (int) $this->option('iterations');

        $this->info("==============================================");
        $this->info("  Validation Approach Comparison");
        $this->info("==============================================");
        $this->info("PHP Version: " . PHP_VERSION);
        $this->info("Records: " . number_format($count));
        $this->info("Iterations: " . $iterations);
        $this->newLine();

        // Generate source data
        $this->info("Generating source data...");
        $sourceData = $this->generateSourceData($count);

        $results = [
            'plain_simple' => [],
            'dto_simple' => [],
            'shape_simple' => [],
            'plain_nested' => [],
            'dto_nested' => [],
            'shape_nested' => [],
            'plain_collection' => [],
            'dto_collection' => [],
            'typed_collection' => [],
        ];

        $this->newLine();
        $this->info("Running benchmarks...");

        for ($i = 0; $i < $iterations; $i++) {
            $this->line("  Iteration " . ($i + 1) . "/" . $iterations);

            // === SIMPLE STRUCTURE ===
            $start = hrtime(true);
            $this->processPlainSimple($sourceData);
            $results['plain_simple'][] = (hrtime(true) - $start) / 1e6;

            $start = hrtime(true);
            $this->processDtoSimple($sourceData);
            $results['dto_simple'][] = (hrtime(true) - $start) / 1e6;

            $start = hrtime(true);
            $this->processShapeSimple($sourceData);
            $results['shape_simple'][] = (hrtime(true) - $start) / 1e6;

            // === NESTED STRUCTURE ===
            $start = hrtime(true);
            $this->processPlainNested($sourceData);
            $results['plain_nested'][] = (hrtime(true) - $start) / 1e6;

            $start = hrtime(true);
            $this->processDtoNested($sourceData);
            $results['dto_nested'][] = (hrtime(true) - $start) / 1e6;

            $start = hrtime(true);
            $this->processShapeNested($sourceData);
            $results['shape_nested'][] = (hrtime(true) - $start) / 1e6;

            // === COLLECTIONS ===
            $start = hrtime(true);
            $this->processPlainCollection($sourceData);
            $results['plain_collection'][] = (hrtime(true) - $start) / 1e6;

            $start = hrtime(true);
            $this->processDtoCollection($sourceData);
            $results['dto_collection'][] = (hrtime(true) - $start) / 1e6;

            $start = hrtime(true);
            $this->processTypedCollection($sourceData);
            $results['typed_collection'][] = (hrtime(true) - $start) / 1e6;
        }

        // Calculate averages
        $averages = [];
        foreach ($results as $key => $times) {
            sort($times);
            if (count($times) >= 5) {
                array_shift($times);
                array_pop($times);
            }
            $averages[$key] = array_sum($times) / count($times);
        }

        // Display results
        $this->newLine();
        $this->info("==============================================");
        $this->info("  RESULTS: Simple Structure (5 fields)");
        $this->info("==============================================");
        $baseline = $averages['plain_simple'];
        $this->table(['Approach', 'Time (ms)', 'Overhead'], [
            ['No validation (plain array)', number_format($averages['plain_simple'], 2), 'baseline'],
            ['DTO class (userland)', number_format($averages['dto_simple'], 2), $this->calcOverhead($averages['dto_simple'], $baseline)],
            ['Array shape (native)', number_format($averages['shape_simple'], 2), $this->calcOverhead($averages['shape_simple'], $baseline)],
        ]);

        $this->info("==============================================");
        $this->info("  RESULTS: Nested Structure (person + address)");
        $this->info("==============================================");
        $baseline = $averages['plain_nested'];
        $this->table(['Approach', 'Time (ms)', 'Overhead'], [
            ['No validation (plain array)', number_format($averages['plain_nested'], 2), 'baseline'],
            ['DTO classes (userland)', number_format($averages['dto_nested'], 2), $this->calcOverhead($averages['dto_nested'], $baseline)],
            ['Array shapes (native)', number_format($averages['shape_nested'], 2), $this->calcOverhead($averages['shape_nested'], $baseline)],
        ]);

        $this->info("==============================================");
        $this->info("  RESULTS: Collections");
        $this->info("==============================================");
        $baseline = $averages['plain_collection'];
        $this->table(['Approach', 'Time (ms)', 'Overhead'], [
            ['No validation (plain array)', number_format($averages['plain_collection'], 2), 'baseline'],
            ['DTO objects (array of objects)', number_format($averages['dto_collection'], 2), $this->calcOverhead($averages['dto_collection'], $baseline)],
            ['Typed array (array<Shape>)', number_format($averages['typed_collection'], 2), $this->calcOverhead($averages['typed_collection'], $baseline)],
        ]);

        $this->newLine();
        $this->line("Memory peak: " . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB");

        // JSON
        $this->newLine();
        $this->line("JSON:");
        $this->line(json_encode([
            'count' => $count,
            'iterations' => $iterations,
            'results_ms' => $averages,
            'memory_mb' => memory_get_peak_usage(true) / 1024 / 1024,
        ], JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function calcOverhead(float $value, float $baseline): string
    {
        $overhead = (($value - $baseline) / $baseline) * 100;
        return sprintf('%+.1f%%', $overhead);
    }

    private function generateSourceData(int $count): array
    {
        $data = [];
        for ($i = 0; $i < $count; $i++) {
            $data[] = [
                'id' => 'person_' . $i,
                'name' => 'Person ' . $i,
                'email' => 'person' . $i . '@example.com',
                'age' => 20 + ($i % 50),
                'active' => $i % 2 === 0,
                'address' => [
                    'street' => $i . ' Main Street',
                    'city' => 'City ' . ($i % 100),
                    'country' => 'Country ' . ($i % 20),
                    'postal_code' => str_pad((string)($i % 99999), 5, '0', STR_PAD_LEFT),
                ],
            ];
        }
        return $data;
    }

    // === NO VALIDATION ===

    private function processPlainSimple(array $sourceData): array
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

    private function processPlainNested(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'email' => $item['email'],
                'age' => $item['age'],
                'active' => $item['active'],
                'address' => [
                    'street' => $item['address']['street'],
                    'city' => $item['address']['city'],
                    'country' => $item['address']['country'],
                    'postal_code' => $item['address']['postal_code'],
                ],
            ];
        }
        return $result;
    }

    private function processPlainCollection(array $sourceData): array
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

    // === DTO CLASSES ===

    private function processDtoSimple(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = new VPersonDto(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
            );
        }
        return $result;
    }

    private function processDtoNested(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = new VPersonWithAddressDto(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
                new VAddressDto(
                    $item['address']['street'],
                    $item['address']['city'],
                    $item['address']['country'],
                    $item['address']['postal_code'],
                ),
            );
        }
        return $result;
    }

    private function processDtoCollection(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = new VPersonDto(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
            );
        }
        return $result;
    }

    // === ARRAY SHAPES ===

    private function processShapeSimple(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = $this->createPersonShape($item);
        }
        return $result;
    }

    private function createPersonShape(array $item): VPersonShape
    {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'email' => $item['email'],
            'age' => $item['age'],
            'active' => $item['active'],
        ];
    }

    private function processShapeNested(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = $this->createPersonWithAddressShape($item);
        }
        return $result;
    }

    private function createPersonWithAddressShape(array $item): VPersonWithAddressShape
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

    private function createAddressShape(array $addr): VAddressShape
    {
        return [
            'street' => $addr['street'],
            'city' => $addr['city'],
            'country' => $addr['country'],
            'postal_code' => $addr['postal_code'],
        ];
    }

    private function processTypedCollection(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = $this->createPersonShape($item);
        }
        return $this->returnTypedCollection($result);
    }

    private function returnTypedCollection(array $items): array<VPersonShape>
    {
        return $items;
    }
}

// DTO Classes
readonly class VPersonDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public int $age,
        public bool $active,
    ) {}
}

readonly class VAddressDto
{
    public function __construct(
        public string $street,
        public string $city,
        public string $country,
        public string $postalCode,
    ) {}
}

readonly class VPersonWithAddressDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public int $age,
        public bool $active,
        public VAddressDto $address,
    ) {}
}
