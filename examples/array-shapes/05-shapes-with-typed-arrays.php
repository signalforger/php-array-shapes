<?php
/**
 * Combining Array Shapes with Typed Arrays (array<T>)
 *
 * Array shapes can contain typed array values, and typed arrays can contain shapes.
 * This allows for powerful type definitions like array<array{id: int, name: string}>
 */

declare(strict_arrays=1);

// =============================================================================
// TYPED ARRAYS INSIDE SHAPES
// =============================================================================

/**
 * Shape with a typed array value
 */
function getNumberList(): array{name: string, values: array<int>} {
    return [
        'name' => 'Prime numbers',
        'values' => [2, 3, 5, 7, 11, 13]
    ];
}

$list = getNumberList();
echo "List '{$list['name']}': " . implode(', ', $list['values']) . "\n";


/**
 * Shape with multiple typed array values
 */
function getDataSets(): array{
    integers: array<int>,
    floats: array<float>,
    strings: array<string>
} {
    return [
        'integers' => [1, 2, 3],
        'floats' => [1.1, 2.2, 3.3],
        'strings' => ['a', 'b', 'c']
    ];
}

$sets = getDataSets();
echo "Integers: " . implode(', ', $sets['integers']) . "\n";
echo "Floats: " . implode(', ', $sets['floats']) . "\n";
echo "Strings: " . implode(', ', $sets['strings']) . "\n";


// =============================================================================
// TYPED ARRAYS OF SHAPES (Lists of Objects)
// =============================================================================

/**
 * Typed array containing shapes - perfect for list of records
 */
function getUsers(): array<array{id: int, name: string, active: bool}> {
    return [
        ['id' => 1, 'name' => 'Alice', 'active' => true],
        ['id' => 2, 'name' => 'Bob', 'active' => false],
        ['id' => 3, 'name' => 'Carol', 'active' => true]
    ];
}

$users = getUsers();
echo "Users:\n";
foreach ($users as $user) {
    echo "  - {$user['name']} (ID: {$user['id']}, " . ($user['active'] ? 'active' : 'inactive') . ")\n";
}


/**
 * Typed array with more complex shapes
 */
function getOrders(): array<array{
    order_id: string,
    amount: float,
    status: string,
    created: string
}> {
    return [
        ['order_id' => 'ORD-001', 'amount' => 99.99, 'status' => 'pending', 'created' => '2024-01-15'],
        ['order_id' => 'ORD-002', 'amount' => 149.50, 'status' => 'shipped', 'created' => '2024-01-16'],
        ['order_id' => 'ORD-003', 'amount' => 29.99, 'status' => 'delivered', 'created' => '2024-01-17']
    ];
}

$orders = getOrders();
echo "Orders:\n";
foreach ($orders as $order) {
    echo "  - {$order['order_id']}: \${$order['amount']} ({$order['status']})\n";
}


// =============================================================================
// SHAPES WITH OPTIONAL KEYS IN TYPED ARRAYS
// =============================================================================

/**
 * Typed array of shapes with optional keys
 */
function getContacts(): array<array{name: string, email: string, phone?: string}> {
    return [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'phone' => '555-0101'],
        ['name' => 'Bob', 'email' => 'bob@example.com'], // phone is optional
        ['name' => 'Carol', 'email' => 'carol@example.com', 'phone' => '555-0103']
    ];
}

$contacts = getContacts();
echo "Contacts:\n";
foreach ($contacts as $contact) {
    echo "  - {$contact['name']}: {$contact['email']}" . (isset($contact['phone']) ? " ({$contact['phone']})" : "") . "\n";
}


// =============================================================================
// NESTED: SHAPE WITH TYPED ARRAY OF SHAPES
// =============================================================================

/**
 * Shape containing a typed array of shapes
 */
function getDepartment(): array{
    name: string,
    budget: float,
    employees: array<array{id: int, name: string, role: string}>
} {
    return [
        'name' => 'Engineering',
        'budget' => 500000.00,
        'employees' => [
            ['id' => 1, 'name' => 'Alice', 'role' => 'Lead'],
            ['id' => 2, 'name' => 'Bob', 'role' => 'Senior'],
            ['id' => 3, 'name' => 'Carol', 'role' => 'Junior']
        ]
    ];
}

$dept = getDepartment();
echo "Department: {$dept['name']} (Budget: \${$dept['budget']})\n";
echo "  Employees:\n";
foreach ($dept['employees'] as $emp) {
    echo "    - {$emp['name']} ({$emp['role']})\n";
}


