#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Test Runner for Array Shapes RFC Simulation
 *
 * This validates the expected behavior of the RFC implementation
 * using a PHP simulation of the C code.
 */

require_once __DIR__ . '/ArrayShapeValidator.php';

use ArrayShapes\ArrayOfValidator;
use ArrayShapes\ArrayShapeValidator;
use ArrayShapes\TypeValidationError;
use function ArrayShapes\validateReturn;

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void
{
    global $passed, $failed;

    try {
        $fn();
        echo "✓ $name\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "✗ $name\n";
        echo "  Error: " . $e->getMessage() . "\n";
        $failed++;
    }
}

function expectException(string $exceptionClass, string $messageContains, callable $fn): void
{
    try {
        $fn();
        throw new \RuntimeException("Expected $exceptionClass but no exception was thrown");
    } catch (\Throwable $e) {
        if (!$e instanceof $exceptionClass) {
            throw new \RuntimeException(
                "Expected $exceptionClass but got " . get_class($e) . ": " . $e->getMessage()
            );
        }
        if (!str_contains($e->getMessage(), $messageContains)) {
            throw new \RuntimeException(
                "Expected message containing '$messageContains' but got: " . $e->getMessage()
            );
        }
    }
}

echo "=== Array Shape Return Types RFC - Simulation Tests ===\n\n";

// ============================================================================
// array<T> Tests
// ============================================================================

echo "--- array<T> Tests ---\n";

test('array<int> with valid integers', function() {
    $validator = new ArrayOfValidator('int');
    $validator->validate([1, 2, 3, 4, 5]);
});

test('array<int> with empty array', function() {
    $validator = new ArrayOfValidator('int');
    $validator->validate([]);
});

test('array<string> with valid strings', function() {
    $validator = new ArrayOfValidator('string');
    $validator->validate(['hello', 'world']);
});

test('array<int> fails with string element', function() {
    expectException(TypeValidationError::class, 'string given', function() {
        $validator = new ArrayOfValidator('int');
        $validator->validate([1, 2, 'three', 4]);
    });
});

test('array<string> fails with int element', function() {
    expectException(TypeValidationError::class, 'integer given', function() {
        $validator = new ArrayOfValidator('string');
        $validator->validate(['hello', 42]);
    });
});

test('array<?int> allows null elements', function() {
    $validator = new ArrayOfValidator('?int');
    $validator->validate([1, null, 3, null, 5]);
});

test('array<array<int>> nested validation', function() {
    $validator = new ArrayOfValidator('array<int>');
    $validator->validate([[1, 2], [3, 4], [5, 6]]);
});

test('array<array<int>> fails with invalid inner type', function() {
    expectException(TypeValidationError::class, 'string given', function() {
        $validator = new ArrayOfValidator('array<int>');
        $validator->validate([[1, 2], [3, 'four']]);
    });
});

echo "\n--- array{...} Shape Tests ---\n";

// ============================================================================
// array{...} Shape Tests
// ============================================================================

test('Simple shape with valid data', function() {
    $validator = ArrayShapeValidator::parse('array{id: int, name: string}');
    $validator->validate(['id' => 1, 'name' => 'Alice']);
});

test('Shape allows extra keys', function() {
    $validator = ArrayShapeValidator::parse('array{id: int, name: string}');
    $validator->validate(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']);
});

test('Shape fails with missing required key', function() {
    expectException(TypeValidationError::class, "missing required key 'email'", function() {
        $validator = ArrayShapeValidator::parse('array{id: int, name: string, email: string}');
        $validator->validate(['id' => 1, 'name' => 'Alice']);
    });
});

test('Shape fails with wrong type', function() {
    expectException(TypeValidationError::class, "must be of type int", function() {
        $validator = ArrayShapeValidator::parse('array{id: int, name: string}');
        $validator->validate(['id' => 'not-an-int', 'name' => 'Alice']);
    });
});

test('Shape with nullable value', function() {
    $validator = ArrayShapeValidator::parse('array{id: int, email: ?string}');
    $validator->validate(['id' => 1, 'email' => null]);
});

test('Shape with optional key missing', function() {
    $validator = ArrayShapeValidator::parse('array{id: int, email?: string}');
    $validator->validate(['id' => 1]); // email is optional
});

test('Shape with optional key present', function() {
    $validator = ArrayShapeValidator::parse('array{id: int, email?: string}');
    $validator->validate(['id' => 1, 'email' => 'test@example.com']);
});

test('Shape with integer keys', function() {
    $validator = ArrayShapeValidator::parse('array{0: string, 1: int}');
    $validator->validate(['hello', 42]);
});

echo "\n--- Nested Types Tests ---\n";

// ============================================================================
// Nested Types
// ============================================================================

test('array<array{id: int, name: string}> - array of shapes', function() {
    $validator = new ArrayOfValidator('array{id: int, name: string}');
    $validator->validate([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]);
});

test('array<array{id: int, name: string}> fails with invalid element', function() {
    expectException(TypeValidationError::class, "missing required key", function() {
        $validator = new ArrayOfValidator('array{id: int, name: string}');
        $validator->validate([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2], // missing 'name'
        ]);
    });
});

