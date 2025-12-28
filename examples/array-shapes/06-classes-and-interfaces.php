<?php
/**
 * Array Shapes with Classes, Interfaces, and Traits
 *
 * Array shapes can be used in class methods, properties, interfaces, and traits.
 */

declare(strict_arrays=1);

// =============================================================================
// CLASS METHODS WITH ARRAY SHAPES
// =============================================================================

class UserRepository
{
    /**
     * Method returning an array shape
     */
    public function findById(int $id): array{id: int, name: string, email: string} {
        return [
            'id' => $id,
            'name' => 'User ' . $id,
            'email' => "user{$id}@example.com"
        ];
    }

    /**
     * Method accepting array shape as parameter
     */
    public function create(array{name: string, email: string, password?: string} $data): array{id: int, name: string, email: string} {
        return [
            'id' => rand(1000, 9999),
            'name' => $data['name'],
            'email' => $data['email']
        ];
    }

    /**
     * Method returning array of shapes
     */
    public function findAll(): array<array{id: int, name: string, email: string}> {
        return [
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com']
        ];
    }
}

$repo = new UserRepository();
$user = $repo->findById(42);
echo "Found user: {$user['name']} <{$user['email']}>\n";

$newUser = $repo->create(['name' => 'Carol', 'email' => 'carol@example.com']);
echo "Created user: {$newUser['name']} (ID: {$newUser['id']})\n";

$all = $repo->findAll();
echo "All users: " . count($all) . "\n";


// =============================================================================
// INTERFACE WITH ARRAY SHAPES
// =============================================================================

interface ConfigProviderInterface
{
    /**
     * Interface method with shape return type
     */
    public function getConfig(): array{
        debug: bool,
        environment: string,
        features: array<string>
    };

    /**
     * Interface method with shape parameter
     */
    public function setConfig(array{
        debug?: bool,
        environment?: string,
        features?: array<string>
    } $config): void;
}

class AppConfigProvider implements ConfigProviderInterface
{
    private bool $debug = false;
    private string $environment = 'production';
    private array $features = [];

    public function getConfig(): array{
        debug: bool,
        environment: string,
        features: array<string>
    } {
        return [
            'debug' => $this->debug,
            'environment' => $this->environment,
            'features' => $this->features
        ];
    }

    public function setConfig(array{
        debug?: bool,
        environment?: string,
        features?: array<string>
    } $config): void {
        if (isset($config['debug'])) {
            $this->debug = $config['debug'];
        }
        if (isset($config['environment'])) {
            $this->environment = $config['environment'];
        }
        if (isset($config['features'])) {
            $this->features = $config['features'];
        }
    }
}

$configProvider = new AppConfigProvider();
$configProvider->setConfig(['debug' => true, 'environment' => 'development']);
$config = $configProvider->getConfig();
echo "Config - debug: " . ($config['debug'] ? 'yes' : 'no') . ", env: {$config['environment']}\n";


// =============================================================================
// ABSTRACT CLASS WITH ARRAY SHAPES
// =============================================================================

abstract class BaseApiClient
{
    /**
     * Abstract method with shape return type
     */
    abstract public function request(string $method, string $endpoint): array{
        status: int,
        body: mixed,
        headers: array<string>
    };

    /**
     * Concrete method using shapes
     */
    protected function buildResponse(
        int $status,
        mixed $body
    ): array{status: int, body: mixed, headers: array<string>} {
        return [
            'status' => $status,
            'body' => $body,
            'headers' => ['Content-Type: application/json']
        ];
    }
}

class MockApiClient extends BaseApiClient
{
    public function request(string $method, string $endpoint): array{
        status: int,
        body: mixed,
        headers: array<string>
    } {
        return $this->buildResponse(200, ['success' => true]);
    }
}

$client = new MockApiClient();
$response = $client->request('GET', '/users');
echo "API response status: {$response['status']}\n";


// =============================================================================
// TRAIT WITH ARRAY SHAPES
// =============================================================================