// =============================================================================
// COMPLEX: MULTIPLE TYPED ARRAYS IN SHAPES
// =============================================================================

/**
 * Shape with multiple typed array fields
 */
function getProject(): array{
    id: string,
    name: string,
    tags: array<string>,
    members: array<array{user_id: int, role: string}>,
    milestones: array<array{name: string, date: string, completed: bool}>
} {
    return [
        'id' => 'PRJ-001',
        'name' => 'Website Redesign',
        'tags' => ['web', 'design', 'frontend'],
        'members' => [
            ['user_id' => 1, 'role' => 'lead'],
            ['user_id' => 2, 'role' => 'developer']
        ],
        'milestones' => [
            ['name' => 'Design', 'date' => '2024-02-01', 'completed' => true],
            ['name' => 'Development', 'date' => '2024-03-01', 'completed' => false]
        ]
    ];
}

$project = getProject();
echo "Project: {$project['name']}\n";
echo "  Tags: " . implode(', ', $project['tags']) . "\n";
echo "  Members: " . count($project['members']) . "\n";
echo "  Milestones:\n";
foreach ($project['milestones'] as $ms) {
    echo "    - {$ms['name']}: " . ($ms['completed'] ? 'done' : 'pending') . "\n";
}


// =============================================================================
// KEYED TYPED ARRAYS IN SHAPES
// =============================================================================

/**
 * Shape with keyed (associative) typed array
 */
function getSettings(): array{
    app_name: string,
    features: array<string, bool>,
    limits: array<string, int>
} {
    return [
        'app_name' => 'MyApp',
        'features' => [
            'dark_mode' => true,
            'notifications' => false,
            'analytics' => true
        ],
        'limits' => [
            'max_users' => 100,
            'max_storage' => 1024,
            'max_requests' => 1000
        ]
    ];
}

$settings = getSettings();
echo "App: {$settings['app_name']}\n";
echo "  Features:\n";
foreach ($settings['features'] as $feature => $enabled) {
    echo "    - {$feature}: " . ($enabled ? 'on' : 'off') . "\n";
}
echo "  Limits:\n";
foreach ($settings['limits'] as $limit => $value) {
    echo "    - {$limit}: {$value}\n";
}


// =============================================================================
// KEYED TYPED ARRAYS OF SHAPES
// =============================================================================

/**
 * Associative array of shapes (keyed by string)
 */
function getUsersById(): array<string, array{name: string, email: string}> {
    return [
        'user_1' => ['name' => 'Alice', 'email' => 'alice@example.com'],
        'user_2' => ['name' => 'Bob', 'email' => 'bob@example.com'],
        'user_3' => ['name' => 'Carol', 'email' => 'carol@example.com']
    ];
}

$usersById = getUsersById();
echo "Users by ID:\n";
foreach ($usersById as $id => $user) {
    echo "  - {$id}: {$user['name']} <{$user['email']}>\n";
}


/**
 * Integer-keyed array of shapes
 */
function getIndexedProducts(): array<int, array{sku: string, name: string, price: float}> {
    return [
        100 => ['sku' => 'WIDGET-A', 'name' => 'Widget A', 'price' => 9.99],
        200 => ['sku' => 'WIDGET-B', 'name' => 'Widget B', 'price' => 19.99],
        300 => ['sku' => 'WIDGET-C', 'name' => 'Widget C', 'price' => 29.99]
    ];
}

$products = getIndexedProducts();
echo "Products by index:\n";
foreach ($products as $idx => $product) {
    echo "  - [{$idx}] {$product['name']} (\${$product['price']})\n";
}


// =============================================================================
// PARAMETER: TYPED ARRAY OF SHAPES
// =============================================================================

/**
 * Function accepting typed array of shapes as parameter
 */
function calculateTotal(array<array{quantity: int, price: float}> $items): float {
    $total = 0.0;
    foreach ($items as $item) {
        $total += $item['quantity'] * $item['price'];
    }
    return $total;
}

$cart = [
    ['quantity' => 2, 'price' => 10.00],
    ['quantity' => 1, 'price' => 25.50],
    ['quantity' => 3, 'price' => 5.00]
];
$total = calculateTotal($cart);
echo "Cart total: \${$total}\n";


echo "\n--- All typed array with shapes examples completed successfully! ---\n";
