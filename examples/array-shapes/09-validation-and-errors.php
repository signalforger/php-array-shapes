<?php
/**
 * Validation and Error Handling with Array Shapes
 *
 * When declare(strict_arrays=1) is enabled, PHP validates array shapes
 * at runtime and throws TypeError on mismatches.
 */

declare(strict_arrays=1);

// =============================================================================
// BASIC TYPE VALIDATION
// =============================================================================

echo "=== Basic Type Validation ===\n";

function getValidUser(): array{id: int, name: string} {
    return ['id' => 1, 'name' => 'Alice']; // Valid
}

$user = getValidUser();
echo "Valid user: ID={$user['id']}, Name={$user['name']}\n\n";


// =============================================================================
// CATCHING TYPE ERRORS - MISSING REQUIRED KEY
// =============================================================================

echo "=== Missing Required Key ===\n";

function getMissingKeyUser(): array{id: int, name: string, email: string} {
    // This would cause a TypeError because 'email' is missing
    // Uncommenting will throw: TypeError: Return value must have key 'email'
    // return ['id' => 1, 'name' => 'Alice'];

    // Correct version:
    return ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
}

try {
    $user = getMissingKeyUser();
    echo "User retrieved successfully: {$user['email']}\n";
} catch (TypeError $e) {
    echo "TypeError caught: {$e->getMessage()}\n";
}
echo "\n";


// =============================================================================
// CATCHING TYPE ERRORS - WRONG VALUE TYPE
// =============================================================================

echo "=== Wrong Value Type ===\n";

function getTypedUser(int $id, string $name): array{id: int, name: string} {
    // Parameters ensure correct types
    return ['id' => $id, 'name' => $name];
}

try {
    // This works
    $user = getTypedUser(1, 'Bob');
    echo "User: ID={$user['id']} (type: " . gettype($user['id']) . ")\n";

    // This would fail at parameter level
    // $user = getTypedUser('not-an-int', 'Carol');
} catch (TypeError $e) {
    echo "TypeError caught: {$e->getMessage()}\n";
}
echo "\n";


// =============================================================================
// OPTIONAL KEYS DON'T REQUIRE VALUES
// =============================================================================

echo "=== Optional Keys ===\n";

function getPartialConfig(): array{host: string, port?: int, ssl?: bool} {
    // Only 'host' is required - this is valid
    return ['host' => 'localhost'];
}

function getFullConfig(): array{host: string, port?: int, ssl?: bool} {
    // All keys provided - also valid
    return ['host' => 'secure.example.com', 'port' => 443, 'ssl' => true];
}

$partial = getPartialConfig();
$full = getFullConfig();
echo "Partial config - host: {$partial['host']}, port: " . ($partial['port'] ?? 'not set') . "\n";
echo "Full config - host: {$full['host']}, port: {$full['port']}, ssl: " . ($full['ssl'] ? 'yes' : 'no') . "\n\n";


// =============================================================================
// VALIDATION WITH NULLABLE TYPES
// =============================================================================

echo "=== Nullable Types ===\n";

function getNullableData(): array{value: ?string, count: ?int} {
    return [
        'value' => null,  // Valid - nullable
        'count' => null   // Valid - nullable
    ];
}

function getMixedNullable(): array{value: ?string, count: ?int} {
    return [
        'value' => 'hello',  // Valid - string
        'count' => null      // Valid - null
    ];
}

$null = getNullableData();
$mixed = getMixedNullable();
echo "Nullable data - value: " . ($null['value'] ?? 'null') . ", count: " . ($null['count'] ?? 'null') . "\n";
echo "Mixed data - value: {$mixed['value']}, count: " . ($mixed['count'] ?? 'null') . "\n\n";


// =============================================================================
// PARAMETER VALIDATION
// =============================================================================

echo "=== Parameter Validation ===\n";

function processData(array{x: int, y: int} $point): int {
    return $point['x'] + $point['y'];
}

try {
    // Valid call
    $sum = processData(['x' => 10, 'y' => 20]);
    echo "Sum: {$sum}\n";

    // Invalid call would be caught here
    // $sum = processData(['x' => 10]); // Missing 'y'
    // $sum = processData(['x' => 'ten', 'y' => 20]); // Wrong type
} catch (TypeError $e) {
    echo "TypeError caught: {$e->getMessage()}\n";
}
echo "\n";


// =============================================================================
// NESTED SHAPE VALIDATION
// =============================================================================

echo "=== Nested Shape Validation ===\n";

function getNestedValid(): array{
    user: array{id: int, name: string},
    meta: array{created: string}
} {
    return [
        'user' => ['id' => 1, 'name' => 'Alice'],
        'meta' => ['created' => '2024-01-15']
    ];
}

$nested = getNestedValid();
echo "Nested user: {$nested['user']['name']}, created: {$nested['meta']['created']}\n\n";


