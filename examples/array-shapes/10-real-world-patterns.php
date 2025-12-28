<?php
/**
 * Real-World Patterns and Use Cases for Array Shapes
 *
 * Common patterns you'll encounter in production applications.
 */

declare(strict_arrays=1);

// =============================================================================
// PATTERN 1: API RESPONSES
// =============================================================================

echo "=== API Response Pattern ===\n";

/**
 * Standard API response wrapper
 */
function apiSuccess(mixed $data): array{
    success: bool,
    data: mixed,
    error: ?string,
    meta: array{timestamp: string, version: string}
} {
    return [
        'success' => true,
        'data' => $data,
        'error' => null,
        'meta' => [
            'timestamp' => date('c'),
            'version' => '1.0'
        ]
    ];
}

function apiError(string $message, int $code = 500): array{
    success: bool,
    data: ?array,
    error: string,
    meta: array{timestamp: string, code: int}
} {
    return [
        'success' => false,
        'data' => null,
        'error' => $message,
        'meta' => [
            'timestamp' => date('c'),
            'code' => $code
        ]
    ];
}

$success = apiSuccess(['user' => ['id' => 1, 'name' => 'Alice']]);
$error = apiError('User not found', 404);
echo "Success response: " . json_encode($success) . "\n";
echo "Error response: " . json_encode($error) . "\n\n";


// =============================================================================
// PATTERN 2: CONFIGURATION OBJECTS
// =============================================================================

echo "=== Configuration Pattern ===\n";

/**
 * Database configuration
 */
function getDatabaseConfig(): array{
    driver: string,
    host: string,
    port: int,
    database: string,
    username: string,
    password: string,
    options?: array{
        charset?: string,
        collation?: string,
        timeout?: int,
        persistent?: bool
    }
} {
    return [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'myapp',
        'username' => 'root',
        'password' => 'secret',
        'options' => [
            'charset' => 'utf8mb4',
            'timeout' => 30
        ]
    ];
}

/**
 * Cache configuration
 */
function getCacheConfig(): array{
    driver: string,
    prefix: string,
    ttl: int,
    redis?: array{host: string, port: int, password?: string},
    memcached?: array{servers: array<array{host: string, port: int}>}
} {
    return [
        'driver' => 'redis',
        'prefix' => 'myapp_',
        'ttl' => 3600,
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379
        ]
    ];
}

$dbConfig = getDatabaseConfig();
$cacheConfig = getCacheConfig();
echo "DB: {$dbConfig['driver']}://{$dbConfig['host']}:{$dbConfig['port']}/{$dbConfig['database']}\n";
echo "Cache: {$cacheConfig['driver']} (prefix: {$cacheConfig['prefix']}, ttl: {$cacheConfig['ttl']}s)\n\n";


// =============================================================================
// PATTERN 3: DATA TRANSFER OBJECTS (DTOs) AS SHAPES
// =============================================================================

echo "=== DTO Pattern ===\n";

/**
 * User DTO
 */
function createUserDTO(
    int $id,
    string $name,
    string $email
): array{id: int, name: string, email: string, created_at: string} {
    return [
        'id' => $id,
        'name' => $name,
        'email' => $email,
        'created_at' => date('c')
    ];
}

/**
 * Order DTO with nested structures
 */
function createOrderDTO(
    string $orderId,
    array{id: int, name: string, email: string} $customer,
    array<array{sku: string, name: string, qty: int, price: float}> $items
): array{
    order_id: string,
    customer: array{id: int, name: string, email: string},
    items: array<array{sku: string, name: string, qty: int, price: float}>,
    total: float,
    created_at: string
} {
    $total = array_reduce($items, fn($sum, $item) => $sum + ($item['qty'] * $item['price']), 0.0);

    return [
        'order_id' => $orderId,
        'customer' => $customer,
        'items' => $items,
        'total' => $total,
        'created_at' => date('c')
    ];
}

$user = createUserDTO(1, 'Alice', 'alice@example.com');
$order = createOrderDTO(
    'ORD-001',
    $user,
    [
        ['sku' => 'WIDGET-A', 'name' => 'Widget A', 'qty' => 2, 'price' => 10.00],
        ['sku' => 'GADGET-B', 'name' => 'Gadget B', 'qty' => 1, 'price' => 25.00]
    ]
);
echo "Order {$order['order_id']} for {$order['customer']['name']}: \${$order['total']}\n\n";


// =============================================================================
// PATTERN 4: EVENT PAYLOADS
// =============================================================================

echo "=== Event Payload Pattern ===\n";

/**
 * User registered event
 */
function userRegisteredEvent(
    int $userId,
    string $email
): array{event: string, payload: array{user_id: int, email: string}, timestamp: string} {
    return [
        'event' => 'user.registered',
        'payload' => [
            'user_id' => $userId,
            'email' => $email
        ],
        'timestamp' => date('c')
    ];
}

