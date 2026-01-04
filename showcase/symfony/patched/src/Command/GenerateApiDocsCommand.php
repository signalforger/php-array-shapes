<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Generate OpenAPI documentation from Action classes.
 *
 * This command uses PHP reflection to inspect Action classes
 * and their typed return values (array shapes) to generate
 * API documentation automatically.
 */
#[AsCommand(
    name: 'app:generate-api-docs',
    description: 'Generate OpenAPI documentation from Action classes using reflection on typed arrays and array shapes',
)]
class GenerateApiDocsCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path', 'public/openapi.json');
        $this->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (json or yaml)', 'json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputPath = $input->getOption('output');
        $format = $input->getOption('format');

        $io->title('Generating OpenAPI Documentation');
        $io->text('Scanning Action classes for typed return values...');

        $actions = $this->discoverActions();
        $io->text(sprintf('Found %d action(s)', count($actions)));

        $openapi = $this->buildOpenApiSpec($actions, $io);

        // Output
        if ($format === 'yaml') {
            $content = $this->toYaml($openapi);
        } else {
            $content = json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        file_put_contents($outputPath, $content);
        $io->success(sprintf('OpenAPI documentation generated: %s', $outputPath));

        // Also output to console
        $io->section('Generated Schema');
        $io->text($content);

        return Command::SUCCESS;
    }

    private function discoverActions(): array
    {
        $actions = [];
        $actionDir = __DIR__ . '/../Action';

        if (!is_dir($actionDir)) {
            return $actions;
        }

        $finder = new Finder();
        $finder->files()->in($actionDir)->name('*Action.php')->depth(0);

        foreach ($finder as $file) {
            $className = 'App\\Action\\' . $file->getBasename('.php');
            if (class_exists($className)) {
                $actions[] = $className;
            }
        }

        return $actions;
    }

    private function buildOpenApiSpec(array $actions, SymfonyStyle $io): array
    {
        $openapi = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Job Board API',
                'description' => 'API for job listings aggregated from multiple sources. Generated from PHP typed arrays and array shapes.',
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => '/api', 'description' => 'API Server'],
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
            ],
        ];

        foreach ($actions as $actionClass) {
            $io->text(sprintf('  Processing: %s', $actionClass));
            $this->processAction($actionClass, $openapi, $io);
        }

        return $openapi;
    }

    private function processAction(string $actionClass, array &$openapi, SymfonyStyle $io): void
    {
        $reflection = new \ReflectionClass($actionClass);

        // Get @api annotation from class docblock
        $docComment = $reflection->getDocComment();
        $apiInfo = $this->parseApiAnnotation($docComment);

        if ($apiInfo === null) {
            $io->text(sprintf('    Skipping (no @api annotation)'));
            return;
        }

        // Get result() method return type
        if (!$reflection->hasMethod('result')) {
            return;
        }

        $resultMethod = $reflection->getMethod('result');
        $returnType = $resultMethod->getReturnType();

        if ($returnType === null) {
            $io->text(sprintf('    Warning: No return type on result() method'));
            return;
        }

        // Handle both regular types and array shape types
        // ReflectionArrayShapeType doesn't have getName(), use __toString() instead
        $typeName = $returnType instanceof \ReflectionNamedType
            ? $returnType->getName()
            : (string) $returnType;
        $io->text(sprintf('    Return type: %s', $typeName));

        // Build schema from return type
        $schema = $this->buildSchemaFromType($typeName, $openapi);

        // Add path
        $path = $apiInfo['path'];
        $method = strtolower($apiInfo['method']);

        if (!isset($openapi['paths'][$path])) {
            $openapi['paths'][$path] = [];
        }

        $openapi['paths'][$path][$method] = [
            'summary' => $this->getActionSummary($reflection),
            'operationId' => $this->getOperationId($actionClass),
            'parameters' => $this->getParameters($actionClass),
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => $schema,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function parseApiAnnotation(?string $docComment): ?array
    {
        if ($docComment === null || $docComment === false) {
            return null;
        }

        if (preg_match('/@api\s+(GET|POST|PUT|DELETE|PATCH)\s+(\S+)/', $docComment, $matches)) {
            return [
                'method' => $matches[1],
                'path' => $matches[2],
            ];
        }

        return null;
    }

    private function buildSchemaFromType(string $typeName, array &$openapi): array
    {
        // Check if this is an inline array shape (from __toString())
        if (str_starts_with($typeName, 'array{')) {
            // Parse the inline array shape directly
            $schema = $this->parseArrayShapeToJsonSchema($typeName, $openapi);
            if ($schema !== null) {
                return $schema;
            }
        }

        // Check if this is a shape alias (will be in App\Action\Response namespace)
        $shortName = basename(str_replace('\\', '/', $typeName));

        // For shape types, we need to introspect them
        // In patched PHP, we can use reflection to get shape structure
        $schema = $this->introspectShapeType($typeName);

        if ($schema !== null) {
            // Add to components/schemas
            $openapi['components']['schemas'][$shortName] = $schema;

            return ['$ref' => '#/components/schemas/' . $shortName];
        }

        // Fallback for unknown types
        return ['type' => 'object'];
    }

    private function parseArrayShapeToJsonSchema(string $shapeString, array &$openapi): ?array
    {
        // Parse array{key: type, ...} syntax into JSON Schema
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        // Remove outer array{ and }
        $inner = substr($shapeString, 6, -1);

        // Parse key-value pairs
        $properties = $this->parseShapeProperties($inner);

        foreach ($properties as $name => $type) {
            $isOptional = str_starts_with($name, '?');
            $cleanName = ltrim($name, '?');

            if (!$isOptional && !str_starts_with($type, '?')) {
                $schema['required'][] = $cleanName;
            }

            $schema['properties'][$cleanName] = $this->typeStringToJsonSchema($type, $openapi);
        }

        if (empty($schema['required'])) {
            unset($schema['required']);
        }

        return $schema;
    }

    private function parseShapeProperties(string $inner): array
    {
        $properties = [];
        $depth = 0;
        $current = '';
        $key = null;

        for ($i = 0; $i < strlen($inner); $i++) {
            $char = $inner[$i];

            if ($char === '{' || $char === '<') {
                $depth++;
                $current .= $char;
            } elseif ($char === '}' || $char === '>') {
                $depth--;
                $current .= $char;
            } elseif ($char === ':' && $depth === 0 && $key === null) {
                $key = trim($current);
                $current = '';
            } elseif ($char === ',' && $depth === 0) {
                if ($key !== null) {
                    $properties[$key] = trim($current);
                }
                $key = null;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        // Last property
        if ($key !== null && trim($current) !== '') {
            $properties[$key] = trim($current);
        }

        return $properties;
    }

    private function typeStringToJsonSchema(string $typeString, array &$openapi): array
    {
        $isNullable = str_starts_with($typeString, '?');
        $type = ltrim($typeString, '?');

        $schema = match (true) {
            $type === 'int' => ['type' => 'integer'],
            $type === 'string' => ['type' => 'string'],
            $type === 'bool' => ['type' => 'boolean'],
            $type === 'float' => ['type' => 'number'],
            str_starts_with($type, 'array<string>') => ['type' => 'array', 'items' => ['type' => 'string']],
            str_starts_with($type, 'array<int>') => ['type' => 'array', 'items' => ['type' => 'integer']],
            str_starts_with($type, 'array<string, int>') => ['type' => 'object', 'additionalProperties' => ['type' => 'integer']],
            str_starts_with($type, 'array<array{') => $this->parseTypedArray($type, $openapi),
            str_starts_with($type, 'array{') => $this->parseArrayShapeToJsonSchema($type, $openapi),
            default => ['type' => 'string'],
        };

        if ($isNullable) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    private function parseTypedArray(string $type, array &$openapi): array
    {
        // Extract the inner shape from array<array{...}>
        if (preg_match('/^array<(array\{.+\})>$/', $type, $matches)) {
            $innerSchema = $this->parseArrayShapeToJsonSchema($matches[1], $openapi);
            return [
                'type' => 'array',
                'items' => $innerSchema ?? ['type' => 'object'],
            ];
        }
        return ['type' => 'array', 'items' => ['type' => 'object']];
    }

    private function introspectShapeType(string $typeName): ?array
    {
        // Use PHP's ReflectionShapeType to introspect the shape
        // This is only available in patched PHP
        if (!function_exists('shape_get_definition')) {
            return $this->getHardcodedSchema($typeName);
        }

        try {
            $definition = shape_get_definition($typeName);
            return $this->convertShapeToJsonSchema($definition);
        } catch (\Throwable $e) {
            return $this->getHardcodedSchema($typeName);
        }
    }

    private function getHardcodedSchema(string $typeName): ?array
    {
        // Fallback schemas based on known shape names
        $schemas = [
            'App\Action\Response\JobListResponse' => [
                'type' => 'object',
                'required' => ['data', 'meta'],
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/JobResponse'],
                    ],
                    'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                ],
            ],
            'App\Action\Response\JobResponse' => [
                'type' => 'object',
                'required' => ['id', 'title', 'company_name', 'location', 'remote', 'job_type', 'salary', 'url', 'tags', 'source'],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'company_name' => ['type' => 'string'],
                    'company_logo' => ['type' => 'string', 'nullable' => true],
                    'location' => ['type' => 'string'],
                    'remote' => ['type' => 'boolean'],
                    'job_type' => ['type' => 'string', 'enum' => ['full-time', 'part-time', 'contract', 'internship']],
                    'salary' => ['$ref' => '#/components/schemas/SalaryRange'],
                    'url' => ['type' => 'string', 'format' => 'uri'],
                    'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'source' => ['type' => 'string'],
                    'posted_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
            'App\Action\Response\JobDetailResponse' => [
                'type' => 'object',
                'required' => ['id', 'title', 'company_name', 'location', 'remote', 'job_type', 'salary', 'url', 'tags', 'source', 'description'],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'company_name' => ['type' => 'string'],
                    'company_logo' => ['type' => 'string', 'nullable' => true],
                    'location' => ['type' => 'string'],
                    'remote' => ['type' => 'boolean'],
                    'job_type' => ['type' => 'string', 'enum' => ['full-time', 'part-time', 'contract', 'internship']],
                    'salary' => ['$ref' => '#/components/schemas/SalaryRange'],
                    'url' => ['type' => 'string', 'format' => 'uri'],
                    'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'source' => ['type' => 'string'],
                    'posted_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'description' => ['type' => 'string'],
                    'fetched_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
            'App\Action\Response\PaginationMeta' => [
                'type' => 'object',
                'required' => ['current_page', 'per_page', 'total', 'last_page'],
                'properties' => [
                    'current_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                ],
            ],
            'App\Action\Response\SalaryRange' => [
                'type' => 'object',
                'required' => ['formatted'],
                'properties' => [
                    'min' => ['type' => 'integer', 'nullable' => true],
                    'max' => ['type' => 'integer', 'nullable' => true],
                    'currency' => ['type' => 'string', 'nullable' => true],
                    'formatted' => ['type' => 'string'],
                ],
            ],
            'App\Action\Response\JobStatsResponse' => [
                'type' => 'object',
                'required' => ['total_jobs', 'by_source', 'by_type', 'remote_jobs', 'with_salary'],
                'properties' => [
                    'total_jobs' => ['type' => 'integer'],
                    'by_source' => [
                        'type' => 'object',
                        'additionalProperties' => ['type' => 'integer'],
                    ],
                    'by_type' => [
                        'type' => 'object',
                        'additionalProperties' => ['type' => 'integer'],
                    ],
                    'remote_jobs' => ['type' => 'integer'],
                    'with_salary' => ['type' => 'integer'],
                    'last_fetched_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
        ];

        return $schemas[$typeName] ?? null;
    }

    private function convertShapeToJsonSchema(array $definition): array
    {
        // Convert shape definition to JSON Schema
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        foreach ($definition as $key => $type) {
            $schema['properties'][$key] = $this->typeToJsonSchema($type);
        }

        return $schema;
    }

    private function typeToJsonSchema(mixed $type): array
    {
        if (is_string($type)) {
            return match ($type) {
                'int' => ['type' => 'integer'],
                'string' => ['type' => 'string'],
                'bool' => ['type' => 'boolean'],
                'float' => ['type' => 'number'],
                default => ['type' => 'string'],
            };
        }

        return ['type' => 'object'];
    }

    private function getActionSummary(\ReflectionClass $reflection): string
    {
        $docComment = $reflection->getDocComment();
        if ($docComment === false) {
            return $reflection->getShortName();
        }

        // Extract first line of docblock
        if (preg_match('/\/\*\*\s*\n\s*\*\s*(.+)/', $docComment, $matches)) {
            return trim($matches[1]);
        }

        return $reflection->getShortName();
    }

    private function getOperationId(string $actionClass): string
    {
        $shortName = basename(str_replace('\\', '/', $actionClass));
        return lcfirst(str_replace('Action', '', $shortName));
    }

    private function getParameters(string $actionClass): array
    {
        $params = [];

        // Check for ListJobsRequest
        if (str_contains($actionClass, 'ListJobs')) {
            $params = [
                ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Search query'],
                ['name' => 'remote', 'in' => 'query', 'schema' => ['type' => 'boolean'], 'description' => 'Filter remote jobs'],
                ['name' => 'job_type', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['full-time', 'part-time', 'contract', 'internship']], 'description' => 'Job type filter'],
                ['name' => 'location', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Location filter'],
                ['name' => 'source', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Source filter'],
                ['name' => 'min_salary', 'in' => 'query', 'schema' => ['type' => 'integer'], 'description' => 'Minimum salary'],
                ['name' => 'max_salary', 'in' => 'query', 'schema' => ['type' => 'integer'], 'description' => 'Maximum salary'],
                ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1], 'description' => 'Page number'],
                ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 100], 'description' => 'Items per page'],
            ];
        }

        // Check for GetJob (has path parameter)
        if (str_contains($actionClass, 'GetJob') && !str_contains($actionClass, 'Stats')) {
            $params = [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer'], 'description' => 'Job ID'],
            ];
        }

        return $params;
    }

    private function toYaml(array $data): string
    {
        // Simple YAML conversion
        return $this->arrayToYaml($data, 0);
    }

    private function arrayToYaml(mixed $data, int $indent): string
    {
        $yaml = '';
        $spaces = str_repeat('  ', $indent);

        if (is_array($data)) {
            $isAssoc = array_keys($data) !== range(0, count($data) - 1);

            foreach ($data as $key => $value) {
                if ($isAssoc) {
                    if (is_array($value)) {
                        $yaml .= $spaces . $key . ":\n" . $this->arrayToYaml($value, $indent + 1);
                    } else {
                        $yaml .= $spaces . $key . ': ' . $this->valueToYaml($value) . "\n";
                    }
                } else {
                    if (is_array($value)) {
                        $yaml .= $spaces . "-\n" . $this->arrayToYaml($value, $indent + 1);
                    } else {
                        $yaml .= $spaces . '- ' . $this->valueToYaml($value) . "\n";
                    }
                }
            }
        }

        return $yaml;
    }

    private function valueToYaml(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_string($value) && (str_contains($value, ':') || str_contains($value, '#') || $value === '')) {
            return '"' . addslashes($value) . '"';
        }
        return (string) $value;
    }
}
