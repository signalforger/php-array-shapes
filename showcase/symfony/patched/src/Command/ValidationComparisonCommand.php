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

#[AsCommand(
    name: 'app:validation-comparison',
    description: 'Compare: No validation vs DTO classes vs Array shapes',
)]
class ValidationComparisonCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Number of records', 50000);
        $this->addOption('iterations', 'i', InputOption::VALUE_OPTIONAL, 'Iterations', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');
        $iterations = (int) $input->getOption('iterations');

        $io->title('Validation Approach Comparison');
        $io->text([
            'PHP Version: ' . PHP_VERSION,
            'Records: ' . number_format($count),
            'Iterations: ' . $iterations,
        ]);

        // Generate source data
        $io->section('Generating source data...');
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

        $io->section('Running benchmarks...');
        $io->progressStart($iterations * 9);

        for ($i = 0; $i < $iterations; $i++) {
            // === SIMPLE STRUCTURE (5 fields) ===

            // Plain array - no validation
            $start = hrtime(true);
            $result = $this->processPlainSimple($sourceData);
            $results['plain_simple'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // DTO class - userland validation
            $start = hrtime(true);
            $result = $this->processDtoSimple($sourceData);
            $results['dto_simple'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // Array shape - native validation
            $start = hrtime(true);
            $result = $this->processShapeSimple($sourceData);
            $results['shape_simple'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // === NESTED STRUCTURE (person + address) ===

            // Plain array - no validation
            $start = hrtime(true);
            $result = $this->processPlainNested($sourceData);
            $results['plain_nested'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // DTO class - userland validation
            $start = hrtime(true);
            $result = $this->processDtoNested($sourceData);
            $results['dto_nested'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // Array shape - native validation
            $start = hrtime(true);
            $result = $this->processShapeNested($sourceData);
            $results['shape_nested'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // === TYPED COLLECTIONS ===

            // Plain array of arrays - no validation
            $start = hrtime(true);
            $result = $this->processPlainCollection($sourceData);
            $results['plain_collection'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // Array of DTO objects
            $start = hrtime(true);
            $result = $this->processDtoCollection($sourceData);
            $results['dto_collection'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();

            // Typed array - array<PersonShape>
            $start = hrtime(true);
            $result = $this->processTypedCollection($sourceData);
            $results['typed_collection'][] = (hrtime(true) - $start) / 1e6;
            $io->progressAdvance();
        }

        $io->progressFinish();

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
        $io->title('RESULTS: Simple Structure (5 fields)');
        $baseline = $averages['plain_simple'];
        $this->renderTable($output, [
            ['No validation (plain array)', $averages['plain_simple'], 'baseline'],
            ['DTO class (userland)', $averages['dto_simple'], $this->calcOverhead($averages['dto_simple'], $baseline)],
            ['Array shape (native)', $averages['shape_simple'], $this->calcOverhead($averages['shape_simple'], $baseline)],
        ]);

        $io->title('RESULTS: Nested Structure (person + address)');
        $baseline = $averages['plain_nested'];
        $this->renderTable($output, [
            ['No validation (plain array)', $averages['plain_nested'], 'baseline'],
            ['DTO classes (userland)', $averages['dto_nested'], $this->calcOverhead($averages['dto_nested'], $baseline)],
            ['Array shapes (native)', $averages['shape_nested'], $this->calcOverhead($averages['shape_nested'], $baseline)],
        ]);

        $io->title('RESULTS: Collections');
        $baseline = $averages['plain_collection'];
        $this->renderTable($output, [
            ['No validation (plain array)', $averages['plain_collection'], 'baseline'],
            ['DTO objects (array of objects)', $averages['dto_collection'], $this->calcOverhead($averages['dto_collection'], $baseline)],
            ['Typed array (array<Shape>)', $averages['typed_collection'], $this->calcOverhead($averages['typed_collection'], $baseline)],
        ]);

        // Summary
        $io->title('SUMMARY');
        $io->text([
            'Simple structure:',
            '  - DTO overhead: ' . $this->calcOverhead($averages['dto_simple'], $averages['plain_simple']),
            '  - Shape overhead: ' . $this->calcOverhead($averages['shape_simple'], $averages['plain_simple']),
            '',
            'Nested structure:',
            '  - DTO overhead: ' . $this->calcOverhead($averages['dto_nested'], $averages['plain_nested']),
            '  - Shape overhead: ' . $this->calcOverhead($averages['shape_nested'], $averages['plain_nested']),
            '',
            'Collections:',
            '  - DTO overhead: ' . $this->calcOverhead($averages['dto_collection'], $averages['plain_collection']),
            '  - Typed array overhead: ' . $this->calcOverhead($averages['typed_collection'], $averages['plain_collection']),
        ]);

        $io->newLine();
        $io->text('Memory peak: ' . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB');

        // JSON output
        $io->newLine();
        $io->text('JSON:');
        $io->text(json_encode([
            'count' => $count,
            'iterations' => $iterations,
            'results_ms' => $averages,
            'memory_mb' => memory_get_peak_usage(true) / 1024 / 1024,
        ], JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function renderTable($output, array $rows): void
    {
        $table = new Table($output);
        $table->setHeaders(['Approach', 'Time (ms)', 'Overhead']);
        foreach ($rows as $row) {
            $table->addRow([
                $row[0],
                number_format($row[1], 2),
                $row[2],
            ]);
        }
        $table->render();
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

    // === NO VALIDATION (Plain Arrays) ===

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

    // === DTO CLASSES (Userland Validation) ===

    private function processDtoSimple(array $sourceData): array
    {
        $result = [];
        foreach ($sourceData as $item) {
            $result[] = new PersonDto(
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
            $result[] = new PersonWithAddressDto(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
                new AddressDto(
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
            $result[] = new PersonDto(
                $item['id'],
                $item['name'],
                $item['email'],
                $item['age'],
                $item['active'],
            );
        }
        return $result;
    }

    // === ARRAY SHAPES (Native Validation) ===

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

    /**
     * @return array<VPersonShape>
     */
    private function returnTypedCollection(array $items): array<VPersonShape>
    {
        return $items;
    }
}

// DTO Classes for userland validation
readonly class PersonDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public int $age,
        public bool $active,
    ) {}
}

readonly class AddressDto
{
    public function __construct(
        public string $street,
        public string $city,
        public string $country,
        public string $postalCode,
    ) {}
}

readonly class PersonWithAddressDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public int $age,
        public bool $active,
        public AddressDto $address,
    ) {}
}