test('Shape with nested array<T>', function() {
    $validator = ArrayShapeValidator::parse('array{success: bool, data: array<int>}');
    $validator->validate([
        'success' => true,
        'data' => [1, 2, 3, 4, 5],
    ]);
});

test('Shape with nested shape', function() {
    $validator = ArrayShapeValidator::parse('array{user: array{id: int, name: string}, active: bool}');
    $validator->validate([
        'user' => ['id' => 1, 'name' => 'Alice'],
        'active' => true,
    ]);
});

test('Complex nested: array{success: bool, data: array<array{id: int}>}', function() {
    $validator = ArrayShapeValidator::parse('array{success: bool, data: array<array{id: int}>, total: int}');
    $validator->validate([
        'success' => true,
        'data' => [
            ['id' => 1],
            ['id' => 2],
        ],
        'total' => 2,
    ]);
});

echo "\n--- validateReturn() Function Tests ---\n";

// ============================================================================
// validateReturn() Tests (simulates runtime return type checking)
// ============================================================================

test('validateReturn with array<int>', function() {
    $result = validateReturn([1, 2, 3], 'array<int>', 'getIds');
    assert($result === [1, 2, 3]);
});

test('validateReturn with array{id: int, name: string}', function() {
    $result = validateReturn(
        ['id' => 1, 'name' => 'Alice'],
        'array{id: int, name: string}',
        'getUser'
    );
    assert($result['id'] === 1);
});

test('validateReturn with ?array<int> returning null', function() {
    $result = validateReturn(null, '?array<int>', 'getMaybeIds');
    assert($result === null);
});

test('validateReturn fails with wrong type', function() {
    expectException(TypeValidationError::class, 'Return value', function() {
        validateReturn([1, 'two', 3], 'array<int>', 'getIds');
    });
});

echo "\n--- Real-World Use Case Tests ---\n";

// ============================================================================
// Real-World Use Cases
// ============================================================================

test('API Response: paginated users', function() {
    $validator = ArrayShapeValidator::parse(
        'array{data: array<array{id: int, name: string, email: ?string}>, ' .
        'meta: array{total: int, page: int, per_page: int}}'
    );

    $validator->validate([
        'data' => [
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'name' => 'Bob', 'email' => null],
        ],
        'meta' => [
            'total' => 100,
            'page' => 1,
            'per_page' => 10,
        ],
    ]);
});

test('Database row shape', function() {
    $validator = ArrayShapeValidator::parse(
        'array{id: int, created_at: string, updated_at: ?string, ' .
        'data: array{title: string, body: string}}'
    );

    $validator->validate([
        'id' => 42,
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => null,
        'data' => [
            'title' => 'Hello World',
            'body' => 'This is the content.',
        ],
    ]);
});

test('Configuration shape', function() {
    $validator = ArrayShapeValidator::parse(
        'array{database: array{host: string, port?: int, username: string, password: string}, ' .
        'cache: array{driver: string, ttl?: int}}'
    );

    $validator->validate([
        'database' => [
            'host' => 'localhost',
            'username' => 'root',
            'password' => 'secret',
        ],
        'cache' => [
            'driver' => 'redis',
            'ttl' => 3600,
        ],
    ]);
});

// ============================================================================
// Summary
// ============================================================================

echo "\n=== Test Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed > 0) {
    echo "\nSome tests failed!\n";
    exit(1);
}

echo "\nAll tests passed! ✓\n";
exit(0);
