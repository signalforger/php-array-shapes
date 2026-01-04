<?php
/**
 * Laravel-style Demo: Typed Arrays & Array Shapes
 *
 * This demo simulates a real-world Laravel application using:
 * - Shape type aliases for data structures
 * - Shape inheritance (extends) for DRY code
 * - Typed arrays for collections (array<T>)
 * - Map types (array<K, V>)
 * - Closed shapes (array{...}!)
 * - Nested shapes
 * - ::shape syntax for shape name references
 *
 * Run with: php demo.php
 */

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║     PHP Typed Arrays & Array Shapes - Laravel Demo              ║\n";
echo "╠══════════════════════════════════════════════════════════════════╣\n";
echo "║  PHP Version: " . PHP_VERSION . str_repeat(' ', 52 - strlen(PHP_VERSION)) . "║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// Base Shape Type Aliases
// ============================================================================

// Base user shape with common fields
shape BaseUser = array{
    id: int,
    name: string,
    email: string
};

// Profile information (composable shape)
shape UserProfile = array{
    avatar_url: ?string,
    bio: ?string,
    location: ?string
};

// Pagination metadata (reusable)
shape PaginationMeta = array{
    current_page: int,
    per_page: int,
    total: int,
    last_page: int
};

// ============================================================================
// User Shapes with Inheritance
// ============================================================================

// User record from database - extends BaseUser with timestamps
shape UserRecord extends BaseUser = array{
    created_at: string,
    is_active?: bool
};

// User with profile - extends BaseUser with profile data
shape UserWithProfile extends BaseUser = array{
    profile: UserProfile
};

// ============================================================================
// API Response Shapes with Inheritance
// ============================================================================

// Base API response
shape ApiResponse = array{
    success: bool,
    data: mixed,
    message?: string
};

// Success response extends base with optional errors
shape SuccessResponse extends ApiResponse = array{
    errors?: array<string>
};

// Paginated users response
shape PaginatedUsers = array{
    data: array<UserRecord>,
    meta: PaginationMeta
};

// ============================================================================
// Repository Layer (simulates database access)
// ============================================================================

class UserRepository
{
    private array $users;

    public function __construct()
    {
        $this->users = [
            ['id' => 1, 'name' => 'Alice Johnson', 'email' => 'alice@example.com', 'created_at' => '2024-01-15 10:30:00', 'is_active' => true],
            ['id' => 2, 'name' => 'Bob Smith', 'email' => 'bob@example.com', 'created_at' => '2024-02-20 14:45:00', 'is_active' => true],
            ['id' => 3, 'name' => 'Charlie Brown', 'email' => 'charlie@example.com', 'created_at' => '2024-03-10 09:15:00', 'is_active' => false],
        ];
    }

    public function find(int $id): ?UserRecord
    {
        foreach ($this->users as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }
        return null;
    }

    public function all(): array<UserRecord>
    {
        return $this->users;
    }

    public function getActive(): array<UserRecord>
    {
        return array_values(array_filter(
            $this->users,
            fn($user) => ($user['is_active'] ?? false) === true
        ));
    }

    public function getIds(): array<int>
    {
        return array_map(fn($user) => $user['id'], $this->users);
    }

    public function getEmailsById(): array<int, string>
    {
        $result = [];
        foreach ($this->users as $user) {
            $result[$user['id']] = $user['email'];
        }
        return $result;
    }

    public function paginate(int $page = 1, int $perPage = 10): PaginatedUsers
    {
        $total = count($this->users);
        $offset = ($page - 1) * $perPage;
        $data = array_slice($this->users, $offset, $perPage);

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage)
            ]
        ];
    }

    public function findWithProfile(int $id): ?UserWithProfile
    {
        $user = $this->find($id);
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'profile' => [
                'avatar_url' => "https://avatars.example.com/{$user['id']}.jpg",
                'bio' => "Hello, I'm {$user['name']}!",
                'location' => null
            ]
        ];
    }
}

// ============================================================================
// Service Layer
// ============================================================================

class UserService
{
    private UserRepository $repository;

    public function __construct()
    {
        $this->repository = new UserRepository();
    }

    public function getAllUsers(): array<UserRecord>
    {
        return $this->repository->all();
    }

    public function getUser(int $id): SuccessResponse
    {
        $user = $this->repository->find($id);

        if ($user === null) {
            return [
                'success' => false,
                'data' => null,
                'message' => "User with ID {$id} not found",
                'errors' => ['User not found']
            ];
        }

        return [
            'success' => true,
            'data' => $user,
            'message' => 'User retrieved successfully'
        ];
    }

    public function getStats(): array{total: int, active: int, inactive: int}!
    {
        $all = $this->repository->all();
        $active = $this->repository->getActive();

        return [
            'total' => count($all),
            'active' => count($active),
            'inactive' => count($all) - count($active)
        ];
    }

    public function getUserNames(): array<string>
    {
        $users = $this->repository->all();
        return array_map(fn($user) => $user['name'], $users);
    }

    public function searchByName(string $query): array<UserRecord>
    {
        $users = $this->repository->all();
        return array_values(array_filter(
            $users,
            fn($user) => stripos($user['name'], $query) !== false
        ));
    }

    public function getEmailLookup(): array<int, string>
    {
        return $this->repository->getEmailsById();
    }

    public function getUsersByIds(array<int> $ids): array<UserRecord>
    {
        $result = [];
        foreach ($ids as $id) {
            $user = $this->repository->find($id);
            if ($user !== null) {
                $result[] = $user;
            }
        }
        return $result;
    }
}

// ============================================================================
// Controller Layer (API endpoints)
// ============================================================================

class UserController
{
    private UserService $service;

    public function __construct()
    {
        $this->service = new UserService();
    }

