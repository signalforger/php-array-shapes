<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Generate OpenAPI documentation from Action classes.
 *
 * This command uses PHP reflection to inspect Action classes
 * and their DTO return types to generate API documentation.
 *
 * In standard PHP (without array shapes), we rely on:
 * - Return type declarations on result() methods
 * - PHPDoc annotations for additional type information
 * - DTO class property types
 */
class GenerateApiDocsCommand extends Command
{
    protected $signature = 'app:generate-api-docs
        {--output=public/openapi.json : Output file path}
        {--format=json : Output format (json or yaml)}';

    protected $description = 'Generate OpenAPI documentation from Action classes using reflection on DTOs';

    public function handle(): int
    {
        $outputPath = $this->option('output');
        $format = $this->option('format');

        $this->info('Generating OpenAPI Documentation');
        $this->line('Scanning Action classes for DTO return types...');

        $actions = $this->discoverActions();
        $this->line(sprintf('Found %d action(s)', count($actions)));

        $openapi = $this->buildOpenApiSpec($actions);

        // Output
        if ($format === 'yaml') {
            $content = $this->toYaml($openapi);
        } else {
            $content = json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        File::put(base_path($outputPath), $content);
        $this->info(sprintf('OpenAPI documentation generated: %s', $outputPath));

        // Also output to console
        $this->newLine();
        $this->line('Generated Schema:');
        $this->line($content);

        return Command::SUCCESS;
    }

    private function discoverActions(): array
    {
        $actions = [];
        $actionDir = app_path('Action');

        if (!is_dir($actionDir)) {
            return $actions;
        }

        $files = File::files($actionDir);

        foreach ($files as $file) {
            if (str_ends_with($file->getFilename(), 'Action.php')) {
                $className = 'App\\Action\\' . $file->getBasename('.php');
                if (class_exists($className)) {
                    $actions[] = $className;
                }
            }
        }

        return $actions;
    }

    private function buildOpenApiSpec(array $actions): array
    {
        $openapi = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Job Board API',
                'description' => 'API for job listings aggregated from multiple sources. Generated from PHP DTOs using reflection.',
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
            $this->line(sprintf('  Processing: %s', $actionClass));
            $this->processAction($actionClass, $openapi);
        }

        return $openapi;
    }

    private function processAction(string $actionClass, array &$openapi): void
    {
        $reflection = new \ReflectionClass($actionClass);

        // Get @api annotation from class docblock
        $docComment = $reflection->getDocComment();
        $apiInfo = $this->parseApiAnnotation($docComment);

        if ($apiInfo === null) {
            $this->line(sprintf('    Skipping (no @api annotation)'));
            return;
        }

        // Get result() method return type
        if (!$reflection->hasMethod('result')) {
            return;
        }

        $resultMethod = $reflection->getMethod('result');
        $returnType = $resultMethod->getReturnType();

        if ($returnType === null) {
            $this->line(sprintf('    Warning: No return type on result() method'));
            return;
        }

        $typeName = $returnType->getName();
        $this->line(sprintf('    Return type: %s', $typeName));

        // Build schema from DTO class
        $schema = $this->buildSchemaFromDto($typeName, $openapi);

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

    private function buildSchemaFromDto(string $className, array &$openapi): array
    {
        if (!class_exists($className)) {
            return ['type' => 'object'];
        }

        $shortName = (new \ReflectionClass($className))->getShortName();

        // Check if already processed
        if (isset($openapi['components']['schemas'][$shortName])) {
            return ['$ref' => '#/components/schemas/' . $shortName];
        }

        $schema = $this->introspectDtoClass($className, $openapi);
        $openapi['components']['schemas'][$shortName] = $schema;

        return ['$ref' => '#/components/schemas/' . $shortName];
    }

    private function introspectDtoClass(string $className, array &$openapi): array
    {
        $reflection = new \ReflectionClass($className);
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        $required = [];

        // Get constructor parameters (for readonly classes)
        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                $name = $this->toSnakeCase($param->getName());
                $type = $param->getType();

                if ($type !== null) {
                    $schema['properties'][$name] = $this->typeToJsonSchema($type, $openapi, $reflection, $param->getName());

                    // Check if nullable
                    if (!$type->allowsNull() && !$param->isDefaultValueAvailable()) {
                        $required[] = $name;
                    }
                }
            }
        }

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    private function typeToJsonSchema(?\ReflectionType $type, array &$openapi, \ReflectionClass $context, string $propertyName): array
    {
        if ($type === null) {
            return ['type' => 'string'];
        }

        if ($type instanceof \ReflectionNamedType) {
            $typeName = $type->getName();

            $schema = match ($typeName) {
                'int' => ['type' => 'integer'],
                'float' => ['type' => 'number'],
                'string' => ['type' => 'string'],
                'bool' => ['type' => 'boolean'],
                'array' => $this->parseArrayType($context, $propertyName, $openapi),
                default => $this->handleComplexType($typeName, $openapi),
            };

            if ($type->allowsNull()) {
                $schema['nullable'] = true;
            }

            return $schema;
        }

        return ['type' => 'string'];
    }

    private function parseArrayType(\ReflectionClass $context, string $propertyName, array &$openapi): array
    {
        // Try to get type from PHPDoc
        $docComment = '';

        // Check constructor parameter
        $constructor = $context->getConstructor();
        if ($constructor !== null) {
            $docComment = $constructor->getDocComment() ?: '';
        }

        // Parse @param annotations
        if (preg_match('/@param\s+array<([^>]+)>\s+\$' . preg_quote($propertyName) . '/', $docComment, $matches)) {
            $itemType = trim($matches[1]);

            // Check for key-value array like array<string, int>
            if (str_contains($itemType, ',')) {
                [$keyType, $valueType] = array_map('trim', explode(',', $itemType, 2));
                if ($keyType === 'string') {
                    return [
                        'type' => 'object',
                        'additionalProperties' => $this->simpleTypeToSchema($valueType),
                    ];
                }
            }

            // Simple array like array<string> or array<JobResponseDto>
            if (class_exists($itemType) || class_exists('App\\Action\\Response\\' . $itemType)) {
                $fullClass = class_exists($itemType) ? $itemType : 'App\\Action\\Response\\' . $itemType;
                return [
                    'type' => 'array',
                    'items' => $this->buildSchemaFromDto($fullClass, $openapi),
                ];
            }

            return [
                'type' => 'array',
                'items' => $this->simpleTypeToSchema($itemType),
            ];
        }

        return ['type' => 'array', 'items' => ['type' => 'string']];
    }

    private function simpleTypeToSchema(string $type): array
    {
        return match ($type) {
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double' => ['type' => 'number'],
            'string' => ['type' => 'string'],
            'bool', 'boolean' => ['type' => 'boolean'],
            default => ['type' => 'string'],
        };
    }

    private function handleComplexType(string $typeName, array &$openapi): array
    {
        if (class_exists($typeName)) {
            return $this->buildSchemaFromDto($typeName, $openapi);
        }

        return ['type' => 'object'];
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
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

        if (str_contains($actionClass, 'GetJob') && !str_contains($actionClass, 'Stats')) {
            $params = [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer'], 'description' => 'Job ID'],
            ];
        }

        return $params;
    }

    private function toYaml(array $data): string
    {
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
