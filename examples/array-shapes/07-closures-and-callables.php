<?php
/**
 * Array Shapes with Closures and Callable Types
 *
 * Array shapes can be used with closures, arrow functions, and callable parameters.
 */

declare(strict_arrays=1);

// =============================================================================
// CLOSURES WITH ARRAY SHAPE RETURN TYPES
// =============================================================================

/**
 * Closure returning an array shape
 */
$createUser = function(string $name, string $email): array{id: int, name: string, email: string} {
    return [
        'id' => rand(1, 1000),
        'name' => $name,
        'email' => $email
    ];
};

$user = $createUser('Alice', 'alice@example.com');
echo "Created user: {$user['name']} <{$user['email']}>\n";


/**
 * Closure with array shape parameter
 */
$formatUser = function(array{name: string, email: string} $user): string {
    return "{$user['name']} <{$user['email']}>";
};

echo "Formatted: " . $formatUser(['name' => 'Bob', 'email' => 'bob@example.com']) . "\n";


// =============================================================================
// ARROW FUNCTIONS WITH ARRAY SHAPES
// =============================================================================

/**
 * Arrow function returning array shape
 */
$getPoint = fn(int $x, int $y): array{x: int, y: int} => ['x' => $x, 'y' => $y];

$point = $getPoint(10, 20);
echo "Point: ({$point['x']}, {$point['y']})\n";


/**
 * Arrow function with array shape parameter
 */
$distance = fn(array{x: int, y: int} $p): float => sqrt($p['x'] ** 2 + $p['y'] ** 2);

echo "Distance from origin: " . round($distance(['x' => 3, 'y' => 4]), 2) . "\n";


// =============================================================================
// HIGHER-ORDER FUNCTIONS WITH ARRAY SHAPES
// =============================================================================

/**
 * Function accepting closure that returns array shape
 */
function processWithTransformer(
    array $data,
    Closure $transformer
): array<array{id: int, value: string}> {
    $results = [];
    foreach ($data as $key => $value) {
        $results[] = $transformer($key, $value);
    }
    return $results;
}

$items = ['foo' => 'bar', 'baz' => 'qux'];
$transformed = processWithTransformer(
    $items,
    fn($k, $v): array{id: int, value: string} => ['id' => crc32($k), 'value' => $v]
);
echo "Transformed items: " . count($transformed) . "\n";


/**
 * Function returning a closure that uses array shapes
 */
function createValidator(array{min: int, max: int} $range): Closure {
    return function(int $value) use ($range): array{valid: bool, message: string} {
        if ($value < $range['min']) {
            return ['valid' => false, 'message' => "Value must be at least {$range['min']}"];
        }
        if ($value > $range['max']) {
            return ['valid' => false, 'message' => "Value must be at most {$range['max']}"];
        }
        return ['valid' => true, 'message' => 'OK'];
    };
}

$validateAge = createValidator(['min' => 0, 'max' => 150]);
$result = $validateAge(25);
echo "Validation: " . ($result['valid'] ? 'passed' : 'failed') . " - {$result['message']}\n";


// =============================================================================
// ARRAY_MAP WITH ARRAY SHAPES
// =============================================================================

/**
 * Using array_map with closures that have array shape types
 */
$numbers = [1, 2, 3, 4, 5];

$wrapped = array_map(
    fn(int $n): array{value: int, squared: int} => ['value' => $n, 'squared' => $n * $n],
    $numbers
);

echo "Wrapped numbers:\n";
foreach ($wrapped as $item) {
    echo "  {$item['value']} => {$item['squared']}\n";
}


// =============================================================================
// ARRAY_FILTER WITH ARRAY SHAPES
// =============================================================================

/**
 * Filtering an array of shapes
 */
$users = [
    ['id' => 1, 'name' => 'Alice', 'active' => true],
    ['id' => 2, 'name' => 'Bob', 'active' => false],
    ['id' => 3, 'name' => 'Carol', 'active' => true]
];

/** @var array<array{id: int, name: string, active: bool}> $activeUsers */
$activeUsers = array_filter(
    $users,
    fn(array{id: int, name: string, active: bool} $user): bool => $user['active']
);

