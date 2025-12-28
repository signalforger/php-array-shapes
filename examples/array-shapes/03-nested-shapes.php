<?php
/**
 * Nested Array Shapes
 *
 * Array shapes can be nested within each other to define complex structures.
 * Syntax: array{key: array{nested_key: type}}
 */

declare(strict_arrays=1);

// =============================================================================
// SIMPLE NESTING
// =============================================================================

/**
 * Shape with one level of nesting
 */
function getUserWithAddress(): array{
    name: string,
    address: array{street: string, city: string}
} {
    return [
        'name' => 'Alice',
        'address' => [
            'street' => '123 Main St',
            'city' => 'Boston'
        ]
    ];
}

$user = getUserWithAddress();
echo "User: {$user['name']}, Lives at: {$user['address']['street']}, {$user['address']['city']}\n";


// =============================================================================
// DEEP NESTING
// =============================================================================

/**
 * Shape with multiple levels of nesting
 */
function getCompanyInfo(): array{
    name: string,
    headquarters: array{
        address: array{
            street: string,
            city: string,
            country: string
        },
        contact: array{
            phone: string,
            email: string
        }
    }
} {
    return [
        'name' => 'Acme Corp',
        'headquarters' => [
            'address' => [
                'street' => '456 Corporate Blvd',
                'city' => 'New York',
                'country' => 'USA'
            ],
            'contact' => [
                'phone' => '+1-555-0123',
                'email' => 'info@acme.com'
            ]
        ]
    ];
}

$company = getCompanyInfo();
echo "Company: {$company['name']}\n";
echo "  Location: {$company['headquarters']['address']['city']}, {$company['headquarters']['address']['country']}\n";
echo "  Contact: {$company['headquarters']['contact']['email']}\n";


// =============================================================================
// NESTED WITH OPTIONAL KEYS
// =============================================================================

/**
 * Nested shapes with optional keys at various levels
 */
function getProfile(): array{
    user: array{
        id: int,
        name: string,
        email?: string
    },
    settings?: array{
        theme?: string,
        notifications?: bool
    }
} {
    return [
        'user' => [
            'id' => 1,
            'name' => 'Bob'
            // email is optional
        ]
        // settings is optional
    ];
}

/**
 * Same shape with all optional keys filled
 */
function getFullProfile(): array{
    user: array{
        id: int,
        name: string,
        email?: string
    },
    settings?: array{
        theme?: string,
        notifications?: bool
    }
} {
    return [
        'user' => [
            'id' => 2,
            'name' => 'Carol',
            'email' => 'carol@example.com'
        ],
        'settings' => [
            'theme' => 'dark',
            'notifications' => true
        ]
    ];
}

$profile1 = getProfile();
$profile2 = getFullProfile();
echo "Profile 1: {$profile1['user']['name']}, email: " . ($profile1['user']['email'] ?? 'not set') . "\n";
echo "Profile 2: {$profile2['user']['name']}, theme: {$profile2['settings']['theme']}\n";


// =============================================================================
// MULTIPLE NESTED STRUCTURES
// =============================================================================

/**
 * Shape with multiple nested structures at the same level
 */
function getOrder(): array{
    order_id: string,
    customer: array{name: string, email: string},
    shipping: array{address: string, city: string},
    billing: array{address: string, city: string},
    items: array{count: int, total: float}
} {
    return [
        'order_id' => 'ORD-001',
        'customer' => ['name' => 'Dave', 'email' => 'dave@example.com'],
        'shipping' => ['address' => '789 Ship Lane', 'city' => 'Portland'],
        'billing' => ['address' => '789 Ship Lane', 'city' => 'Portland'],
        'items' => ['count' => 3, 'total' => 149.99]
    ];
}

$order = getOrder();
echo "Order {$order['order_id']} for {$order['customer']['name']}\n";
echo "  Ship to: {$order['shipping']['city']}\n";
echo "  Total: \${$order['items']['total']} ({$order['items']['count']} items)\n";


// =============================================================================
// NESTED SHAPE AS PARAMETER
// =============================================================================

/**
 * Function accepting nested shape as parameter
 */
function processPayment(array{
    amount: float,
    card: array{
        number: string,
        expiry: string,
        cvv: string
    },
    billing: array{
        name: string,
        address: string
    }
} $payment): array{success: bool, transaction_id: string} {
    // Simulate payment processing
    return [
        'success' => true,
        'transaction_id' => 'TXN-' . substr(md5($payment['card']['number']), 0, 8)
    ];
}

$result = processPayment([
    'amount' => 99.99,
    'card' => [
        'number' => '4111111111111111',
        'expiry' => '12/25',
        'cvv' => '123'
    ],
    'billing' => [
        'name' => 'Eve Smith',
        'address' => '321 Pay St'
    ]
]);
echo "Payment result: " . ($result['success'] ? 'Success' : 'Failed') . ", ID: {$result['transaction_id']}\n";


// =============================================================================
// DEEPLY NESTED REAL-WORLD EXAMPLE
// =============================================================================

/**
 * Complex nested structure: API configuration
 */
function getApiConfig(): array{
    api: array{
        version: string,
        endpoints: array{
            base: string,
            auth: string
        }
    },
    auth: array{
        type: string,
        credentials: array{
            key: string,
            secret?: string
        }
    },
    options: array{
        timeout: int,
        retry: array{
            enabled: bool,
            max_attempts: int
        }
    }
} {
    return [
        'api' => [
            'version' => 'v2',
            'endpoints' => [
                'base' => 'https://api.example.com',
                'auth' => 'https://auth.example.com'
            ]
        ],
        'auth' => [
            'type' => 'api_key',
            'credentials' => [
                'key' => 'my-api-key'
            ]
        ],
        'options' => [
            'timeout' => 30,
            'retry' => [
                'enabled' => true,
                'max_attempts' => 3
            ]
        ]
    ];
}

$config = getApiConfig();
echo "API Config:\n";
echo "  Version: {$config['api']['version']}\n";
echo "  Base URL: {$config['api']['endpoints']['base']}\n";
echo "  Auth type: {$config['auth']['type']}\n";
echo "  Timeout: {$config['options']['timeout']}s\n";
echo "  Retry enabled: " . ($config['options']['retry']['enabled'] ? 'yes' : 'no') . "\n";


echo "\n--- All nested shape examples completed successfully! ---\n";
