<?php
/**
 * Basic Array Shape Examples
 *
 * Array shapes define the structure of associative arrays with typed keys.
 * Syntax: array{key: type, key2: type2, ...}
 */

declare(strict_arrays=1);

// =============================================================================
// BASIC SHAPE WITH REQUIRED KEYS
// =============================================================================

/**
 * Simple shape with two required keys
 */
function getPoint(): array{x: int, y: int} {
    return ['x' => 10, 'y' => 20];
}

$point = getPoint();
echo "Point: ({$point['x']}, {$point['y']})\n";


/**
 * Shape with different types
 */
function getUser(): array{id: int, name: string, balance: float, active: bool} {
    return [
        'id' => 1,
        'name' => 'Alice',
        'balance' => 100.50,
        'active' => true
    ];
}

$user = getUser();
echo "User: {$user['name']} (ID: {$user['id']}, Balance: {$user['balance']}, Active: " . ($user['active'] ? 'yes' : 'no') . ")\n";


// =============================================================================
// SHAPES AS PARAMETER TYPES
// =============================================================================

/**
 * Function accepting a shaped array as parameter
 */
function processOrder(array{product: string, quantity: int, price: float} $order): float {
    return $order['quantity'] * $order['price'];
}

$total = processOrder(['product' => 'Widget', 'quantity' => 5, 'price' => 9.99]);
echo "Order total: \${$total}\n";


/**
 * Function with both shaped parameter and return type
 */
function createInvoice(
    array{customer: string, items: int} $order
): array{invoice_id: string, customer: string, items: int, created: string} {
    return [
        'invoice_id' => uniqid('INV-'),
        'customer' => $order['customer'],
        'items' => $order['items'],
        'created' => date('Y-m-d H:i:s')
    ];
}

$invoice = createInvoice(['customer' => 'Acme Corp', 'items' => 3]);
echo "Invoice: {$invoice['invoice_id']} for {$invoice['customer']}\n";


// =============================================================================
// SINGLE KEY SHAPES
// =============================================================================

/**
 * Shape with just one key (useful for wrapper types)
 */
function wrapValue(): array{value: mixed} {
    return ['value' => 'anything goes here'];
}

$wrapped = wrapValue();
echo "Wrapped value: {$wrapped['value']}\n";


// =============================================================================
// SHAPES WITH MANY KEYS
// =============================================================================

/**
 * Shape with many keys (complex data structure)
 */
function getFullProfile(): array{
    id: int,
    username: string,
    email: string,
    first_name: string,
    last_name: string,
    age: int,
    verified: bool,
    score: float
} {
    return [
        'id' => 42,
        'username' => 'johndoe',
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'age' => 30,
        'verified' => true,
        'score' => 95.5
    ];
}

$profile = getFullProfile();
echo "Profile: {$profile['first_name']} {$profile['last_name']} ({$profile['username']})\n";


echo "\n--- All basic shape examples completed successfully! ---\n";