trait Auditable
{
    /**
     * Trait method returning shape
     */
    public function getAuditInfo(): array{
        created_at: string,
        updated_at: string,
        created_by: ?int,
        updated_by: ?int
    } {
        return [
            'created_at' => '2024-01-15 10:00:00',
            'updated_at' => '2024-01-16 15:30:00',
            'created_by' => 1,
            'updated_by' => 2
        ];
    }
}

class Document
{
    use Auditable;

    public function __construct(
        public string $title
    ) {}
}

$doc = new Document('My Document');
$audit = $doc->getAuditInfo();
echo "Document '{$doc->title}' created at: {$audit['created_at']}\n";


// =============================================================================
// STATIC METHODS WITH ARRAY SHAPES
// =============================================================================

class Factory
{
    /**
     * Static factory method returning shape
     */
    public static function createProduct(
        string $name,
        float $price
    ): array{id: string, name: string, price: float, created: string} {
        return [
            'id' => uniqid('prod_'),
            'name' => $name,
            'price' => $price,
            'created' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Static method returning array of shapes
     */
    public static function createBatch(
        array<string> $names,
        float $price
    ): array<array{id: string, name: string, price: float, created: string}> {
        $products = [];
        foreach ($names as $name) {
            $products[] = self::createProduct($name, $price);
        }
        return $products;
    }
}

$product = Factory::createProduct('Widget', 9.99);
echo "Product: {$product['name']} - \${$product['price']}\n";

$batch = Factory::createBatch(['A', 'B', 'C'], 5.99);
echo "Batch created: " . count($batch) . " products\n";


// =============================================================================
// CLASS WITH TYPED PROPERTIES (Array Shapes in Properties)
// =============================================================================

class Order
{
    /**
     * Property with array shape type
     */
    public array $customer; // Type enforced at runtime when strict_arrays=1

    /**
     * Property with typed array of shapes
     */
    public array $items;

    public function __construct(
        array $customer,
        array $items
    ) {
        $this->customer = $customer;
        $this->items = $items;
    }

    public function getCustomer(): array{name: string, email: string} {
        return $this->customer;
    }

    public function getItems(): array<array{sku: string, qty: int, price: float}> {
        return $this->items;
    }

    public function getTotal(): float {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item['qty'] * $item['price'];
        }
        return $total;
    }
}

$order = new Order(
    ['name' => 'John', 'email' => 'john@example.com'],
    [
        ['sku' => 'ABC', 'qty' => 2, 'price' => 10.00],
        ['sku' => 'DEF', 'qty' => 1, 'price' => 25.00]
    ]
);
echo "Order for: {$order->getCustomer()['name']}\n";
echo "Total: \${$order->getTotal()}\n";


// =============================================================================
// FLUENT INTERFACE WITH ARRAY SHAPES
// =============================================================================

class QueryBuilder
{
    private array $conditions = [];
    private array $orderBy = [];
    private ?int $limit = null;

    public function where(array{field: string, operator: string, value: mixed} $condition): self {
        $this->conditions[] = $condition;
        return $this;
    }

    public function orderBy(array{field: string, direction: string} $order): self {
        $this->orderBy[] = $order;
        return $this;
    }

    public function limit(int $limit): self {
        $this->limit = $limit;
        return $this;
    }

    public function build(): array{
        conditions: array<array{field: string, operator: string, value: mixed}>,
        order_by: array<array{field: string, direction: string}>,
        limit: ?int
    } {
        return [
            'conditions' => $this->conditions,
            'order_by' => $this->orderBy,
            'limit' => $this->limit
        ];
    }
}

$query = (new QueryBuilder())
    ->where(['field' => 'status', 'operator' => '=', 'value' => 'active'])
    ->where(['field' => 'age', 'operator' => '>', 'value' => 18])
    ->orderBy(['field' => 'name', 'direction' => 'ASC'])
    ->limit(10)
    ->build();

echo "Query - conditions: " . count($query['conditions']) . ", limit: {$query['limit']}\n";


echo "\n--- All class and interface examples completed successfully! ---\n";