// =============================================================================
// SAFE WRAPPER PATTERN
// =============================================================================

echo "=== Safe Wrapper Pattern ===\n";

/**
 * Wrapper that catches validation errors and returns a result shape
 */
function safeGetUser(int $id): array{success: bool, data: ?array, error: ?string} {
    try {
        // Simulate fetching user
        if ($id <= 0) {
            throw new InvalidArgumentException("Invalid user ID: {$id}");
        }

        $user = [
            'id' => $id,
            'name' => 'User ' . $id,
            'email' => "user{$id}@example.com"
        ];

        return [
            'success' => true,
            'data' => $user,
            'error' => null
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'data' => null,
            'error' => $e->getMessage()
        ];
    }
}

$result1 = safeGetUser(42);
$result2 = safeGetUser(-1);

echo "Result 1: " . ($result1['success'] ? "success - {$result1['data']['name']}" : "failed - {$result1['error']}") . "\n";
echo "Result 2: " . ($result2['success'] ? "success - {$result2['data']['name']}" : "failed - {$result2['error']}") . "\n\n";


// =============================================================================
// VALIDATION HELPER FUNCTIONS
// =============================================================================

echo "=== Custom Validation Helpers ===\n";

/**
 * Validate array has required keys before using as shape
 */
function validateShape(array $data, array $requiredKeys): bool {
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $data)) {
            return false;
        }
    }
    return true;
}

/**
 * Validate and coerce to shape
 */
function toUserShape(array $data): array{id: int, name: string, email: string} {
    // Validate required keys
    $required = ['id', 'name', 'email'];
    foreach ($required as $key) {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException("Missing required key: {$key}");
        }
    }

    // Coerce types if needed
    return [
        'id' => (int) $data['id'],
        'name' => (string) $data['name'],
        'email' => (string) $data['email']
    ];
}

try {
    $userData = ['id' => '123', 'name' => 'Test', 'email' => 'test@example.com'];
    $user = toUserShape($userData);
    echo "Coerced user: ID={$user['id']} (type: " . gettype($user['id']) . ")\n";
} catch (InvalidArgumentException $e) {
    echo "Validation failed: {$e->getMessage()}\n";
}
echo "\n";


// =============================================================================
// ASSERTION STYLE VALIDATION
// =============================================================================

echo "=== Assertion Style Validation ===\n";

/**
 * Assert function for shape validation
 */
function assertValidUser(array $user): void {
    assert(isset($user['id']) && is_int($user['id']), 'id must be an integer');
    assert(isset($user['name']) && is_string($user['name']), 'name must be a string');
    assert(isset($user['email']) && is_string($user['email']), 'email must be a string');
}

$validUser = ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
assertValidUser($validUser);
echo "User validated successfully\n\n";


// =============================================================================
// TYPED ARRAYS VALIDATION
// =============================================================================

echo "=== Typed Arrays Validation ===\n";

function getTypedList(): array<int> {
    return [1, 2, 3, 4, 5]; // Valid - all integers
}

function getTypedShapeList(): array<array{id: int, name: string}> {
    return [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 3, 'name' => 'Carol']
    ];
}

$numbers = getTypedList();
$users = getTypedShapeList();
echo "Numbers: " . implode(', ', $numbers) . "\n";
echo "Users: " . count($users) . " valid user shapes\n\n";


// =============================================================================
// DEFENSIVE CODING PATTERNS
// =============================================================================

echo "=== Defensive Coding Patterns ===\n";

class UserRepository
{
    /**
     * Returns user or throws exception
     */
    public function findOrFail(int $id): array{id: int, name: string, email: string} {
        $user = $this->find($id);
        if ($user === null) {
            throw new RuntimeException("User not found: {$id}");
        }
        return $user;
    }

    /**
     * Returns user or null
     */
    public function find(int $id): ?array {
        if ($id <= 0) {
            return null;
        }
        return ['id' => $id, 'name' => "User {$id}", 'email' => "user{$id}@example.com"];
    }

    /**
     * Returns result shape for safer error handling
     */
    public function findSafe(int $id): array{found: bool, user: ?array} {
        $user = $this->find($id);
        return [
            'found' => $user !== null,
            'user' => $user
        ];
    }
}

$repo = new UserRepository();

// Pattern 1: Try-catch with findOrFail
try {
    $user = $repo->findOrFail(1);
    echo "Found user: {$user['name']}\n";
} catch (RuntimeException $e) {
    echo "Error: {$e->getMessage()}\n";
}

// Pattern 2: Check result shape
$result = $repo->findSafe(999);
if ($result['found']) {
    echo "User found: {$result['user']['name']}\n";
} else {
    echo "User not found\n";
}


echo "\n--- All validation and error examples completed successfully! ---\n";