    public function index(): SuccessResponse
    {
        $users = $this->service->getAllUsers();
        return [
            'success' => true,
            'data' => $users,
            'message' => 'Users retrieved successfully'
        ];
    }

    public function show(int $id): SuccessResponse
    {
        return $this->service->getUser($id);
    }

    public function stats(): array{total: int, active: int, inactive: int}!
    {
        return $this->service->getStats();
    }

    public function names(): array<string>
    {
        return $this->service->getUserNames();
    }

    public function search(string $query): SuccessResponse
    {
        $results = $this->service->searchByName($query);
        return [
            'success' => true,
            'data' => $results,
            'message' => count($results) . ' users found'
        ];
    }

    public function batch(array<int> $ids): SuccessResponse
    {
        $users = $this->service->getUsersByIds($ids);
        return [
            'success' => true,
            'data' => $users,
            'message' => count($users) . ' users retrieved'
        ];
    }

    public function emails(): array<int, string>
    {
        return $this->service->getEmailLookup();
    }
}

// ============================================================================
// Run the Demo
// ============================================================================

$controller = new UserController();

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. GET /api/users - List all users (array<UserRecord>)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$response = $controller->index();
echo "Response type: SuccessResponse (extends ApiResponse)\n";
echo "Users returned: " . count($response['data']) . "\n";
echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2. GET /api/users/1 - Get single user (UserRecord extends BaseUser)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$response = $controller->show(1);
echo "Response type: SuccessResponse with UserRecord data\n";
echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3. GET /api/users/stats - Closed shape (no extra keys)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$stats = $controller->stats();
echo "Return type: array{total: int, active: int, inactive: int}!\n";
echo "Stats: " . json_encode($stats) . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4. GET /api/users/names - Simple typed array (array<string>)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$names = $controller->names();
echo "Return type: array<string>\n";
echo "Names: " . json_encode($names) . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "5. GET /api/users/emails - Map type (array<int, string>)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$emails = $controller->emails();
echo "Return type: array<int, string>\n";
echo "Email lookup: " . json_encode($emails) . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "6. GET /api/users/search?q=alice - Filtered typed array\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$response = $controller->search('alice');
echo "Return type: SuccessResponse with array<UserRecord>\n";
echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "7. POST /api/users/batch - Typed array parameter (array<int>)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$response = $controller->batch([1, 3]);
echo "Parameter type: array<int>\n";
echo "Return type: SuccessResponse with array<UserRecord>\n";
echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "8. Shape Inheritance Demo - Using ::shape syntax\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

echo "Shape names using ::shape syntax:\n";
echo "  BaseUser::shape = " . BaseUser::shape . "\n";
echo "  UserRecord::shape = " . UserRecord::shape . " (extends BaseUser)\n";
echo "  UserWithProfile::shape = " . UserWithProfile::shape . " (extends BaseUser)\n";
echo "  ApiResponse::shape = " . ApiResponse::shape . "\n";
echo "  SuccessResponse::shape = " . SuccessResponse::shape . " (extends ApiResponse)\n";
echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "9. Type Safety Demo - What happens with wrong types?\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

echo "Attempting to call batch() with invalid data (strings instead of ints)...\n";
try {
    $response = $controller->batch(['one', 'two']);
    echo "ERROR: Should have thrown TypeError!\n";
} catch (TypeError $e) {
    echo "✓ TypeError caught!\n";
    echo "  Message: " . $e->getMessage() . "\n";
}

echo "\nAttempting to return wrong shape (missing required key)...\n";
function getBadUser(): array{id: int, name: string, email: string} {
    return ['id' => 1, 'name' => 'Test'];  // Missing 'email'
}
try {
    $user = getBadUser();
    echo "ERROR: Should have thrown TypeError!\n";
} catch (TypeError $e) {
    echo "✓ TypeError caught!\n";
    echo "  Message: " . $e->getMessage() . "\n";
}

echo "\nAttempting closed shape with extra key...\n";
function getStrictStats(): array{total: int}! {
    return ['total' => 10, 'extra' => 'not allowed'];
}
try {
    $stats = getStrictStats();
    echo "ERROR: Should have thrown TypeError!\n";
} catch (TypeError $e) {
    echo "✓ TypeError caught!\n";
    echo "  Message: " . $e->getMessage() . "\n";
}

echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "10. Pagination Demo - Complex nested shapes\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$repo = new UserRepository();
$paginated = $repo->paginate(1, 2);
echo "Return type: PaginatedUsers (uses UserRecord which extends BaseUser)\n";
echo json_encode($paginated, JSON_PRETTY_PRINT) . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "11. Reflection API - Inspecting types at runtime\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$ref = new ReflectionMethod(UserController::class, 'stats');
$returnType = $ref->getReturnType();
echo "Method: UserController::stats()\n";
echo "Return type: " . $returnType . "\n";
if (method_exists($returnType, 'isClosed')) {
    echo "Is closed shape: " . ($returnType->isClosed() ? 'yes' : 'no') . "\n";
}

echo "\n";

$ref = new ReflectionMethod(UserController::class, 'names');
$returnType = $ref->getReturnType();
echo "Method: UserController::names()\n";
echo "Return type: " . $returnType . "\n";
if ($returnType instanceof ReflectionTypedArrayType) {
    echo "Element type: " . $returnType->getElementType() . "\n";
}

echo "\n";

$ref = new ReflectionMethod(UserController::class, 'emails');
$returnType = $ref->getReturnType();
echo "Method: UserController::emails()\n";
echo "Return type: " . $returnType . "\n";
if ($returnType instanceof ReflectionTypedArrayType) {
    echo "Key type: " . $returnType->getKeyType() . "\n";
    echo "Element type: " . $returnType->getElementType() . "\n";
}

echo "\n";

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                    Demo completed successfully!                  ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
