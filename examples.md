# Typed Arrays & Array Shapes: Examples

This document provides practical examples of using typed arrays and array shapes in PHP.

## Table of Contents

- [Typed Arrays](#typed-arrays)
  - [Basic Collections](#basic-collections)
  - [Map Types](#map-types)
  - [Nested Arrays](#nested-arrays)
  - [Object Collections](#object-collections)
  - [Union Types](#union-types)
- [Array Shapes](#array-shapes)
  - [Basic Shapes](#basic-shapes)
  - [Optional and Nullable Fields](#optional-and-nullable-fields)
  - [Nested Shapes](#nested-shapes)
  - [Closed Shapes](#closed-shapes)
- [Shape Type Aliases](#shape-type-aliases)
  - [Defining Shapes](#defining-shapes)
  - [Shape Inheritance](#shape-inheritance)
- [Real-World Patterns](#real-world-patterns)
  - [Database Operations](#database-operations)
  - [API Responses](#api-responses)
  - [Configuration](#configuration)
  - [Form Validation](#form-validation)
- [Class Integration](#class-integration)
  - [Property Types](#property-types)
  - [Interface Contracts](#interface-contracts)
  - [Trait Composition](#trait-composition)
- [Advanced Usage](#advanced-usage)
  - [Callable Types in Shapes](#callable-types-in-shapes)
  - [Combining Typed Arrays and Shapes](#combining-typed-arrays-and-shapes)
  - [Reflection](#reflection)
- [Array Shapes and DTOs: Complementary, Not Competing](#array-shapes-and-dtos-complementary-not-competing)
  - [The Boundary Pattern](#the-boundary-pattern)
  - [When to Use What](#when-to-use-what)
  - [Complete Example: API to Domain](#complete-example-api-to-domain)

---

## Array Shapes and DTOs: Complementary, Not Competing

A common question: "Why not just use classes/DTOs?" The answer is that **array shapes and DTOs serve different purposes** and work best together.

**Array shapes validate data at application boundaries** - where external data enters your system. **DTOs encapsulate domain logic** - where your application does its work.

### The Boundary Pattern

Data flows through your application like this:

```
External World          Boundary              Internal Domain
─────────────────────────────────────────────────────────────
                           │
  Database (PDO)     ──────┼────────>  Domain Objects
  API Responses      ──────┼────────>  Value Objects
  JSON Payloads      ──────┼────────>  Entities
  Webhook Data       ──────┼────────>  DTOs with behavior
  Config Files       ──────┼────────>  Service Objects
                           │
              Array Shapes validate here
```

At the boundary, you receive **raw arrays** from external sources. Array shapes validate these arrays have the expected structure **before** you trust them.

```php
<?php

// BOUNDARY: External data enters as arrays
// Array shapes validate structure at the gate

shape GitHubUserResponse = array{
    login: string,
    id: int,
    avatar_url: string,
    html_url: string,
    type: string
};

function fetchGitHubUser(string $username): GitHubUserResponse {
    $response = file_get_contents("https://api.github.com/users/$username");
    $data = json_decode($response, true);

    // Shape validates: if GitHub changes their API or returns
    // unexpected data, we get a TypeError HERE, not deep in our code
    return $data;
}

// INTERNAL: Domain uses proper objects with behavior

class GitHubUser {
    public function __construct(
        public readonly string $login,
        public readonly int $id,
        public readonly string $avatarUrl,
        public readonly string $profileUrl,
        public readonly bool $isOrganization
    ) {}

    public function getDisplayName(): string {
        return "@{$this->login}";
    }

    public function isOrg(): bool {
        return $this->isOrganization;
    }
}

// CONVERSION: Transform validated array to domain object

class GitHubUserFactory {
    public function fromApi(GitHubUserResponse $data): GitHubUser {
        return new GitHubUser(
            login: $data['login'],
            id: $data['id'],
            avatarUrl: $data['avatar_url'],
            profileUrl: $data['html_url'],
            isOrganization: $data['type'] === 'Organization'
        );
    }
}

// Usage
$apiData = fetchGitHubUser('php');           // Validated array shape
$user = $factory->fromApi($apiData);          // Domain object
echo $user->getDisplayName();                 // "@php"
```

### When to Use What

| Scenario | Use Array Shape | Use DTO/Class |
|----------|-----------------|---------------|
| Receiving database query results | ✓ | |
| Parsing API responses | ✓ | |
| Validating JSON payloads | ✓ | |
| Loading configuration files | ✓ | |
| Processing webhook data | ✓ | |
| Domain entities with behavior | | ✓ |
| Objects with calculated properties | | ✓ |
| Encapsulating business rules | | ✓ |
| Internal application state | | ✓ |
| Data with invariants | | ✓ |

**Key insight**: Arrays are **data**. Objects are **behavior**.

Array shapes give you type safety for data at the edges of your system, where:
- Data structure is uncertain (external sources)
- You need validation before trusting the data
- Creating a DTO adds ceremony without benefit
- The data is just passing through

DTOs give you encapsulation inside your system, where:
- You need methods and behavior
- You have business rules to enforce
- You want IDE autocomplete on properties
- The object has a lifecycle

### Complete Example: API to Domain

Here's a realistic example showing the full flow from external API to internal domain:

```php
<?php

// ============================================================
// STEP 1: Define shapes for external API responses
// These validate data AT THE BOUNDARY
// ============================================================

shape StripeCustomerResponse = array{
    id: string,
    object: string,
    email: string,
    name: ?string,
    metadata: array<string, string>,
    created: int,
    livemode: bool
};

shape StripeSubscriptionResponse = array{
    id: string,
    customer: string,
    status: string,
    current_period_start: int,
    current_period_end: int,
    items: array{
        data: array<array{
            id: string,
            price: array{
                id: string,
                unit_amount: int,
                currency: string
            }
        }>
    }
};

// ============================================================
// STEP 2: Define domain objects with behavior
// These encapsulate business logic INSIDE THE APP
// ============================================================

enum SubscriptionStatus: string {
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Trialing = 'trialing';
    case Unpaid = 'unpaid';
}

class Customer {
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly ?string $name,
        public readonly DateTimeImmutable $createdAt,
        private array $subscriptions = []
    ) {}

    public function getDisplayName(): string {
        return $this->name ?? $this->email;
    }

    public function hasActiveSubscription(): bool {
        foreach ($this->subscriptions as $sub) {
            if ($sub->isActive()) {
                return true;
            }
        }
        return false;
    }

    public function addSubscription(Subscription $sub): void {
        $this->subscriptions[] = $sub;
    }

    public function getMonthlySpend(): Money {
        $total = 0;
        foreach ($this->subscriptions as $sub) {
            if ($sub->isActive()) {
                $total += $sub->getMonthlyAmount()->cents;
            }
        }
        return new Money($total, 'usd');
    }
}

class Subscription {
    public function __construct(
        public readonly string $id,
        public readonly string $customerId,
        public readonly SubscriptionStatus $status,
        public readonly DateTimeImmutable $periodStart,
        public readonly DateTimeImmutable $periodEnd,
        public readonly Money $monthlyAmount
    ) {}

    public function isActive(): bool {
        return $this->status === SubscriptionStatus::Active
            || $this->status === SubscriptionStatus::Trialing;
    }

    public function daysRemaining(): int {
        $now = new DateTimeImmutable();
        return max(0, $this->periodEnd->diff($now)->days);
    }

    public function getMonthlyAmount(): Money {
        return $this->monthlyAmount;
    }
}

class Money {
    public function __construct(
        public readonly int $cents,
        public readonly string $currency
    ) {}

    public function format(): string {
        return sprintf('$%.2f %s', $this->cents / 100, strtoupper($this->currency));
    }
}

// ============================================================
// STEP 3: Gateway handles API calls, returns validated shapes
// Shapes catch API changes/errors at the boundary
// ============================================================

class StripeGateway {
    public function __construct(private string $apiKey) {}

    public function getCustomer(string $id): StripeCustomerResponse {
        $response = $this->request("GET", "/customers/$id");
        return json_decode($response, true);  // Validated by shape!
    }

    public function getSubscription(string $id): StripeSubscriptionResponse {
        $response = $this->request("GET", "/subscriptions/$id");
        return json_decode($response, true);  // Validated by shape!
    }

    private function request(string $method, string $endpoint): string {
        // HTTP request implementation...
    }
}

// ============================================================
// STEP 4: Factory converts validated shapes to domain objects
// Clean separation of concerns
// ============================================================

class StripeCustomerFactory {
    public function fromResponse(StripeCustomerResponse $data): Customer {
        return new Customer(
            id: $data['id'],
            email: $data['email'],
            name: $data['name'],
            createdAt: (new DateTimeImmutable)->setTimestamp($data['created'])
        );
    }
}

class StripeSubscriptionFactory {
    public function fromResponse(StripeSubscriptionResponse $data): Subscription {
        $item = $data['items']['data'][0];  // Safe: shape guarantees structure

        return new Subscription(
            id: $data['id'],
            customerId: $data['customer'],
            status: SubscriptionStatus::from($data['status']),
            periodStart: (new DateTimeImmutable)->setTimestamp($data['current_period_start']),
            periodEnd: (new DateTimeImmutable)->setTimestamp($data['current_period_end']),
            monthlyAmount: new Money(
                cents: $item['price']['unit_amount'],
                currency: $item['price']['currency']
            )
        );
    }
}

// ============================================================
// STEP 5: Service orchestrates everything
// Works with domain objects, not raw arrays
// ============================================================

class BillingService {
    public function __construct(
        private StripeGateway $gateway,
        private StripeCustomerFactory $customerFactory,
        private StripeSubscriptionFactory $subscriptionFactory
    ) {}

    public function getCustomerWithSubscriptions(string $customerId): Customer {
        // Gateway returns validated shapes
        $customerData = $this->gateway->getCustomer($customerId);

        // Factory converts to domain objects
        $customer = $this->customerFactory->fromResponse($customerData);

        // Now we work with proper objects
        // If we need subscriptions, we fetch and convert those too

        return $customer;
    }

    public function sendInvoiceReminder(Customer $customer): void {
        if (!$customer->hasActiveSubscription()) {
            return;  // Domain logic using object methods
        }

        $this->mailer->send(
            to: $customer->email,
            subject: "Invoice reminder for {$customer->getDisplayName()}",
            body: "Your monthly spend is {$customer->getMonthlySpend()->format()}"
        );
    }
}

// ============================================================
// USAGE: Clean, type-safe flow from API to domain
// ============================================================

$billing = new BillingService($gateway, $customerFactory, $subscriptionFactory);

// External data validated by shapes at boundary
// Internal logic uses rich domain objects
$customer = $billing->getCustomerWithSubscriptions('cus_123');

echo $customer->getDisplayName();           // Method on object
echo $customer->getMonthlySpend()->format(); // Composed behavior

if ($customer->hasActiveSubscription()) {
    // Business logic encapsulated in domain objects
}
```

**Summary of the pattern:**

1. **Shapes at boundaries** - Validate external data structure (API responses, database rows)
2. **Factories for conversion** - Transform validated shapes into domain objects
3. **Domain objects inside** - Encapsulate behavior, enforce invariants
4. **Services orchestrate** - Coordinate between boundaries and domain

This gives you:
- **Early validation** - Catch API/schema changes immediately at the boundary
- **Type safety** - Compiler and runtime both enforce correct data flow
- **Clean architecture** - Clear separation between external data and internal domain
- **Testability** - Mock the gateway, test domain objects in isolation

---

## Typed Arrays

### Basic Collections

```php
<?php

// List of integers
function getIds(): array<int> {
    return [1, 2, 3, 4, 5];
}

// List of strings
function getTags(): array<string> {
    return ['php', 'rfc', 'types'];
}

// List of floats
function getPrices(): array<float> {
    return [19.99, 29.99, 39.99];
}

// List of booleans
function getFlags(): array<bool> {
    return [true, false, true];
}

// Parameter validation
function sumAll(array<int> $numbers): int {
    return array_sum($numbers);
}

echo sumAll([1, 2, 3]);  // 6
echo sumAll([1, "two"]); // TypeError!
```

### Map Types

```php
<?php

// String keys, integer values
function getScores(): array<string, int> {
    return [
        'alice' => 95,
        'bob' => 87,
        'charlie' => 92
    ];
}

// Integer keys, string values
function getMonthNames(): array<int, string> {
    return [
        1 => 'January',
        2 => 'February',
        3 => 'March'
    ];
}

// String keys, float values
function getCurrencyRates(): array<string, float> {
    return [
        'USD' => 1.0,
        'EUR' => 0.85,
        'GBP' => 0.73
    ];
}

// Mixed key types not allowed - pick one
function getConfig(): array<string, mixed> {
    return [
        'debug' => true,
        'timeout' => 30,
        'name' => 'MyApp'
    ];
}
```

### Nested Arrays

```php
<?php

// 2D matrix
function getMatrix(): array<array<int>> {
    return [
        [1, 2, 3],
        [4, 5, 6],
        [7, 8, 9]
    ];
}

// List of coordinate pairs
function getPoints(): array<array<float>> {
    return [
        [0.0, 0.0],
        [1.5, 2.5],
        [3.0, 4.0]
    ];
}

// Grouped data
function getGroupedScores(): array<string, array<int>> {
    return [
        'math' => [85, 90, 78],
        'english' => [92, 88, 95],
        'science' => [76, 82, 89]
    ];
}

// Deep nesting
function getDeepStructure(): array<array<array<string>>> {
    return [
        [
            ['a', 'b'],
            ['c', 'd']
        ],
        [
            ['e', 'f'],
            ['g', 'h']
        ]
    ];
}
```

### Object Collections

```php
<?php

class User {
    public function __construct(
        public int $id,
        public string $name
    ) {}
}

class Product {
    public function __construct(
        public string $sku,
        public float $price
    ) {}
}

// List of User objects
function getActiveUsers(): array<User> {
    return [
        new User(1, 'Alice'),
        new User(2, 'Bob'),
        new User(3, 'Charlie')
    ];
}

// List of Product objects
function getFeaturedProducts(): array<Product> {
    return [
        new Product('SKU001', 29.99),
        new Product('SKU002', 49.99)
    ];
}

// Map of users by ID
function getUsersById(): array<int, User> {
    return [
        1 => new User(1, 'Alice'),
        2 => new User(2, 'Bob')
    ];
}

// Interface types
interface Serializable {
    public function toArray(): array;
}

function serializeAll(array<Serializable> $items): array<array> {
    return array_map(fn($item) => $item->toArray(), $items);
}
```

### Union Types

```php
<?php

// Integers or strings
function getIdentifiers(): array<int|string> {
    return [1, 'abc', 2, 'def'];
}

// Multiple object types
function getEntities(): array<User|Product> {
    return [
        new User(1, 'Alice'),
        new Product('SKU001', 29.99)
    ];
}

// Nullable elements
function getMaybeValues(): array<?string> {
    return ['hello', null, 'world', null];
}

// Complex unions
function getFlexibleData(): array<int|float|string> {
    return [1, 2.5, 'three', 4, 5.5];
}
```

---

## Array Shapes

### Basic Shapes

```php
<?php

// Simple user shape
function getUser(): array{id: int, name: string} {
    return ['id' => 1, 'name' => 'Alice'];
}

// Access fields with confidence
$user = getUser();
echo $user['id'];    // int, guaranteed
echo $user['name'];  // string, guaranteed

// Shape as parameter
function greetUser(array{name: string} $user): string {
    return "Hello, {$user['name']}!";
}

// Multiple fields
function getProduct(): array{sku: string, name: string, price: float, stock: int} {
    return [
        'sku' => 'PROD001',
        'name' => 'Widget',
        'price' => 29.99,
        'stock' => 100
    ];
}
```

### Optional and Nullable Fields

```php
<?php

// Optional field (may be absent)
function getUserProfile(): array{id: int, name: string, bio?: string} {
    return ['id' => 1, 'name' => 'Alice'];  // bio not required
}

// Nullable field (present but can be null)
function getRecord(): array{id: int, deleted_at: ?string} {
    return ['id' => 1, 'deleted_at' => null];  // must be present
}

// Combined: optional AND nullable
function getFullProfile(): array{id: int, nickname?: ?string} {
    // nickname can be: absent, null, or a string
    return ['id' => 1];  // valid
    return ['id' => 1, 'nickname' => null];  // valid
    return ['id' => 1, 'nickname' => 'Ali'];  // valid
}

// Multiple optional fields
function getConfig(): array{
    host: string,
    port: int,
    timeout?: int,
    retries?: int,
    debug?: bool
} {
    return ['host' => 'localhost', 'port' => 3306];
}
```

### Nested Shapes

```php
<?php

// Shape within shape
function getUserWithAddress(): array{
    id: int,
    name: string,
    address: array{
        street: string,
        city: string,
        zip: string
    }
} {
    return [
        'id' => 1,
        'name' => 'Alice',
        'address' => [
            'street' => '123 Main St',
            'city' => 'Boston',
            'zip' => '02101'
        ]
    ];
}

// Deeply nested
function getOrder(): array{
    id: int,
    customer: array{
        id: int,
        name: string,
        contact: array{
            email: string,
            phone?: string
        }
    },
    total: float
} {
    return [
        'id' => 1001,
        'customer' => [
            'id' => 1,
            'name' => 'Alice',
            'contact' => [
                'email' => 'alice@example.com'
            ]
        ],
        'total' => 99.99
    ];
}
```

### Closed Shapes

```php
<?php

// Open shape (default): extra keys allowed
function getOpenUser(): array{id: int, name: string} {
    return [
        'id' => 1,
        'name' => 'Alice',
        'extra' => 'ignored'  // OK - open shapes allow extra keys
    ];
}

// Closed shape: no extra keys allowed
function getStrictUser(): array{id: int, name: string}! {
    return [
        'id' => 1,
        'name' => 'Alice'
    ];  // OK
}

function getStrictUserBad(): array{id: int, name: string}! {
    return [
        'id' => 1,
        'name' => 'Alice',
        'extra' => 'fail'  // TypeError! Extra key in closed shape
    ];
}

// Use closed shapes for:
// - API responses (prevent data leakage)
// - Strict contracts
// - Catching typos in key names
```

---

## Shape Type Aliases

### Defining Shapes

```php
<?php

// Define reusable shapes
shape User = array{id: int, name: string, email: string};
shape Product = array{sku: string, name: string, price: float};
shape Address = array{street: string, city: string, zip: string, country?: string};

// Use in functions
function createUser(string $name, string $email): User {
    return [
        'id' => rand(1, 1000),
        'name' => $name,
        'email' => $email
    ];
}

function getProducts(): array<Product> {
    return [
        ['sku' => 'A001', 'name' => 'Widget', 'price' => 9.99],
        ['sku' => 'A002', 'name' => 'Gadget', 'price' => 19.99]
    ];
}

// Shapes in classes
class UserService {
    public function find(int $id): ?User {
        return $this->repository->findById($id);
    }

    public function save(User $user): void {
        $this->repository->persist($user);
    }
}

// Check if shape exists
if (shape_exists('User')) {
    echo User::shape;  // "User"
}
```

### Shape Inheritance

```php
<?php

// Base shape
shape Entity = array{id: int, created_at: string};

// Extended shapes
shape User extends Entity = array{
    name: string,
    email: string
};
// Equivalent to: array{id: int, created_at: string, name: string, email: string}

shape Product extends Entity = array{
    sku: string,
    price: float
};

// Multi-level inheritance
shape AdminUser extends User = array{
    role: string,
    permissions: array<string>
};
// Has: id, created_at, name, email, role, permissions

// Override with narrower type (allowed)
shape BaseRecord = array{status: string|int};
shape StrictRecord extends BaseRecord = array{status: string};  // OK: narrower

// Override with wider type (error)
// shape BadRecord extends BaseRecord = array{status: bool};  // Error!

function createAdmin(): AdminUser {
    return [
        'id' => 1,
        'created_at' => '2024-01-01',
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'role' => 'admin',
        'permissions' => ['users.read', 'users.write']
    ];
}
```

---

## Real-World Patterns

### Database Operations

```php
<?php

shape UserRow = array{
    id: int,
    username: string,
    email: string,
    password_hash: string,
    created_at: string,
    updated_at: ?string,
    deleted_at: ?string
};

shape UserPublic = array{
    id: int,
    username: string,
    email: string
};

class UserRepository {
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?UserRow {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(): array<UserRow> {
        $stmt = $this->pdo->query(
            "SELECT * FROM users WHERE deleted_at IS NULL"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toPublic(UserRow $row): UserPublic {
        return [
            'id' => $row['id'],
            'username' => $row['username'],
            'email' => $row['email']
        ];
    }
}
```

### API Responses

```php
<?php

// Generic API response wrapper
shape ApiResponse = array{
    success: bool,
    data: mixed,
    error?: string,
    meta?: array{
        page: int,
        per_page: int,
        total: int
    }
};

// Typed response for users endpoint
shape UserApiResponse = array{
    success: bool,
    data: array{
        id: int,
        name: string,
        email: string
    },
    error?: string
};

// GitHub API response
shape GitHubUser = array{
    login: string,
    id: int,
    avatar_url: string,
    type: string,
    site_admin: bool
};

function fetchGitHubUser(string $username): GitHubUser {
    $json = file_get_contents("https://api.github.com/users/$username");
    return json_decode($json, true);  // Validated against shape
}

// Stripe webhook
shape StripeWebhook = array{
    id: string,
    type: string,
    data: array{
        object: array{
            id: string,
            amount: int,
            currency: string
        }
    }
};

function handleStripeWebhook(StripeWebhook $event): void {
    match ($event['type']) {
        'payment_intent.succeeded' => $this->handlePayment($event),
        'customer.created' => $this->handleCustomer($event),
        default => $this->log("Unknown event: {$event['type']}")
    };
}
```

### Configuration

```php
<?php

shape DatabaseConfig = array{
    driver: string,
    host: string,
    port: int,
    database: string,
    username: string,
    password: string,
    charset?: string,
    options?: array<string, mixed>
};

shape CacheConfig = array{
    driver: string,
    host?: string,
    port?: int,
    prefix?: string,
    ttl?: int
};

shape AppConfig = array{
    name: string,
    env: string,
    debug: bool,
    url: string,
    database: DatabaseConfig,
    cache?: CacheConfig,
    mail?: array{
        driver: string,
        host: string,
        port: int,
        username: string,
        password: string
    }
};

function loadConfig(string $path): AppConfig {
    $config = require $path;
    return $config;  // Validated
}

// Usage
$config = loadConfig(__DIR__ . '/config.php');
$db = new PDO(
    "{$config['database']['driver']}:host={$config['database']['host']};dbname={$config['database']['database']}",
    $config['database']['username'],
    $config['database']['password']
);
```

### Form Validation

```php
<?php

shape LoginForm = array{
    email: string,
    password: string,
    remember?: bool
};

shape RegistrationForm = array{
    username: string,
    email: string,
    password: string,
    password_confirmation: string,
    terms_accepted: bool
};

shape ContactForm = array{
    name: string,
    email: string,
    subject: string,
    message: string,
    attachments?: array<string>
};

class FormValidator {
    public function validateLogin(array $input): LoginForm {
        // Basic validation then return typed shape
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email');
        }
        return [
            'email' => $input['email'],
            'password' => $input['password'],
            'remember' => $input['remember'] ?? false
        ];
    }

    public function validateRegistration(array $input): RegistrationForm {
        if ($input['password'] !== $input['password_confirmation']) {
            throw new ValidationException('Passwords do not match');
        }
        return $input;  // Validated against shape
    }
}
```

---

## Class Integration

### Property Types

```php
<?php

class ShoppingCart {
    // Typed array property
    public array<Product> $items = [];

    // Shape property
    public array{subtotal: float, tax: float, total: float} $totals;

    public function __construct() {
        $this->totals = ['subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0];
    }

    public function addItem(Product $product): void {
        $this->items[] = $product;  // Validated
        $this->recalculate();
    }

    private function recalculate(): void {
        $subtotal = array_sum(array_map(fn($p) => $p->price, $this->items));
        $tax = $subtotal * 0.1;
        $this->totals = [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax
        ];
    }
}

class UserSettings {
    public array{
        theme: string,
        language: string,
        notifications: array{
            email: bool,
            push: bool,
            sms: bool
        },
        timezone?: string
    } $preferences;

    public function __construct() {
        $this->preferences = [
            'theme' => 'light',
            'language' => 'en',
            'notifications' => [
                'email' => true,
                'push' => true,
                'sms' => false
            ]
        ];
    }
}
```

### Interface Contracts

```php
<?php

shape UserData = array{id: int, name: string, email: string};

interface UserRepositoryInterface {
    public function find(int $id): ?UserData;
    public function findAll(): array<UserData>;
    public function save(UserData $user): void;
}

class DatabaseUserRepository implements UserRepositoryInterface {
    public function find(int $id): ?UserData {
        // Return more fields - covariant (allowed)
        $row = $this->db->fetch($id);
        return $row ? [
            'id' => $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'created_at' => $row['created_at']  // Extra field OK (open shape)
        ] : null;
    }

    public function findAll(): array<UserData> {
        return $this->db->fetchAll();
    }

    public function save(UserData $user): void {
        $this->db->insert($user);
    }
}

class CacheUserRepository implements UserRepositoryInterface {
    public function __construct(
        private UserRepositoryInterface $inner,
        private CacheInterface $cache
    ) {}

    public function find(int $id): ?UserData {
        return $this->cache->remember(
            "user:$id",
            fn() => $this->inner->find($id)
        );
    }

    public function findAll(): array<UserData> {
        return $this->cache->remember(
            'users:all',
            fn() => $this->inner->findAll()
        );
    }

    public function save(UserData $user): void {
        $this->inner->save($user);
        $this->cache->forget("user:{$user['id']}");
    }
}
```

### Trait Composition

```php
<?php

shape Timestamps = array{created_at: string, updated_at: ?string};

trait HasTimestamps {
    public function getTimestamps(): Timestamps {
        return [
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    public function touch(): void {
        $this->updated_at = date('Y-m-d H:i:s');
    }
}

shape SoftDeleteInfo = array{deleted_at: ?string, deleted_by: ?int};

trait SoftDeletes {
    public function getSoftDeleteInfo(): SoftDeleteInfo {
        return [
            'deleted_at' => $this->deleted_at,
            'deleted_by' => $this->deleted_by
        ];
    }

    public function softDelete(int $userId): void {
        $this->deleted_at = date('Y-m-d H:i:s');
        $this->deleted_by = $userId;
    }
}

class Article {
    use HasTimestamps, SoftDeletes;

    public string $created_at;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;
    public ?int $deleted_by = null;

    public function __construct() {
        $this->created_at = date('Y-m-d H:i:s');
    }
}
```

---

## Advanced Usage

### Callable Types in Shapes

```php
<?php

// Callable field (any callable)
function processWithCallback(array{data: array<int>, handler: callable} $config): array<int> {
    return array_map($config['handler'], $config['data']);
}

$result = processWithCallback([
    'data' => [1, 2, 3],
    'handler' => fn($x) => $x * 2
]);
// [2, 4, 6]

// Closure field (only closures, not strings/arrays)
function runWithClosure(array{task: Closure} $job): mixed {
    return $job['task']();
}

runWithClosure(['task' => fn() => 'done']);  // OK
runWithClosure(['task' => 'strtoupper']);    // TypeError! Not a Closure

// Event handler pattern
shape EventHandler = array{
    event: string,
    handler: callable,
    priority?: int
};

function registerHandler(EventHandler $handler): void {
    $this->handlers[$handler['event']][] = [
        'callback' => $handler['handler'],
        'priority' => $handler['priority'] ?? 0
    ];
}
```

### Combining Typed Arrays and Shapes

```php
<?php

shape OrderItem = array{
    product_id: int,
    quantity: int,
    unit_price: float
};

shape Order = array{
    id: int,
    customer_id: int,
    items: array<OrderItem>,  // Typed array of shapes!
    status: string,
    totals: array{
        subtotal: float,
        discount: float,
        tax: float,
        total: float
    }
};

function createOrder(int $customerId, array<OrderItem> $items): Order {
    $subtotal = array_sum(array_map(
        fn($item) => $item['quantity'] * $item['unit_price'],
        $items
    ));

    return [
        'id' => rand(1000, 9999),
        'customer_id' => $customerId,
        'items' => $items,
        'status' => 'pending',
        'totals' => [
            'subtotal' => $subtotal,
            'discount' => 0.0,
            'tax' => $subtotal * 0.1,
            'total' => $subtotal * 1.1
        ]
    ];
}

$order = createOrder(1, [
    ['product_id' => 101, 'quantity' => 2, 'unit_price' => 29.99],
    ['product_id' => 102, 'quantity' => 1, 'unit_price' => 49.99]
]);
```

### Reflection

```php
<?php

shape User = array{id: int, name: string, email?: string};

function getUser(): User {
    return ['id' => 1, 'name' => 'Alice'];
}

// Inspect return type
$func = new ReflectionFunction('getUser');
$returnType = $func->getReturnType();

if ($returnType instanceof ReflectionArrayShapeType) {
    echo "Element count: " . $returnType->getElementCount() . "\n";
    echo "Required count: " . $returnType->getRequiredElementCount() . "\n";
    echo "Is closed: " . ($returnType->isClosed() ? 'yes' : 'no') . "\n";

    foreach ($returnType->getElements() as $element) {
        echo "{$element->getName()}: {$element->getType()}";
        echo $element->isOptional() ? " (optional)" : " (required)";
        echo "\n";
    }
}

// Output:
// Element count: 3
// Required count: 2
// Is closed: no
// id: int (required)
// name: string (required)
// email: string (optional)

// Typed array reflection
function getIds(): array<int> {
    return [1, 2, 3];
}

$func = new ReflectionFunction('getIds');
$returnType = $func->getReturnType();

if ($returnType instanceof ReflectionTypedArrayType) {
    echo "Element type: " . $returnType->getElementType() . "\n";
    echo "Key type: " . ($returnType->getKeyType() ?? 'int (default)') . "\n";
}

// Output:
// Element type: int
// Key type: int (default)
```

---

## Error Messages

When validation fails, you get clear, actionable errors:

```php
<?php

// Typed array errors
function getIds(): array<int> {
    return [1, "two", 3];
}
// TypeError: getIds(): Return value must be of type array<int>,
//            array element at index 1 is string

// Shape - missing key
function getUser(): array{id: int, name: string} {
    return ['id' => 1];
}
// TypeError: getUser(): Return value must be of type array{name: string, ...},
//            array given with missing key "name"

// Shape - wrong type
function getProduct(): array{price: float} {
    return ['price' => 'free'];
}
// TypeError: getProduct(): Return value must be of type array{price: float, ...},
//            array key "price" is string

// Closed shape - extra key
function getStrictData(): array{id: int}! {
    return ['id' => 1, 'extra' => 'value'];
}
// TypeError: getStrictData(): Return value must be of type array{id: int}!,
//            unexpected key "extra" in closed shape

// Property errors
class Example {
    public array<int> $values;
}
$e = new Example();
$e->values = [1, "two"];
// TypeError: Cannot assign to property Example::$values of type array<int>,
//            array element at index 1 is string
```
