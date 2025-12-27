#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Demo: Array Shape Return Types RFC
 *
 * This demonstrates what the syntax will look like when implemented in PHP 8.5.
 * The validateReturn() calls simulate the native runtime validation.
 */

require_once __DIR__ . '/ArrayShapeValidator.php';

use function ArrayShapes\validateReturn;

echo "=== Array Shape Return Types RFC Demo ===\n\n";

// ============================================================================
// Example 1: Simple array<T>
// ============================================================================

echo "1. Simple array<int>:\n";

// Native PHP 8.5 syntax:
// function getIds(): array<int> { return [1, 2, 3]; }

function getIds(): array
{
    $result = [1, 2, 3, 4, 5];
    return validateReturn($result, 'array<int>', __FUNCTION__);
}

$ids = getIds();
echo "   getIds() = " . json_encode($ids) . "\n\n";

// ============================================================================
// Example 2: Simple shape
// ============================================================================

echo "2. Simple array{id: int, name: string}:\n";

// Native PHP 8.5 syntax:
// function getUser(): array{id: int, name: string} { ... }

function getUser(): array
{
    $result = ['id' => 1, 'name' => 'Alice'];
    return validateReturn($result, 'array{id: int, name: string}', __FUNCTION__);
}

$user = getUser();
echo "   getUser() = " . json_encode($user) . "\n\n";

// ============================================================================
// Example 3: Nested array<array<T>>
// ============================================================================

echo "3. Nested array<array<int>> (matrix):\n";

// Native PHP 8.5 syntax:
// function getMatrix(): array<array<int>> { ... }

function getMatrix(): array
{
    $result = [
        [1, 2, 3],
        [4, 5, 6],
        [7, 8, 9],
    ];
    return validateReturn($result, 'array<array<int>>', __FUNCTION__);
}

$matrix = getMatrix();
echo "   getMatrix() = " . json_encode($matrix) . "\n\n";

// ============================================================================
// Example 4: Array of shapes
// ============================================================================

echo "4. array<array{id: int, name: string}> (list of users):\n";

// Native PHP 8.5 syntax:
// function getUsers(): array<array{id: int, name: string}> { ... }

function getUsers(): array
{
    $result = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 3, 'name' => 'Charlie'],
    ];
    return validateReturn($result, 'array<array{id: int, name: string}>', __FUNCTION__);
}

$users = getUsers();
echo "   getUsers() = " . json_encode($users) . "\n\n";

// ============================================================================
// Example 5: Complex nested structure
// ============================================================================

echo "5. Complex API response shape:\n";

// Native PHP 8.5 syntax:
// function getApiResponse(): array{
//     success: bool,
//     data: array<array{id: int, name: string}>,
//     meta: array{total: int, page: int}
// } { ... }

function getApiResponse(): array
{
    $result = [
        'success' => true,
        'data' => [
            ['id' => 1, 'name' => 'Product A'],
            ['id' => 2, 'name' => 'Product B'],
        ],
        'meta' => [
            'total' => 100,
            'page' => 1,
        ],
    ];
    return validateReturn(
        $result,
        'array{success: bool, data: array<array{id: int, name: string}>, meta: array{total: int, page: int}}',
        __FUNCTION__
    );
}

$response = getApiResponse();
echo "   getApiResponse() = " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

// ============================================================================
// Example 6: Optional keys
// ============================================================================

echo "6. Shape with optional keys:\n";

// Native PHP 8.5 syntax:
// function getConfig(): array{host: string, port?: int, ssl?: bool} { ... }

function getConfig(): array
{
    $result = ['host' => 'localhost']; // port and ssl are optional
    return validateReturn($result, 'array{host: string, port?: int, ssl?: bool}', __FUNCTION__);
}

$config = getConfig();
echo "   getConfig() = " . json_encode($config) . "\n\n";

// ============================================================================
// Example 7: Nullable types
// ============================================================================

echo "7. Nullable array and nullable elements:\n";

// Native PHP 8.5 syntax:
// function getMaybeInts(): ?array<int> { ... }

function getMaybeInts(): ?array
{
    $result = null;
    return validateReturn($result, '?array<int>', __FUNCTION__);
}

$maybeInts = getMaybeInts();
echo "   getMaybeInts() = " . json_encode($maybeInts) . "\n";

// Native PHP 8.5 syntax:
// function getIntsWithNulls(): array<?int> { ... }

function getIntsWithNulls(): array
{
    $result = [1, null, 3, null, 5];
    return validateReturn($result, 'array<?int>', __FUNCTION__);
}

$intsWithNulls = getIntsWithNulls();
echo "   getIntsWithNulls() = " . json_encode($intsWithNulls) . "\n\n";

// ============================================================================
// Example 8: Type error demonstration
// ============================================================================

echo "8. Type error demonstration:\n";

function getBadData(): array
{
    $result = [1, 2, 'three', 4]; // 'three' is invalid
    return validateReturn($result, 'array<int>', __FUNCTION__);
}

try {
    getBadData();
} catch (TypeError $e) {
    echo "   Caught TypeError: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// Example 9: Missing key demonstration
// ============================================================================

echo "9. Missing key demonstration:\n";

function getUserMissingField(): array
{
    $result = ['id' => 1]; // missing 'name'
    return validateReturn($result, 'array{id: int, name: string}', __FUNCTION__);
}

try {
    getUserMissingField();
} catch (TypeError $e) {
    echo "   Caught TypeError: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// Real-World Example: Repository Pattern
// ============================================================================

echo "10. Real-world example - UserRepository:\n";

class UserRepository
{
    private array $users = [
        ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'active' => true],
        ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'active' => false],
        ['id' => 3, 'name' => 'Charlie', 'email' => null, 'active' => true],
    ];

    // Native: public function findAll(): array<array{id: int, name: string, email: ?string, active: bool}>
    public function findAll(): array
    {
        return validateReturn(
            $this->users,
            'array<array{id: int, name: string, email: ?string, active: bool}>',
            __CLASS__ . '::' . __FUNCTION__
        );
    }

    // Native: public function findById(int $id): ?array{id: int, name: string, email: ?string, active: bool}
    public function findById(int $id): ?array
    {
        foreach ($this->users as $user) {
            if ($user['id'] === $id) {
                return validateReturn(
                    $user,
                    'array{id: int, name: string, email: ?string, active: bool}',
                    __CLASS__ . '::' . __FUNCTION__
                );
            }
        }
        return null;
    }

    // Native: public function findActive(): array<array{id: int, name: string}>
    public function findActive(): array
    {
        $active = array_filter($this->users, fn($u) => $u['active']);
        $result = array_map(fn($u) => ['id' => $u['id'], 'name' => $u['name']], $active);
        return validateReturn(
            array_values($result),
            'array<array{id: int, name: string}>',
            __CLASS__ . '::' . __FUNCTION__
        );
    }
}

$repo = new UserRepository();

echo "   findAll():\n";
foreach ($repo->findAll() as $user) {
    $email = $user['email'] ?? 'no email';
    echo "     - {$user['name']} ($email)\n";
}

echo "\n   findById(2):\n";
$user = $repo->findById(2);
echo "     " . json_encode($user) . "\n";

echo "\n   findActive():\n";
foreach ($repo->findActive() as $user) {
    echo "     - ID: {$user['id']}, Name: {$user['name']}\n";
}

echo "\n=== Demo Complete ===\n";