echo "Active users: " . count($activeUsers) . "\n";


// =============================================================================
// ARRAY_REDUCE WITH ARRAY SHAPES
// =============================================================================

/**
 * Reducing array of shapes to a single shape
 */
$orders = [
    ['product' => 'Widget', 'qty' => 2, 'price' => 10.0],
    ['product' => 'Gadget', 'qty' => 1, 'price' => 25.0],
    ['product' => 'Thing', 'qty' => 3, 'price' => 5.0]
];

$summary = array_reduce(
    $orders,
    function(array{total_items: int, total_amount: float} $carry, array{product: string, qty: int, price: float} $item): array{total_items: int, total_amount: float} {
        return [
            'total_items' => $carry['total_items'] + $item['qty'],
            'total_amount' => $carry['total_amount'] + ($item['qty'] * $item['price'])
        ];
    },
    ['total_items' => 0, 'total_amount' => 0.0]
);

echo "Order summary: {$summary['total_items']} items, \${$summary['total_amount']}\n";


// =============================================================================
// USORT WITH ARRAY SHAPES
// =============================================================================

/**
 * Sorting array of shapes
 */
$products = [
    ['name' => 'Banana', 'price' => 0.50],
    ['name' => 'Apple', 'price' => 1.00],
    ['name' => 'Cherry', 'price' => 2.50]
];

usort($products, fn(array{name: string, price: float} $a, array{name: string, price: float} $b): int =>
    $a['price'] <=> $b['price']
);

echo "Products by price:\n";
foreach ($products as $p) {
    echo "  {$p['name']}: \${$p['price']}\n";
}


// =============================================================================
// CALLABLE RETURNING CLOSURES WITH SHAPES
// =============================================================================

/**
 * Factory function returning typed closures
 */
function createCounter(int $start = 0): Closure {
    $count = $start;
    return function() use (&$count): array{current: int, next: int} {
        $current = $count++;
        return ['current' => $current, 'next' => $count];
    };
}

$counter = createCounter(10);
$step1 = $counter();
$step2 = $counter();
echo "Counter: step1={$step1['current']}, step2={$step2['current']}, next={$step2['next']}\n";


// =============================================================================
// CLOSURE WITH USE AND ARRAY SHAPES
// =============================================================================

/**
 * Closure capturing external variables
 */
$config = ['prefix' => 'USER_', 'suffix' => '_ID'];

$formatId = function(int $id) use ($config): array{raw: int, formatted: string} {
    return [
        'raw' => $id,
        'formatted' => $config['prefix'] . $id . $config['suffix']
    ];
};

$formatted = $formatId(42);
echo "ID: {$formatted['raw']} => {$formatted['formatted']}\n";


// =============================================================================
// RECURSIVE CLOSURES WITH ARRAY SHAPES
// =============================================================================

/**
 * Recursive closure building a tree structure
 */
$buildTree = function(int $depth, string $prefix = '') use (&$buildTree): array{
    name: string,
    children: array
} {
    $name = $prefix . 'Node_' . $depth;
    $children = [];

    if ($depth > 0) {
        $children[] = $buildTree($depth - 1, $prefix . 'L');
        $children[] = $buildTree($depth - 1, $prefix . 'R');
    }

    return [
        'name' => $name,
        'children' => $children
    ];
};

$tree = $buildTree(2);
echo "Tree root: {$tree['name']}, children: " . count($tree['children']) . "\n";


// =============================================================================
// FIRST-CLASS CALLABLES WITH ARRAY SHAPES
// =============================================================================

class Formatter
{
    public function formatUser(array{name: string, email: string} $user): string {
        return "{$user['name']} <{$user['email']}>";
    }
}

$formatter = new Formatter();
$formatFn = $formatter->formatUser(...);  // First-class callable

$users = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com']
];

$formatted = array_map($formatFn, $users);
echo "Formatted users:\n";
foreach ($formatted as $f) {
    echo "  - {$f}\n";
}


echo "\n--- All closure and callable examples completed successfully! ---\n";
