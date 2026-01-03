<?php
/**
 * PHP Typed Arrays & Array Shapes - Examples
 *
 * Focus: Typed collections with classes (array<ClassName>)
 *
 * Run with: ./php-src/sapi/cli/php examples.php
 */

echo "=== PHP Typed Arrays & Array Shapes ===\n\n";

// =============================================================================
// DTOs / Value Objects
// =============================================================================

class User {
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email
    ) {}
}

class Product {
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly float $price
    ) {}
}

class OrderItem {
    public function __construct(
        public readonly Product $product,
        public readonly int $quantity
    ) {}

    public function getSubtotal(): float {
        return $this->product->price * $this->quantity;
    }
}

// =============================================================================
// 1. Typed Collection: array<User>
// =============================================================================

function getUsers(): array<User> {
    return [
        new User(1, 'Alice', 'alice@example.com'),
        new User(2, 'Bob', 'bob@example.com'),
        new User(3, 'Charlie', 'charlie@example.com'),
    ];
}

echo "1. array<User> - Collection of User objects:\n";
foreach (getUsers() as $user) {
    echo "   - {$user->name} ({$user->email})\n";
}
echo "\n";

// =============================================================================
// 2. Typed Collection: array<Product>
// =============================================================================

function getProducts(): array<Product> {
    return [
        new Product(101, 'Laptop', 999.99),
        new Product(102, 'Mouse', 29.99),
        new Product(103, 'Keyboard', 79.99),
    ];
}

echo "2. array<Product> - Collection of Product objects:\n";
foreach (getProducts() as $product) {
    echo "   - {$product->name}: \${$product->price}\n";
}
echo "\n";

// =============================================================================
// 3. Typed Collection as Parameter
// =============================================================================

function calculateTotal(array<OrderItem> $items): float {
    $total = 0.0;
    foreach ($items as $item) {
        $total += $item->getSubtotal();
    }
    return $total;
}

$orderItems = [
    new OrderItem(new Product(1, 'Widget', 10.00), 3),
    new OrderItem(new Product(2, 'Gadget', 25.00), 2),
];

echo "3. array<OrderItem> as parameter:\n";
echo "   Order total: \$" . calculateTotal($orderItems) . "\n\n";

// =============================================================================
// 4. Repository Pattern with Typed Collections
// =============================================================================

class UserRepository {
    private array $users = [];

    public function __construct() {
        $this->users = [
            1 => new User(1, 'Alice', 'alice@example.com'),
            2 => new User(2, 'Bob', 'bob@example.com'),
        ];
    }

    public function findAll(): array<User> {
        return array_values($this->users);
    }

    public function findById(int $id): ?User {
        return $this->users[$id] ?? null;
    }

    public function findByIds(array<int> $ids): array<User> {
        $result = [];
        foreach ($ids as $id) {
            if (isset($this->users[$id])) {
                $result[] = $this->users[$id];
            }
        }
        return $result;
    }
}

$repo = new UserRepository();

echo "4. Repository pattern with array<User>:\n";
echo "   findAll(): " . count($repo->findAll()) . " users\n";
echo "   findByIds([1, 2]): " . count($repo->findByIds([1, 2])) . " users\n\n";

// =============================================================================
// 5. Service Layer with Typed Collections
// =============================================================================

class UserService {
    public function __construct(
        private UserRepository $repository
    ) {}

    public function getActiveUsers(): array<User> {
        // In real code, this would filter by active status
        return $this->repository->findAll();
    }

    public function transformUsers(array<User> $users): array<array{id: int, displayName: string}> {
        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'id' => $user->id,
                'displayName' => strtoupper($user->name),
            ];
        }
        return $result;
    }
}

$service = new UserService($repo);

echo "5. Service layer transforming array<User> to array<shape>:\n";
foreach ($service->transformUsers($repo->findAll()) as $data) {
    echo "   - ID {$data['id']}: {$data['displayName']}\n";
}
echo "\n";

// =============================================================================
// 6. Map Type: array<string, User>
// =============================================================================

function getUsersByEmail(): array<string, User> {
    return [
        'alice@example.com' => new User(1, 'Alice', 'alice@example.com'),
        'bob@example.com' => new User(2, 'Bob', 'bob@example.com'),
    ];
}

echo "6. array<string, User> - Map with email keys:\n";
$userMap = getUsersByEmail();
echo "   alice@example.com -> {$userMap['alice@example.com']->name}\n\n";

// =============================================================================
// 7. Combining Typed Arrays with Shapes
// =============================================================================

function getApiResponse(): array{
    success: bool,
    users: array<User>,
    total: int
} {
    $users = getUsers();
    return [
        'success' => true,
        'users' => $users,
        'total' => count($users),
    ];
}

echo "7. Shape containing array<User>:\n";
$response = getApiResponse();
echo "   success: " . ($response['success'] ? 'true' : 'false') . "\n";
echo "   total: {$response['total']} users\n\n";

// =============================================================================
// 8. Type Error Example (commented out - would throw TypeError)
// =============================================================================

echo "8. Type safety - these would throw TypeError:\n";
echo "   - Returning string in array<int>\n";
echo "   - Returning stdClass in array<User>\n";
echo "   - Missing required shape key\n\n";

// Uncomment to see the error:
// function brokenGetUsers(): array<User> {
//     return [new User(1, 'Alice', 'a@b.com'), new stdClass()]; // TypeError!
// }

// =============================================================================
// Summary
// =============================================================================

echo "=== All examples completed! ===\n";
echo "\nKey patterns demonstrated:\n";
echo "  - array<ClassName>     : Typed collection of objects\n";
echo "  - array<string, Class> : Map with typed values\n";
echo "  - array<int>           : Primitive typed collection\n";
echo "  - array{key: type}     : Structured data shapes\n";
