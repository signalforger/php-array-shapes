<?php
declare(strict_arrays=1);

/**
 * PHP Array Shapes - Working Examples
 *
 * Run with: php examples.php
 */

echo "=== PHP Array Shapes Examples ===\n\n";

// -----------------------------------------------------------------------------
// 1. Simple array<T>
// -----------------------------------------------------------------------------
function getIds(): array<int> {
    return [1, 2, 3, 4, 5];
}

echo "1. array<int>: ";
var_export(getIds());
echo "\n\n";

// -----------------------------------------------------------------------------
// 2. array<K, V> map types
// -----------------------------------------------------------------------------
function getScores(): array<string, int> {
    return ['alice' => 100, 'bob' => 85, 'charlie' => 92];
}

echo "2. array<string, int>: ";
var_export(getScores());
echo "\n\n";

// -----------------------------------------------------------------------------
// 3. Simple shape
// -----------------------------------------------------------------------------
function getUser(): array{id: int, name: string, email: string} {
    return ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
}

echo "3. array{id: int, name: string, email: string}: ";
var_export(getUser());
echo "\n\n";

// -----------------------------------------------------------------------------
// 4. Shape with optional keys
// -----------------------------------------------------------------------------
function getConfig(): array{host: string, port?: int, ssl?: bool} {
    return ['host' => 'localhost'];  // port and ssl are optional
}

echo "4. array{host: string, port?: int, ssl?: bool}: ";
var_export(getConfig());
echo "\n\n";

// -----------------------------------------------------------------------------
// 5. Nested array<array<T>> - 2 levels
// -----------------------------------------------------------------------------
function getMatrix(): array<array<int>> {
    return [
        [1, 2, 3],
        [4, 5, 6],
        [7, 8, 9]
    ];
}

echo "5. array<array<int>> (matrix): ";
var_export(getMatrix());
echo "\n\n";

// -----------------------------------------------------------------------------
// 6. Nested array<array<array<T>>> - 3 levels
// -----------------------------------------------------------------------------
function get3D(): array<array<array<int>>> {
    return [
        [[1, 2], [3, 4]],
        [[5, 6], [7, 8]]
    ];
}

echo "6. array<array<array<int>>> (3D): ";
var_export(get3D());
echo "\n\n";

// -----------------------------------------------------------------------------
// 7. Deep nesting - 5 levels
// -----------------------------------------------------------------------------
function get5Levels(): array<array<array<array<array<int>>>>> {
    return [[[[[1, 2, 3]]]]];
}

echo "7. 5 levels deep: ";
var_export(get5Levels());
echo "\n\n";

// -----------------------------------------------------------------------------
// 8. Deep nesting - 10 levels
// -----------------------------------------------------------------------------
function get10Levels(): array<array<array<array<array<array<array<array<array<array<int>>>>>>>>>> {
    return [[[[[[[[[[42]]]]]]]]]];
}

echo "8. 10 levels deep: ";
var_export(get10Levels());
echo "\n\n";

// -----------------------------------------------------------------------------
// 9. Array of shapes
// -----------------------------------------------------------------------------
function getUsers(): array<array{id: int, name: string}> {
    return [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 3, 'name' => 'Charlie']
    ];
}

echo "9. array<array{id: int, name: string}>: ";
var_export(getUsers());
echo "\n\n";

// -----------------------------------------------------------------------------
// 10. Shape containing typed arrays
// -----------------------------------------------------------------------------
function getApiResponse(): array{
    success: bool,
    data: array<array{id: int, title: string}>,
    count: int
} {
    return [
        'success' => true,
        'data' => [
            ['id' => 1, 'title' => 'First Post'],
            ['id' => 2, 'title' => 'Second Post']
        ],
        'count' => 2
    ];
}

echo "10. Shape with nested array<shape>: ";
var_export(getApiResponse());
echo "\n\n";

// -----------------------------------------------------------------------------
// 11. Complex real-world example: User with nested settings
// -----------------------------------------------------------------------------
function getUserProfile(): array{
    id: int,
    profile: array{
        name: string,
        email: string,
        avatar?: string
    },
    settings: array{
        theme: string,
        notifications: array{
            email: bool,
            push: bool,
            sms?: bool
        },
        privacy: array{
            showEmail: bool,
            showOnline: bool
        }
    },
    roles: array<string>
} {
    return [
        'id' => 1,
        'profile' => [
            'name' => 'Alice Smith',
            'email' => 'alice@example.com'
        ],
        'settings' => [
            'theme' => 'dark',
            'notifications' => [
                'email' => true,
                'push' => false
            ],
            'privacy' => [
                'showEmail' => false,
                'showOnline' => true
            ]
        ],
        'roles' => ['admin', 'moderator']
    ];
}

echo "11. Complex nested user profile:\n";
print_r(getUserProfile());
echo "\n";

// -----------------------------------------------------------------------------
// 12. Parameter types - shapes as input
// -----------------------------------------------------------------------------
function processOrder(array{product: string, quantity: int, price: float} $order): float {
    return $order['quantity'] * $order['price'];
}

$order = ['product' => 'Widget', 'quantity' => 5, 'price' => 9.99];
echo "12. Parameter shape - order total: " . processOrder($order) . "\n\n";

// -----------------------------------------------------------------------------
// 13. Parameter types - typed arrays as input
// -----------------------------------------------------------------------------
function sumScores(array<int> $scores): int {
    return array_sum($scores);
}

echo "13. Parameter array<int> - sum: " . sumScores([10, 20, 30, 40]) . "\n\n";

// -----------------------------------------------------------------------------
// 14. Both parameter and return shapes
// -----------------------------------------------------------------------------
function transformUser(
    array{id: int, name: string} $input
): array{id: int, name: string, processed: bool, timestamp: int} {
    return [
        ...$input,
        'processed' => true,
        'timestamp' => time()
    ];
}

echo "14. Transform with input and output shapes:\n";
print_r(transformUser(['id' => 1, 'name' => 'Alice']));
echo "\n";

// -----------------------------------------------------------------------------
// 15. Union types in arrays
// -----------------------------------------------------------------------------
function getMixedIds(): array<int|string> {
    return [1, 'abc', 2, 'def', 3];
}

echo "15. array<int|string>: ";
var_export(getMixedIds());
echo "\n\n";

// -----------------------------------------------------------------------------
// 16. Map with complex values
// -----------------------------------------------------------------------------
function getTeams(): array<string, array{members: array<string>, score: int}> {
    return [
        'red' => ['members' => ['Alice', 'Bob'], 'score' => 150],
        'blue' => ['members' => ['Charlie', 'Diana'], 'score' => 175]
    ];
}

echo "16. Map with shape values:\n";
print_r(getTeams());
echo "\n";

// -----------------------------------------------------------------------------
// 17. Nullable array types
// -----------------------------------------------------------------------------
function maybeGetData(): ?array<int> {
    return rand(0, 1) ? [1, 2, 3] : null;
}

echo "17. ?array<int>: ";
var_export(maybeGetData());
echo "\n\n";

// -----------------------------------------------------------------------------
// 18. Array elements can be nullable
// -----------------------------------------------------------------------------
function sparseArray(): array<?int> {
    return [1, null, 3, null, 5];
}

echo "18. array<?int>: ";
var_export(sparseArray());
echo "\n\n";

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "=== All 18 examples completed successfully! ===\n";