/**
 * Order placed event
 */
function orderPlacedEvent(
    string $orderId,
    int $customerId,
    float $total
): array{event: string, payload: array{order_id: string, customer_id: int, total: float}, timestamp: string} {
    return [
        'event' => 'order.placed',
        'payload' => [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'total' => $total
        ],
        'timestamp' => date('c')
    ];
}

$regEvent = userRegisteredEvent(42, 'new@example.com');
$orderEvent = orderPlacedEvent('ORD-123', 42, 99.99);
echo "Event: {$regEvent['event']} at {$regEvent['timestamp']}\n";
echo "Event: {$orderEvent['event']} - Order {$orderEvent['payload']['order_id']}\n\n";


// =============================================================================
// PATTERN 5: FORM DATA VALIDATION
// =============================================================================

echo "=== Form Validation Pattern ===\n";

/**
 * Registration form data
 */
function validateRegistrationForm(array $input): array{
    valid: bool,
    data?: array{name: string, email: string, password: string},
    errors: array<string>
} {
    $errors = [];

    if (empty($input['name'])) {
        $errors[] = 'Name is required';
    }
    if (empty($input['email']) || !str_contains($input['email'], '@')) {
        $errors[] = 'Valid email is required';
    }
    if (empty($input['password']) || strlen($input['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }

    if (empty($errors)) {
        return [
            'valid' => true,
            'data' => [
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password']
            ],
            'errors' => []
        ];
    }

    return [
        'valid' => false,
        'errors' => $errors
    ];
}

$validForm = validateRegistrationForm([
    'name' => 'Alice',
    'email' => 'alice@example.com',
    'password' => 'securepassword'
]);

$invalidForm = validateRegistrationForm([
    'name' => '',
    'email' => 'not-an-email',
    'password' => 'short'
]);

echo "Valid form: " . ($validForm['valid'] ? 'yes' : 'no') . "\n";
echo "Invalid form errors: " . implode(', ', $invalidForm['errors']) . "\n\n";


// =============================================================================
// PATTERN 6: PAGINATION RESULTS
// =============================================================================

echo "=== Pagination Pattern ===\n";

/**
 * Paginated result wrapper
 */
function paginatedResult(
    array $items,
    int $page,
    int $perPage,
    int $total
): array{
    data: array,
    pagination: array{
        current_page: int,
        per_page: int,
        total_items: int,
        total_pages: int,
        has_next: bool,
        has_prev: bool
    }
} {
    $totalPages = (int) ceil($total / $perPage);

    return [
        'data' => $items,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_items' => $total,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
}

$result = paginatedResult(
    [['id' => 1], ['id' => 2], ['id' => 3]],
    2, 10, 45
);
echo "Page {$result['pagination']['current_page']} of {$result['pagination']['total_pages']}\n";
echo "Has next: " . ($result['pagination']['has_next'] ? 'yes' : 'no') . "\n\n";


// =============================================================================
// PATTERN 7: REPOSITORY METHODS
// =============================================================================

echo "=== Repository Pattern ===\n";

class ProductRepository
{
    private array $products = [];

    public function __construct()
    {
        $this->products = [
            ['id' => 1, 'sku' => 'PROD-001', 'name' => 'Widget', 'price' => 9.99, 'stock' => 100],
            ['id' => 2, 'sku' => 'PROD-002', 'name' => 'Gadget', 'price' => 19.99, 'stock' => 50],
            ['id' => 3, 'sku' => 'PROD-003', 'name' => 'Thing', 'price' => 29.99, 'stock' => 25]
        ];
    }

    public function findById(int $id): ?array {
        foreach ($this->products as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }
        return null;
    }

    public function findAll(): array<array{id: int, sku: string, name: string, price: float, stock: int}> {
        return $this->products;
    }

    public function findBySku(string $sku): ?array {
        foreach ($this->products as $product) {
            if ($product['sku'] === $sku) {
                return $product;
            }
        }
        return null;
    }

    public function search(
        array{min_price?: float, max_price?: float, in_stock?: bool} $criteria
    ): array<array{id: int, sku: string, name: string, price: float, stock: int}> {
        return array_filter($this->products, function($p) use ($criteria) {
            if (isset($criteria['min_price']) && $p['price'] < $criteria['min_price']) {
                return false;
            }
            if (isset($criteria['max_price']) && $p['price'] > $criteria['max_price']) {
                return false;
            }
            if (isset($criteria['in_stock']) && $criteria['in_stock'] && $p['stock'] <= 0) {
                return false;
            }
            return true;
        });
    }
}

$repo = new ProductRepository();
$all = $repo->findAll();
$searched = $repo->search(['min_price' => 15.00, 'in_stock' => true]);

echo "All products: " . count($all) . "\n";
echo "Filtered products: " . count($searched) . "\n\n";


// =============================================================================
// PATTERN 8: SERVICE LAYER RESPONSES
// =============================================================================

echo "=== Service Layer Pattern ===\n";

class PaymentService
{
    public function processPayment(
        array{amount: float, currency: string, card_token: string} $payment
    ): array{
        success: bool,
        transaction_id: ?string,
        error_code: ?string,
        error_message: ?string
    } {
        // Simulate payment processing
        if ($payment['amount'] <= 0) {
            return [
                'success' => false,
                'transaction_id' => null,
                'error_code' => 'INVALID_AMOUNT',
                'error_message' => 'Amount must be greater than zero'
            ];
        }

        return [
            'success' => true,
            'transaction_id' => 'TXN-' . uniqid(),
            'error_code' => null,
            'error_message' => null
        ];
    }

    public function refund(
        string $transactionId,
        float $amount
    ): array{success: bool, refund_id: ?string, error: ?string} {
        return [
            'success' => true,
            'refund_id' => 'REF-' . uniqid(),
            'error' => null
        ];
    }
}

$paymentService = new PaymentService();
$result = $paymentService->processPayment([
    'amount' => 99.99,
    'currency' => 'USD',
    'card_token' => 'tok_visa'
]);

echo "Payment: " . ($result['success'] ? "Success - {$result['transaction_id']}" : "Failed - {$result['error_message']}") . "\n\n";


// =============================================================================
// PATTERN 9: BUILDER PATTERN WITH SHAPES
// =============================================================================

echo "=== Builder Pattern ===\n";

class EmailBuilder
{
    private array $data = [
        'to' => [],
        'cc' => [],
        'bcc' => [],
        'subject' => '',
        'body' => '',
        'attachments' => []
    ];

    public function to(string $email, string $name = ''): self {
        $this->data['to'][] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function cc(string $email): self {
        $this->data['cc'][] = $email;
        return $this;
    }

    public function subject(string $subject): self {
        $this->data['subject'] = $subject;
        return $this;
    }

    public function body(string $body): self {
        $this->data['body'] = $body;
        return $this;
    }

    public function attach(array{filename: string, content: string, mime: string} $attachment): self {
        $this->data['attachments'][] = $attachment;
        return $this;
    }

    public function build(): array{
        to: array<array{email: string, name: string}>,
        cc: array<string>,
        bcc: array<string>,
        subject: string,
        body: string,
        attachments: array<array{filename: string, content: string, mime: string}>
    } {
        return $this->data;
    }
}

$email = (new EmailBuilder())
    ->to('alice@example.com', 'Alice')
    ->to('bob@example.com', 'Bob')
    ->cc('manager@example.com')
    ->subject('Weekly Report')
    ->body('Please find attached the weekly report.')
    ->attach(['filename' => 'report.pdf', 'content' => '...', 'mime' => 'application/pdf'])
    ->build();

echo "Email to: " . count($email['to']) . " recipients, Subject: {$email['subject']}\n\n";


// =============================================================================
// PATTERN 10: JSON API RESOURCES
// =============================================================================

echo "=== JSON:API Resource Pattern ===\n";

/**
 * JSON:API compliant resource wrapper
 */
function jsonApiResource(
    string $type,
    string|int $id,
    array $attributes,
    array $relationships = []
): array{
    type: string,
    id: string,
    attributes: array,
    relationships: array,
    links: array{self: string}
} {
    return [
        'type' => $type,
        'id' => (string) $id,
        'attributes' => $attributes,
        'relationships' => $relationships,
        'links' => [
            'self' => "/{$type}/{$id}"
        ]
    ];
}

/**
 * JSON:API collection wrapper
 */
function jsonApiCollection(
    array $resources,
    array{page: int, per_page: int, total: int} $meta
): array{
    data: array,
    meta: array{page: int, per_page: int, total: int, total_pages: int}
} {
    return [
        'data' => $resources,
        'meta' => [
            'page' => $meta['page'],
            'per_page' => $meta['per_page'],
            'total' => $meta['total'],
            'total_pages' => (int) ceil($meta['total'] / $meta['per_page'])
        ]
    ];
}

$userResource = jsonApiResource('users', 1, ['name' => 'Alice', 'email' => 'alice@example.com']);
$collection = jsonApiCollection(
    [$userResource],
    ['page' => 1, 'per_page' => 10, 'total' => 1]
);

echo "Resource type: {$userResource['type']}, id: {$userResource['id']}\n";
echo "Collection total: {$collection['meta']['total']}\n\n";


echo "--- All real-world pattern examples completed successfully! ---\n";
