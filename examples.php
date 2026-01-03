<?php
/**
 * PHP Typed Arrays & Array Shapes - Examples
 *
 * Two complementary features:
 * 1. Typed Collections: array<ClassName> for lists of objects
 * 2. Array Shapes: array{key: type} for structured data from external sources
 *
 * Run with: ./php-src/sapi/cli/php examples.php
 */

echo "=== PHP Typed Arrays & Array Shapes ===\n\n";

// =============================================================================
// PART 1: TYPED COLLECTIONS — array<ClassName>
// =============================================================================

echo "--- PART 1: Typed Collections (array<ClassName>) ---\n\n";

// DTOs for internal domain logic
class User {
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email
    ) {}

    public function getDisplayName(): string {
        return strtoupper($this->name);
    }
}

class Product {
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly float $price
    ) {}
}

// -----------------------------------------------------------------------------
// 1. Typed Collection: array<User>
// -----------------------------------------------------------------------------

function getUsers(): array<User> {
    return [
        new User(1, 'Alice', 'alice@example.com'),
        new User(2, 'Bob', 'bob@example.com'),
        new User(3, 'Charlie', 'charlie@example.com'),
    ];
}

echo "1. array<User> - Collection of DTOs:\n";
foreach (getUsers() as $user) {
    echo "   - {$user->getDisplayName()} ({$user->email})\n";
}
echo "\n";

// -----------------------------------------------------------------------------
// 2. Repository returning typed collection
// -----------------------------------------------------------------------------

class UserRepository {
    private array $users;

    public function __construct() {
        $this->users = [
            1 => new User(1, 'Alice', 'alice@example.com'),
            2 => new User(2, 'Bob', 'bob@example.com'),
        ];
    }

    public function findAll(): array<User> {
        return array_values($this->users);
    }

    public function findByIds(array<int> $ids): array<User> {
        return array_filter(
            $this->users,
            fn($u) => in_array($u->id, $ids)
        );
    }
}

$repo = new UserRepository();
echo "2. Repository with array<User>:\n";
echo "   findAll(): " . count($repo->findAll()) . " users\n";
echo "   findByIds([1]): " . count($repo->findByIds([1])) . " user\n\n";

// =============================================================================
// PART 2: ARRAY SHAPES — For External Data (APIs, Databases)
// =============================================================================

echo "--- PART 2: Array Shapes (External Data Boundaries) ---\n\n";

// -----------------------------------------------------------------------------
// 3. Database query result - PDO returns arrays, not objects
// -----------------------------------------------------------------------------

function fetchUserFromDb(int $id): array{id: int, name: string, email: string, created_at: string} {
    // Simulating: $stmt->fetch(PDO::FETCH_ASSOC)
    return [
        'id' => $id,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'created_at' => '2024-01-15 10:30:00'
    ];
}

echo "3. Database result (array shape):\n";
$dbUser = fetchUserFromDb(1);
echo "   Fetched: {$dbUser['name']} (ID: {$dbUser['id']})\n\n";

// -----------------------------------------------------------------------------
// 4. API response - json_decode returns arrays
// -----------------------------------------------------------------------------

function fetchGitHubUser(string $username): array{login: string, id: int, name: ?string, public_repos: int} {
    // Simulating: json_decode($response, true)
    return [
        'login' => $username,
        'id' => 12345,
        'name' => 'Alice Developer',
        'public_repos' => 42
    ];
}

echo "4. API response (array shape):\n";
$ghUser = fetchGitHubUser('alice');
echo "   GitHub user: {$ghUser['login']} has {$ghUser['public_repos']} repos\n\n";

// -----------------------------------------------------------------------------
// 5. Webhook payload - external data you don't control
// -----------------------------------------------------------------------------

function handleWebhook(array{event: string, data: array{id: string, amount: int}} $payload): string {
    return "Received {$payload['event']} for {$payload['data']['id']}: \${$payload['data']['amount']}";
}

$webhookPayload = [
    'event' => 'payment.completed',
    'data' => ['id' => 'pay_123', 'amount' => 5000]
];

echo "5. Webhook payload (array shape):\n";
echo "   " . handleWebhook($webhookPayload) . "\n\n";

// =============================================================================
// PART 3: THE BOUNDARY PATTERN — Arrays In, DTOs Inside
// =============================================================================

echo "--- PART 3: The Boundary Pattern ---\n\n";

// -----------------------------------------------------------------------------
// 6. Convert array shape to DTO at the boundary
// -----------------------------------------------------------------------------

class UserService {
    // External boundary: array shape from API/database
    private function fetchFromApi(int $id): array{id: int, name: string, email: string} {
        // Simulating external API call
        return ['id' => $id, 'name' => 'Alice', 'email' => 'alice@example.com'];
    }

    // Internal: returns DTO with behavior
    public function getUser(int $id): User {
        $data = $this->fetchFromApi($id);  // Array shape at boundary
        return new User(                    // Convert to DTO
            $data['id'],
            $data['name'],
            $data['email']
        );
    }
}

$service = new UserService();
$user = $service->getUser(1);

echo "6. Boundary pattern - Array -> DTO:\n";
echo "   External data fetched as array shape\n";
echo "   Converted to User DTO: {$user->getDisplayName()}\n\n";

// -----------------------------------------------------------------------------
// 7. Collection of shapes from database, converted to DTOs
// -----------------------------------------------------------------------------

function fetchAllUsersFromDb(): array<array{id: int, name: string, email: string}> {
    // Simulating: fetchAll(PDO::FETCH_ASSOC)
    return [
        ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
    ];
}

function convertToUsers(array<array{id: int, name: string, email: string}> $rows): array<User> {
    return array_map(
        fn($row) => new User($row['id'], $row['name'], $row['email']),
        $rows
    );
}

echo "7. Database rows -> DTO collection:\n";
$rows = fetchAllUsersFromDb();
echo "   Fetched " . count($rows) . " rows as array<shape>\n";
$users = convertToUsers($rows);
echo "   Converted to " . count($users) . " User DTOs\n\n";

// =============================================================================
// PART 4: COMBINING BOTH — Real-World Patterns
// =============================================================================

echo "--- PART 4: Combined Real-World Patterns ---\n\n";

// -----------------------------------------------------------------------------
// 8. API response containing typed collection
// -----------------------------------------------------------------------------

function getApiResponse(): array{
    success: bool,
    data: array<User>,
    meta: array{total: int, page: int}
} {
    return [
        'success' => true,
        'data' => getUsers(),
        'meta' => ['total' => 3, 'page' => 1]
    ];
}

echo "8. API response with array<User>:\n";
$response = getApiResponse();
echo "   Success: " . ($response['success'] ? 'yes' : 'no') . "\n";
echo "   Users: " . count($response['data']) . "\n";
echo "   Page: {$response['meta']['page']} of {$response['meta']['total']}\n\n";

// =============================================================================
// Summary
// =============================================================================

echo "=== Summary ===\n\n";
echo "Array Shapes vs DTOs - When to use what:\n\n";
echo "  USE ARRAY SHAPES for:\n";
echo "    - Database query results (PDO returns arrays)\n";
echo "    - API responses (json_decode returns arrays)\n";
echo "    - Webhook payloads (external data)\n";
echo "    - Configuration files\n\n";
echo "  USE DTOs/CLASSES for:\n";
echo "    - Domain entities with behavior (methods)\n";
echo "    - Internal application state\n";
echo "    - Complex business logic\n\n";
echo "  THE BOUNDARY PATTERN:\n";
echo "    - Array shapes at edges (external data in)\n";
echo "    - Convert to DTOs for internal logic\n";
echo "    - Best of both worlds!\n";
