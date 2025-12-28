<?php
/**
 * Union Types and Nullable Types in Array Shapes
 *
 * Shape values can use union types (type1|type2) and nullable types (?type).
 */

declare(strict_arrays=1);

// =============================================================================
// UNION TYPES IN SHAPES
// =============================================================================

/**
 * Shape with union type values
 */
function getFlexibleId(): array{id: int|string} {
    return ['id' => 'ABC-123']; // Can be string
}

function getNumericId(): array{id: int|string} {
    return ['id' => 42]; // Can also be int
}

$flex = getFlexibleId();
$num = getNumericId();
echo "Flexible ID: {$flex['id']} (type: " . gettype($flex['id']) . ")\n";
echo "Numeric ID: {$num['id']} (type: " . gettype($num['id']) . ")\n";


/**
 * Multiple union types in a shape
 */
function getMixedData(): array{
    value: int|float|string,
    status: bool|string,
    code: int|null
} {
    return [
        'value' => 3.14,
        'status' => 'active',
        'code' => null
    ];
}

$data = getMixedData();
echo "Mixed data - value: {$data['value']}, status: {$data['status']}, code: " . ($data['code'] ?? 'null') . "\n";


// =============================================================================
// NULLABLE TYPES IN SHAPES
// =============================================================================

/**
 * Shape with nullable type values using ? syntax
 */
function getUserWithOptionalEmail(): array{name: string, email: ?string} {
    return [
        'name' => 'Alice',
        'email' => null // Explicitly null
    ];
}

function getUserWithEmail(): array{name: string, email: ?string} {
    return [
        'name' => 'Bob',
        'email' => 'bob@example.com'
    ];
}

$user1 = getUserWithOptionalEmail();
$user2 = getUserWithEmail();
echo "User 1: {$user1['name']}, email: " . ($user1['email'] ?? 'null') . "\n";
echo "User 2: {$user2['name']}, email: {$user2['email']}\n";


/**
 * Shape with multiple nullable types
 */
function getNullableConfig(): array{
    host: string,
    port: ?int,
    timeout: ?float,
    proxy: ?string
} {
    return [
        'host' => 'localhost',
        'port' => 8080,
        'timeout' => null,
        'proxy' => null
    ];
}

$config = getNullableConfig();
echo "Config - host: {$config['host']}, port: {$config['port']}, timeout: " . ($config['timeout'] ?? 'null') . "\n";


// =============================================================================
// COMBINING UNION AND NULLABLE
// =============================================================================

/**
 * Nullable union types
 */
function getResult(): array{
    success: bool,
    data: string|int|null,
    error: ?string
} {
    return [
        'success' => true,
        'data' => 42,
        'error' => null
    ];
}

function getErrorResult(): array{
    success: bool,
    data: string|int|null,
    error: ?string
} {
    return [
        'success' => false,
        'data' => null,
        'error' => 'Something went wrong'
    ];
}

$success = getResult();
$error = getErrorResult();
echo "Success result - data: {$success['data']}\n";
echo "Error result - error: {$error['error']}\n";


// =============================================================================
// UNION TYPES WITH OBJECTS
// =============================================================================

class User {
    public function __construct(public string $name) {}
}

class Admin {
    public function __construct(public string $name, public string $role) {}
}

/**
 * Shape with object union types
 */
function getAccount(): array{
    id: int,
    principal: User|Admin
} {
    return [
        'id' => 1,
        'principal' => new Admin('Alice', 'super')
    ];
}

$account = getAccount();
echo "Account ID: {$account['id']}, Principal: {$account['principal']->name}\n";


// =============================================================================
// COMPLEX UNION EXAMPLES
// =============================================================================

/**
 * Real-world example: Database column value
 */
function getColumnValue(): array{
    column: string,
    value: int|float|string|bool|null,
    type: string
} {
    return [
        'column' => 'price',
        'value' => 29.99,
        'type' => 'decimal'
    ];
}

$col = getColumnValue();
echo "Column '{$col['column']}' = {$col['value']} (type: {$col['type']})\n";


/**
 * Union with array types
 */
function getItems(): array{
    items: array|null,
    count: int
} {
    return [
        'items' => [1, 2, 3],
        'count' => 3
    ];
}

function getEmptyItems(): array{
    items: array|null,
    count: int
} {
    return [
        'items' => null,
        'count' => 0
    ];
}

$items = getItems();
$empty = getEmptyItems();
echo "Items: " . ($items['items'] ? implode(', ', $items['items']) : 'none') . "\n";
echo "Empty items: " . ($empty['items'] ? implode(', ', $empty['items']) : 'none') . "\n";


// =============================================================================
// OPTIONAL VS NULLABLE
// =============================================================================

/**
 * Demonstrates the difference between optional keys and nullable types:
 * - Optional key (key?): The key may be absent from the array
 * - Nullable type (?type): The key must be present but can have null value
 */

// Optional: key may not exist
function withOptionalKey(): array{name: string, age?: int} {
    return ['name' => 'Alice']; // 'age' key is completely absent
}

// Nullable: key exists but value is null
function withNullableValue(): array{name: string, age: ?int} {
    return ['name' => 'Bob', 'age' => null]; // 'age' key must exist, but value can be null
}

$opt = withOptionalKey();
$null = withNullableValue();
echo "Optional - has 'age' key: " . (array_key_exists('age', $opt) ? 'yes' : 'no') . "\n";
echo "Nullable - has 'age' key: " . (array_key_exists('age', $null) ? 'yes' : 'no') . ", value: " . ($null['age'] ?? 'null') . "\n";


// =============================================================================
// COMBINING OPTIONAL AND NULLABLE
// =============================================================================

/**
 * Optional key with nullable type: key may be absent, or present with null/value
 */
function getContact(): array{
    phone: string,
    fax?: ?string,  // Optional AND nullable
    email?: string  // Just optional (if present, must be string)
} {
    return [
        'phone' => '555-0100',
        'fax' => null  // Present but null
        // email is absent
    ];
}

$contact = getContact();
echo "Contact - phone: {$contact['phone']}, fax: " . (array_key_exists('fax', $contact) ? ($contact['fax'] ?? 'null') : 'not set') . "\n";


echo "\n--- All union and nullable type examples completed successfully! ---\n";
