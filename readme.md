# PHP RFC: Typed Arrays & Array Shapes

* Version: 2.5
* Date: 2026-01-06
* Author: [Signalforger] <signalforger@signalforge.eu>
* Status: Implemented (Proof of Concept)
* Target Version: PHP 8.5

## Table of Contents

- [Try It Now (Docker)](#try-it-now-docker)
- [Introduction](#introduction)
- [Two Complementary Features](#two-complementary-features)
  - [Typed Arrays: For Collections](#typed-arrays-for-collections)
  - [Array Shapes: For Structured Data](#array-shapes-for-structured-data)
- [Array Shapes Complement DTOs](#array-shapes-complement-dtos)
  - [Where Array Shapes Shine](#where-array-shapes-shine)
  - [The Boundary Pattern](#the-boundary-pattern-arrays-in-dtos-inside)
  - [When to Use What](#when-to-use-what)
- [Quick Reference](#quick-reference)
- [Real-World Examples](#real-world-examples)
  - [Working with Database Results](#working-with-database-results)
  - [Working with API Responses](#working-with-api-responses)
  - [Configuration Arrays](#configuration-arrays)
- [Runtime Validation](#runtime-validation)
  - [Error Messages](#error-messages)
- [Property Types](#property-types)
  - [Error Messages for Properties](#error-messages-for-properties)
- [Shape Type Aliases](#shape-type-aliases)
  - [Shape Inheritance](#shape-inheritance)
  - [The ::shape Syntax](#the-shape-syntax)
  - [Shape Autoloading](#shape-autoloading)
  - [shape_exists() Function](#shape_exists-function)
- [Closed Shapes](#closed-shapes)
- [Reflection API](#reflection-api)
  - [ReflectionArrayShapeType](#reflectionarrayshapetype)
  - [ReflectionTypedArrayType](#reflectiontypedarraytype)
- [API Documentation Generation](#api-documentation-generation)
  - [OpenAPI/Swagger Generation](#openapiswagger-generation)
  - [GraphQL Schema Generation](#graphql-schema-generation)
- [Variance and Inheritance](#variance-and-inheritance)
- [Implementation Status](#implementation-status)
- [Performance Optimizations](#performance-optimizations)
  - [Element Type Caching](#element-type-caching)
  - [Key Type Caching](#key-type-caching)
  - [Class Entry Caching](#class-entry-caching)
  - [Object Array Optimizations](#object-array-optimizations)
  - [SIMD Validation (AVX2)](#simd-validation-avx2)
  - [Recursion Depth Limit](#recursion-depth-limit)
  - [Cache Invalidation](#cache-invalidation)
  - [String Interning for Shape Keys](#string-interning-for-shape-keys)
  - [Cached Expected Keys for Closed Shapes](#cached-expected-keys-for-closed-shapes)
- [Why Native Types Instead of Static Analysis?](#why-native-types-instead-of-static-analysis)
- [Why Not Generics?](#why-not-generics)
- [Backward Compatibility](#backward-compatibility)
- [Future Scope](#future-scope)
- [Source Code](#source-code)
- [Changelog](#changelog)
- [References](#references)

## Try It Now (Docker)

The fastest way to try typed arrays and array shapes:

```bash
# Pull the image
docker pull ghcr.io/signalforger/php-array-shapes:latest

# Start interactive PHP shell
docker run -it --rm ghcr.io/signalforger/php-array-shapes:latest php -a

# Run a PHP file
docker run --rm -v $(pwd):/app ghcr.io/signalforger/php-array-shapes:latest php /app/example.php
```

Available image variants:

| Image | Description |
|-------|-------------|
| `ghcr.io/signalforger/php-array-shapes:latest` | CLI version (default) |
| `ghcr.io/signalforger/php-array-shapes:latest-fpm` | PHP-FPM for web servers |
| `ghcr.io/signalforger/php-array-shapes:8.5.1` | Specific version |

### Build Locally

If you prefer to build the image yourself:

```bash
# Clone the repository with submodules
git clone --recursive https://github.com/signalforger/php-array-shapes.git
cd php-array-shapes

# Build the CLI image
docker build --target cli -t php-array-shapes:latest .

# Or build with FPM support
docker build --target fpm -t php-array-shapes:fpm .
```

**Run examples:**

```bash
# Interactive PHP shell
docker run -it --rm php-array-shapes:latest php -a

# Run a script
docker run --rm -v $(pwd):/app php-array-shapes:latest php /app/your-script.php

# Quick test
docker run --rm php-array-shapes:latest php -r '
function getUser(): array{id: int, name: string}! {
    return ["id" => 1, "name" => "Alice"];
}
var_dump(getUser());
'
```

**Build options:**

| Target | Command | Use case |
|--------|---------|----------|
| `cli` | `docker build --target cli -t php-array-shapes:latest .` | CLI scripts |
| `fpm` | `docker build --target fpm -t php-array-shapes:fpm .` | Web servers |

**Rebuild from scratch** (after pulling updates):

```bash
docker build --no-cache --target cli -t php-array-shapes:latest .
```

## Introduction

PHP's type system currently supports return type declarations for scalar types, classes, and the generic `array` type. However, the `array` type provides no structural information, forcing developers to rely on documentation and static analysis tools to understand array contents.

This RFC proposes adding **Typed Arrays** and **Array Shapes** to PHP: two complementary features that bring type safety to PHP's most versatile data structure.

### The Problem

```php
function getUsers(): array {
    // What's in this array? Objects? Associative arrays? Integers?
    // The type system can't tell you.
}
```

This leads to:
- **Runtime errors** - Type mismatches discovered only during execution
- **Poor IDE support** - Limited autocomplete and refactoring capabilities
- **Documentation burden** - Developers must rely on PHPDoc annotations
- **Maintenance issues** - Changing array structures requires manual updates

## Two Complementary Features

### Typed Arrays: For Collections

When you have a **list of things of the same type**, use typed arrays:

```php
// A list of integers
function getIds(): array<int> {
    return [1, 2, 3];
}

// A list of User objects
function getActiveUsers(): array<User> {
    return $this->repository->findActive();
}

// A dictionary with string keys and integer values
function getScores(): array<string, int> {
    return ['alice' => 95, 'bob' => 87, 'charlie' => 92];
}
```

This is what you reach for when working with collections, where every element is the same kind of thing.

### Array Shapes: For Structured Data

When you have **structured data with known keys**, like records from a database or responses from an API, use array shapes:

```php
// Data from a database row
function getUser(int $id): array{id: int, name: string, email: string} {
    return $this->db->fetch("SELECT id, name, email FROM users WHERE id = ?", $id);
}

// Response from an external API
function getWeather(string $city): array{temp: float, humidity: int, conditions: string} {
    return json_decode(file_get_contents("https://api.weather.com/$city"), true);
}
```

## Array Shapes Complement DTOs

A common reaction: "Why not just use classes/DTOs?"

**Array shapes don't replace DTOs, they complement them.** The key insight is that arrays earn their keep at the boundaries of your application, where data enters from external sources:

### Where Array Shapes Shine

```php
// Database results - PDO returns arrays, not objects
function fetchUser(PDO $db, int $id): array{id: int, name: string, email: string} {
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);  // Returns array, validated by type
}

// API responses - json_decode returns arrays
function getGitHubUser(string $username): array{login: string, id: int, avatar_url: string} {
    $json = file_get_contents("https://api.github.com/users/$username");
    return json_decode($json, true);  // Validated: ensures expected structure
}

// Webhook payloads - external data you don't control
function handleStripeWebhook(array{type: string, data: array{object: array{id: string}}} $payload): void {
    // Type system guarantees the structure before you use it
    $eventType = $payload['type'];
    $objectId = $payload['data']['object']['id'];
}
```

### The Boundary Pattern: Arrays In, DTOs Inside

A practical pattern: use array shapes at boundaries, convert to DTOs for internal logic:

```php
// Array shape for external data
function fetchUserFromApi(): array{id: int, name: string, email: string} {
    return json_decode($this->http->get('/api/user'), true);
}

// DTO for internal domain logic
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

// Convert at the boundary
class UserService {
    public function getUser(): User {
        $data = $this->fetchUserFromApi();  // array{id: int, name: string, email: string}
        return new User($data['id'], $data['name'], $data['email']);  // DTO
    }
}
```

### When to Use What

| Scenario | Use Array Shape | Use DTO/Class |
|----------|-----------------|---------------|
| Database query results | ✓ | |
| API response parsing | ✓ | |
| JSON deserialization | ✓ | |
| Configuration loading | ✓ | |
| Webhook/event payloads | ✓ | |
| Domain entities with behavior | | ✓ |
| Objects with methods | | ✓ |
| Complex business logic | | ✓ |
| Internal application state | | ✓ |

**Arrays are data. Objects are behavior.** Array shapes give you type safety for data at the edges of your system, where DTOs would add ceremony without benefit.

## Quick Reference

```php
// Typed arrays (for collections)
array<int>                     // List of integers
array<string>                  // List of strings
array<User>                    // List of User objects
array<int|string>              // List of integers or strings
array<string, int>             // Dictionary: string keys, int values
array<array<int>>              // List of integer lists

// Array shapes (for structured data)
array{id: int, name: string}   // Required keys (open: extra keys allowed)
array{id: int, email?: string} // Optional key (may be absent)
array{data: ?string}           // Nullable value (can be null)
array{user: array{id: int}}    // Nested shapes
array{id: int, name: string}!  // Closed shape (no extra keys allowed)

// Shape type aliases (for reusability)
shape User = array{id: int, name: string};
shape Point = array{x: int, y: int};
shape Config = array{debug: bool, cache?: int};

// Shape inheritance (extends)
shape BaseUser = array{id: int, name: string};
shape AdminUser extends BaseUser = array{role: string, permissions: array<string>};

// ::shape syntax (get shape name)
echo UserRecord::shape;           // "UserRecord"
echo \App\Types\User::shape;      // "App\Types\User"

// Property types (typed class properties)
class Example {
    public array<int> $ids = [];
    public array<User> $users;
    public array{id: int, name: string} $config;
}
```

## Real-World Examples

### Working with Database Results

```php
// Define the shape of a user record
shape UserRecord = array{
    id: int,
    name: string,
    email: string,
    created_at: string,
    is_active?: bool
};

class UserRepository {
    // Single record
    public function find(int $id): ?UserRecord {
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", $id);
    }

    // Collection of records (combining both features!)
    public function findAll(): array<UserRecord> {
        return $this->db->fetchAll("SELECT * FROM users");
    }
}
```

### Working with API Responses

```php
// Shape describing the API response structure
shape ApiResponse = array{
    success: bool,
    data: mixed,
    error?: string,
    meta?: array{page: int, total: int}
};

shape ProductData = array{
    id: int,
    name: string,
    price: float,
    tags: array<string>     // Nested typed array!
};

function fetchProduct(int $id): ProductData {
    $response = $this->http->get("/api/products/$id");
    return $response['data'];
}

function fetchProducts(): array<ProductData> {
    $response = $this->http->get("/api/products");
    return $response['data'];
}
```

### Configuration Arrays

```php
shape DatabaseConfig = array{
    host: string,
    port: int,
    database: string,
    username: string,
    password: string,
    options?: array<string, mixed>
};

shape AppConfig = array{
    debug: bool,
    env: string,
    database: DatabaseConfig,
    cache_ttl?: int
};

function loadConfig(string $path): AppConfig {
    return require $path;
}
```

## Runtime Validation

Typed array and array shape validation is **always enabled**. When a type constraint is declared, it is enforced at runtime:

```php
function getIds(): array<int> {
    return [1, "two", 3];  // TypeError at runtime
}

function getUser(): array{id: int, name: string} {
    return ['id' => 1];  // TypeError: missing required key 'name'
}
```

### Error Messages

When validation fails, you get clear, actionable error messages:

```php
// For typed arrays
TypeError: getIds(): Return value must be of type array<int>,
           array element at index 1 is string

// For array shapes - missing key
TypeError: getUser(): Return value must be of type array{name: string, ...},
           array given with missing key "name"

// For array shapes - wrong type
TypeError: getUser(): Return value must be of type array{id: int, ...},
           array key "id" is string
```

## Property Types

Typed arrays and array shapes work on class properties, with runtime validation on assignment:

```php
class UserRepository {
    public array<User> $users = [];
    public array{host: string, port: int} $dbConfig;

    public function __construct() {
        $this->dbConfig = ['host' => 'localhost', 'port' => 3306];
    }

    public function addUser(User $user): void {
        $this->users[] = $user;  // Validated: must be User object
    }
}

// Property validation errors
$repo = new UserRepository();
$repo->dbConfig = ['host' => 'localhost'];  // TypeError: missing key "port"
$repo->dbConfig = ['host' => 123, 'port' => 3306];  // TypeError: key "host" is int
```

### Error Messages for Properties

```php
// Typed array property
TypeError: Cannot assign to property UserRepository::$users of type array<User>,
           array element at index 0 is string

// Array shape property - missing key
TypeError: Cannot assign to property UserRepository::$dbConfig of type array{port: int, ...},
           array given with missing key "port"

// Array shape property - wrong type
TypeError: Cannot assign to property UserRepository::$dbConfig of type array{host: string, ...},
           array key "host" is int
```

## Shape Type Aliases

Define reusable type aliases for array structures using the `shape` keyword:

```php
shape User = array{id: int, name: string, email: string};
shape Point = array{x: int, y: int};
shape Config = array{debug: bool, env: string, cache_ttl?: int};

function getUser(int $id): User {
    return ['id' => $id, 'name' => 'Alice', 'email' => 'alice@example.com'];
}

function processUser(User $user): void {
    echo "Processing: {$user['name']}";
}
```

### Shape Inheritance

Shapes can extend other shapes using the `extends` keyword. Child shapes inherit all fields from their parent and can add new fields:

```php
// Base shape with common fields
shape BaseUser = array{
    id: int,
    name: string,
    email: string
};

// Extended shape adds new fields
shape UserRecord extends BaseUser = array{
    created_at: string,
    is_active?: bool
};

// Further extension
shape AdminUser extends UserRecord = array{
    role: string,
    permissions: array<string>
};

function getAdmin(int $id): AdminUser {
    return [
        'id' => $id,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'created_at' => '2024-01-15',
        'is_active' => true,
        'role' => 'admin',
        'permissions' => ['users.read', 'users.write']
    ];
}
```

**Compile-time flattening**: Shape inheritance is resolved at compile time. The child shape contains all fields from the parent plus its own fields, flattened into a single shape definition. This means no runtime overhead for inheritance.

**Type covariance rules**: When overriding parent fields, child shapes must follow these rules:
- Child shapes can **narrow** parent types (e.g., `string|int` → `string`)
- Child shapes **cannot widen** types (e.g., `string` → `int` is rejected)
- Child shapes can make optional properties required
- Child shapes **cannot** make required properties optional

```php
shape Base = array{value: string|int, status?: string};

// Valid: narrows string|int to string, makes optional required
shape Valid extends Base = array{value: string, status: string};

// Invalid: widens string|int to bool (compile error)
shape Invalid extends Base = array{value: bool};

// Invalid: makes required property optional (compile error)
shape AlsoInvalid extends Base = array{value?: string};
```

**Restrictions**: Shapes and classes are separate concepts and cannot be mixed:

```php
// Shape cannot extend a class
class MyClass {}
shape BadShape extends MyClass = array{id: int};
// Fatal error: Shape BadShape cannot extend class MyClass

// Class cannot extend a shape
shape MyShape = array{id: int};
class BadClass extends MyShape {}
// Fatal error: Class BadClass cannot extend shape MyShape
```

### The ::shape Syntax

Similar to how classes can be referenced with `::class`, shapes can be referenced with `::shape` to get their fully qualified name:

```php
shape UserRecord = array{id: int, name: string};

echo UserRecord::shape;  // "UserRecord"

// In a namespace
namespace App\Types;
shape ApiResponse = array{success: bool, data: mixed};

echo ApiResponse::shape;  // "App\Types\ApiResponse"
echo \App\Types\ApiResponse::shape;  // "App\Types\ApiResponse"

// Useful for logging, debugging, and reflection
function logShape(string $shapeName): void {
    echo "Processing shape: $shapeName";
}
logShape(UserRecord::shape);
```

**Compile-time resolution**: The `::shape` syntax is resolved at compile time, making it zero-cost at runtime.

**Error handling**: Using `::shape` on a class or `::class` on a shape produces clear error messages:

```php
class MyClass {}
echo MyClass::shape;
// Fatal error: Cannot use ::shape on class MyClass, use ::class instead

shape MyShape = array{id: int};
echo MyShape::class;
// Fatal error: Cannot use ::class on shape MyShape, use ::shape instead
```

### Shape Autoloading

Shapes can be autoloaded using the standard `spl_autoload_register()` mechanism:

```php
spl_autoload_register(function($name) {
    $file = __DIR__ . "/shapes/$name.php";
    if (file_exists($file)) {
        require_once $file;
    }
});

// UserShape is autoloaded when first used
function getUser(): UserShape { ... }
```

### shape_exists() Function

Check if a shape type alias is defined:

```php
// Check without triggering autoload
if (shape_exists('User', false)) { ... }

// Check with autoloading (default)
if (shape_exists('User')) { ... }
```

## Closed Shapes

By default, array shapes are **open**: they allow extra keys beyond those declared. This is pragmatic for API responses where you often don't care about additional fields.

For stricter validation, use the `!` suffix to create a **closed shape** that rejects extra keys:

```php
// Open shape (default): extra keys allowed
function getUser(): array{id: int, name: string} {
    return ['id' => 1, 'name' => 'Alice', 'extra' => 'ok'];  // Valid
}

// Closed shape: no extra keys allowed
function getStrictUser(): array{id: int, name: string}! {
    return ['id' => 1, 'name' => 'Alice', 'extra' => 'fail'];  // TypeError!
}
```

Use closed shapes when:
- You need to prevent data leakage (e.g., returning internal fields to clients)
- You want to catch typos in key names
- Your contract requires an exact structure

Reflection supports closed shapes:

```php
$ref = new ReflectionFunction('getStrictUser');
$type = $ref->getReturnType();
echo $type->isClosed();  // true
echo $type;              // array{id: int, name: string}!
```

## Reflection API

New reflection classes provide runtime introspection:

### ReflectionArrayShapeType

```php
$ref = new ReflectionFunction('getUser');
$type = $ref->getReturnType();

if ($type instanceof ReflectionArrayShapeType) {
    echo $type->getElementCount();          // Number of elements
    echo $type->getRequiredElementCount();  // Required elements only

    foreach ($type->getElements() as $element) {
        echo $element->getName();      // Key name
        echo $element->getType();      // Element type
        echo $element->isOptional();   // Is optional?
    }
}
```

### ReflectionTypedArrayType

```php
if ($type instanceof ReflectionTypedArrayType) {
    echo $type->getElementType();  // Element type (e.g., "int")
    echo $type->getKeyType();      // Key type for array<K,V>
}
```

## API Documentation Generation

A major benefit of native typed arrays and array shapes is automatic API documentation generation. Since types are part of the language (not comments), the Reflection API can introspect them reliably, enabling tools to generate OpenAPI/Swagger specs, GraphQL schemas, and other documentation formats from your actual code.

### OpenAPI/Swagger Generation

```php
shape ProductResponse = array{
    id: int,
    name: string,
    price: float,
    tags: array<string>,
    metadata?: array{sku: string, weight?: float}
};

class ProductController {
    #[Route('/api/products/{id}', methods: ['GET'])]
    public function show(int $id): ProductResponse {
        return $this->repository->find($id);
    }

    #[Route('/api/products', methods: ['GET'])]
    public function index(): array<ProductResponse> {
        return $this->repository->findAll();
    }
}

// Generate OpenAPI schema from return types
function generateOpenApiSchema(string $class): array {
    $schemas = [];
    $ref = new ReflectionClass($class);

    foreach ($ref->getMethods() as $method) {
        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionArrayShapeType) {
            $schemas[$method->getName()] = shapeToOpenApi($returnType);
        } elseif ($returnType instanceof ReflectionTypedArrayType) {
            $schemas[$method->getName()] = [
                'type' => 'array',
                'items' => typeToOpenApi($returnType->getElementType())
            ];
        }
    }
    return $schemas;
}

function shapeToOpenApi(ReflectionArrayShapeType $shape): array {
    $properties = [];
    $required = [];

    foreach ($shape->getElements() as $element) {
        $properties[$element->getName()] = typeToOpenApi($element->getType());

        if (!$element->isOptional()) {
            $required[] = $element->getName();
        }
    }

    return [
        'type' => 'object',
        'properties' => $properties,
        'required' => $required
    ];
}
```

This generates OpenAPI-compliant schemas:

```yaml
ProductResponse:
  type: object
  required: [id, name, price, tags]
  properties:
    id:
      type: integer
    name:
      type: string
    price:
      type: number
    tags:
      type: array
      items:
        type: string
    metadata:
      type: object
      properties:
        sku:
          type: string
        weight:
          type: number
```

### GraphQL Schema Generation

The same reflection capabilities enable GraphQL schema generation:

```php
shape Author = array{id: int, name: string, email: string};
shape Article = array{
    id: int,
    title: string,
    content: string,
    author: Author,
    tags: array<string>,
    published_at?: string
};

class ArticleResolver {
    public function article(int $id): Article { ... }
    public function articles(): array<Article> { ... }
}

// Generate GraphQL types from shapes
function shapeToGraphQL(string $shapeName, ReflectionArrayShapeType $shape): string {
    $fields = [];

    foreach ($shape->getElements() as $element) {
        $type = phpTypeToGraphQL($element->getType());
        $nullable = $element->isOptional() ? '' : '!';
        $fields[] = "  {$element->getName()}: {$type}{$nullable}";
    }

    return "type {$shapeName} {\n" . implode("\n", $fields) . "\n}";
}
```

Generated GraphQL schema:

```graphql
type Author {
  id: Int!
  name: String!
  email: String!
}

type Article {
  id: Int!
  title: String!
  content: String!
  author: Author!
  tags: [String!]!
  published_at: String
}

type Query {
  article(id: Int!): Article
  articles: [Article!]!
}
```

### Single Source of Truth

The key advantage: **your PHP types ARE your API contract**. No more maintaining separate type definitions that drift out of sync:

| Traditional Approach | With Native Types |
|---------------------|-------------------|
| PHPDoc for static analysis | Native types do both |
| Separate OpenAPI YAML files | Generated from code |
| Separate GraphQL schema files | Generated from code |
| Manual sync between all three | Automatic, always in sync |
| Comments can lie | Types are enforced |

When you change a return type, your API documentation updates automatically because it's generated from the same source: your actual code.

## Variance and Inheritance

Array shape return types follow PHP's standard covariance rules:

### Covariance (More Specific Return Types Allowed)

```php
class Repository {
    function getUser(): array{id: int} {
        return ['id' => 1];
    }
}

class ExtendedRepository extends Repository {
    // Valid: Child returns MORE keys (covariant)
    function getUser(): array{id: int, name: string, email: string} {
        return ['id' => 1, 'name' => 'Alice', 'email' => 'a@b.com'];
    }
}
```

### For Typed Arrays

```php
class NumberProvider {
    function getNumbers(): array<int|float> {
        return [1, 2.5, 3];
    }
}

class IntProvider extends NumberProvider {
    // Valid: array<int> is more specific than array<int|float>
    function getNumbers(): array<int> {
        return [1, 2, 3];
    }
}
```

## Implementation Status

- [x] Typed arrays: `array<T>`, `array<K, V>`
- [x] Array shapes: `array{key: type}`
- [x] Optional keys: `array{key?: type}`
- [x] Nullable values: `array{key: ?type}`
- [x] Union types: `array<int|string>`
- [x] Nested structures: `array<array<int>>`, `array{user: array{id: int}}`
- [x] Shape type aliases: `shape Name = array{...}`
- [x] **Shape inheritance**: `shape Child extends Parent = array{...}`
- [x] **Type covariance validation**: Compile-time checks for shape property overrides
- [x] **::shape syntax**: `UserRecord::shape` returns fully qualified shape name
- [x] Shape autoloading via `spl_autoload_register()`
- [x] Reflection API support (`ReflectionArrayType`, `ReflectionArrayShapeType`)
- [x] Runtime validation with detailed error messages
- [x] **Property types**: `public array<int> $ids;`, `public array{id: int} $user;`
- [x] **Closed shapes**: `array{id: int}!` (no extra keys allowed)
- [x] **Compile-time validation**: Shape/class cross-inheritance prevention
- [x] **Performance optimizations**: String interning, cached key lookups for closed shapes

All 35 array shape tests pass.

## Performance Optimizations

The implementation includes several optimizations to minimize the runtime cost of typed array validation. These optimizations are implemented in the Zend engine (`Zend/zend_execute.c`, `Zend/zend_hash.c`, `Zend/zend_types.h`).

### Element Type Caching

When validating `array<T>`, the validated element type is cached in the HashTable's `nValidatedElemType` field (a single byte in the HashTable structure). On subsequent validations:

1. If the cached type matches the expected type, validation is skipped entirely
2. The cache uses PHP's internal type codes (`IS_LONG`, `IS_STRING`, etc.)
3. Complex types that can't be cached fall back to full validation

```php
function process(array<int> $ids): void {
    // First call: validates all elements, caches IS_LONG
    validate($ids);

    // Second call: cache hit, O(1) instead of O(n)
    validate($ids);
}
```

**Implementation**: The cache is stored in `ht->u.v.nValidatedElemType` and checked via `HT_ELEM_TYPE_IS_VALID()` macro.

### Key Type Caching

For `array<K, V>` map types, key types are cached in `nValidatedKeyType` using a bitmask:

| Key Type | Bitmask |
|----------|---------|
| `int` keys only | `MAY_BE_LONG` |
| `string` keys only | `MAY_BE_STRING` |
| Mixed keys | `MAY_BE_LONG \| MAY_BE_STRING` |

**Fast path for packed arrays**: PHP internally distinguishes "packed" arrays (sequential integer keys 0, 1, 2...) from "hash" arrays. For `array<int, V>` validation:

```php
function getScores(): array<int, float> {
    return [98.5, 87.2, 92.0];  // Packed array - O(1) key validation
}
```

Packed arrays skip key iteration entirely since they can only have integer keys by definition.

### Class Entry Caching

When validating `array<ClassName>`, the class lookup (`zend_lookup_class()`) is cached thread-locally:

```c
ZEND_TLS zend_string *zend_cached_class_name = NULL;
ZEND_TLS zend_class_entry *zend_cached_class_entry = NULL;
```

This means:
- First validation: looks up class, caches result
- Subsequent validations of same class type: cache hit, no lookup

```php
function getUsers(): array<User> {
    // First call: zend_lookup_class("User"), caches result
    // All subsequent calls: returns cached class entry
}
```

### Object Array Optimizations

Arrays of objects (`array<ClassName>`) have three optimization levels:

**1. Exact Class Match (Pointer Comparison)**

When an object's class pointer equals the expected class exactly, no inheritance check is needed:

```php
class User {}
class Admin extends User {}

function getUsers(): array<User> {
    return [new User(), new User()];  // Pointer comparison only
}
```

**2. Monomorphic Array Detection**

If all objects in an array are the same concrete type, only the first element needs a full `instanceof` check:

```php
function getAdmins(): array<User> {
    // First element: full instanceof check
    // Remaining elements: pointer comparison against first
    return [new Admin(), new Admin(), new Admin()];
}
```

**3. Packed Array Fast Path**

For packed arrays of objects, the implementation uses direct pointer access instead of hash table iteration:

```c
// Direct access: data[i] instead of ZEND_HASH_FOREACH
for (uint32_t i = 0; i < count; i++) {
    zend_class_entry *obj_ce = Z_OBJCE(data[i]);
    // validate...
}
```

### SIMD Validation (AVX2)

On x86-64 systems with AVX2 support, large packed arrays of primitive types use SIMD instructions to validate 8 elements simultaneously:

```c
#ifdef __AVX2__
// Validate 8 integers at once using 256-bit registers
__m256i types = _mm256_i32gather_epi32(type_ptr, gather_indices, 4);
__m256i cmp = _mm256_cmpeq_epi32(type_bytes, expected_type);
if (_mm256_movemask_epi8(cmp) != 0xFFFFFFFF) {
    // At least one type mismatch - fall back to scalar
}
#endif
```

**When SIMD kicks in**:
- Packed arrays only (sequential integer keys)
- 16+ elements (threshold defined by `ZEND_SIMD_MIN_ELEMENTS`)
- Validating `array<int>` or `array<float>`

**Performance benefit**: ~8x throughput for type checking on large numeric arrays.

### Recursion Depth Limit

Nested array validation uses a thread-local depth counter to prevent stack overflow:

```c
ZEND_TLS int zend_typed_array_recursion_depth = 0;
#define ZEND_TYPED_ARRAY_MAX_DEPTH 128
```

This handles:
- Deeply nested structures: `array<array<array<array<int>>>>`
- Circular references (detected and handled gracefully)

```php
// Safe: recursion limit prevents stack overflow
function deep(): array<array<array<array<array<int>>>>> {
    return [[[[[1, 2, 3]]]]];
}
```

### Cache Invalidation

All type caches are automatically invalidated when arrays are mutated. The invalidation hooks are placed in `Zend/zend_hash.c`:

| Operation | Cache Invalidated |
|-----------|-------------------|
| `zend_hash_add()` | Element type + key type |
| `zend_hash_update()` | Element type + key type |
| `zend_hash_del()` | Element type + key type |
| `zend_hash_clean()` | Element type + key type |
| `zend_hash_index_add()` | Element type |
| `zend_hash_index_del()` | Element type |

**Implementation**: Uses `HT_INVALIDATE_ELEM_TYPE()` and `HT_INVALIDATE_KEY_TYPE()` macros after mutation operations.

### String Interning for Shape Keys

Shape element keys use PHP's string interning mechanism to reduce memory usage and enable fast pointer comparison:

```c
// During shape persistence, keys are interned when possible
zend_string *interned = zend_string_init_existing_interned(key, len, 1);
if (interned) {
    shape->elements[i].key = interned;  // Reuse existing interned string
}
```

**Benefits**:
- Common keys like `"id"`, `"name"`, `"email"` share memory across all shapes
- String comparison becomes pointer comparison for interned strings
- Reduced memory footprint for applications with many similar shapes

### Cached Expected Keys for Closed Shapes

Closed shapes (`array{...}!`) cache their expected keys in a hash table at definition time:

```c
typedef struct _zend_array_shape {
    uint32_t num_elements;
    uint32_t num_required;
    bool is_closed;
    HashTable *expected_keys;  // Pre-built for O(1) lookup
    zend_array_shape_element elements[];
} zend_array_shape;
```

**Without cache**: Each validation builds a temporary hash table O(n) + checks O(m)
**With cache**: Direct hash lookup O(m) only

This significantly improves validation performance for closed shapes with many elements.

### Performance Summary

| Scenario | Optimization | Benefit |
|----------|--------------|---------|
| Repeated validation of same array | Element/key type cache | O(1) vs O(n) |
| Packed array with int keys | Packed array fast path | Skip key iteration |
| Same class type validated repeatedly | Class entry cache | Skip class lookup |
| Array of same concrete type | Monomorphic detection | 1 instanceof vs n |
| Large numeric arrays (16+) | SIMD/AVX2 | ~8x throughput |
| Deep nesting | Recursion limit | Prevents stack overflow |
| Closed shape validation | Cached expected keys | O(m) vs O(n+m) |
| Shape key comparison | String interning | Pointer comparison |

For detailed benchmark results, see [benchmarks.md](benchmarks.md).

## Why Native Types Instead of Static Analysis?

Tools like PHPStan and Psalm already support array shapes via docblocks. Why add native syntax?

| Aspect | Native Types | Static Analysis |
|--------|--------------|-----------------|
| Runtime validation | Yes | No |
| Can be bypassed | No | Yes |
| Comment drift | Impossible | Common |
| Reflection access | Built-in | Parse comments |
| Performance | Engine-optimized | Userland |
| Setup required | None | Tools + config |
| External data | Validated | Trusted blindly |

**Native types and static analysis are complementary** - static analysis catches bugs before runtime, native types catch bugs that slip through (especially from external data sources).

## Why Not Generics?

A common question is why this RFC proposes typed arrays instead of full generics. The answer lies in PHP's specific characteristics and what developers actually need.

### Implementation Approaches

Every language that implements generics must choose an approach, each with trade offs:

| Approach | Languages | Trade off |
|----------|-----------|-----------|
| **Type Erasure** | Java, TypeScript, Python | Types checked at compile time only; no runtime validation |
| **Reification** | C# | Full runtime types; requires deep VM integration |
| **Monomorphization** | Rust, C++ | Specialized code per type; increases compile time and binary size |

For PHP, type erasure would duplicate what static analyzers (PHPStan, Psalm) already provide. Reification would require significant engine changes (C#'s implementation took Microsoft Research six years). Monomorphization is designed for ahead of time compilation, not interpreted execution.

### Where Generics Originated

Generics were designed to solve specific problems in other languages:

| Language | Problem | Generic Solution |
|----------|---------|------------------|
| C++ (STL) | Writing reusable algorithms for any container type | Templates let `sort<T>` work with vectors, arrays, deques |
| Java | Fixed size arrays; unsafe Object collections pre Java 5 | `ArrayList<T>` provides growable, type safe collections |
| Rust | Zero cost abstractions for systems programming | Monomorphization compiles generic code to type specific machine code |
| C# | Separate collection types with no type safety | Reified generics with full runtime type information |

These languages needed generics because their built in collections were inflexible or unsafe.

### PHP's Context

PHP solved these problems differently from the start:

1. **Arrays are the universal container**: PHP arrays are dynamic, associative, and growable. There's no need for `ArrayList<T>` when `array` already does everything.
2. **Runtime type enforcement**: PHP validates types during execution. Compile time only checking (type erasure) adds little value.
3. **Request response model**: Each request starts fresh. Type errors don't accumulate over long running processes.
4. **Built in array functions**: `sort()`, `array_map()`, `array_filter()` already work with any array. No need to write generic algorithms.

Generics are a solution to problems PHP solved differently from the start. What PHP developers actually need is type declarations for the containers they already have.

### What This RFC Provides

Rather than introducing a general purpose generics system, typed arrays address specific needs directly:

| Need | Typed Arrays Solution |
|------|----------------------|
| Type safe collections | `array<User>` |
| Structured data | `array{id: int, name: string}` |
| Runtime validation | Built in enforcement |
| IDE support | Native type declarations |
| External data validation | Shape validation at boundaries |

Typed arrays and array shapes cover the majority of cases where developers reach for generic types in other languages, with native runtime validation that type erased generics cannot provide.

## Backward Compatibility

This proposal is fully backward compatible:

1. New syntax is opt-in via return/parameter type declarations
2. Existing code without typed array syntax continues to work unchanged
3. `shape` is a new keyword only valid at file scope for shape declarations
4. Plain `array` type hints remain valid and unaffected

## Future Scope

Potential future enhancements (not part of this RFC):

1. **Readonly shapes**: Immutable array structures
2. **Shape variance in inheritance**: Covariant/contravariant field types

## Source Code

- **Docker Image:** `ghcr.io/signalforger/php-array-shapes:latest`
- **Fork:** https://github.com/signalforger/php-src/tree/feature/array-shapes
- **Patch:** See `array-shapes.patch` in this repository
- **Build scripts:**
  - `build-docker.sh` - Build Docker image locally
  - `build-php-array-shapes.sh` - Compile PHP from source

## Changelog

- **v1.0 (2024-12-25):** Initial draft
- **v1.1 (2025-12-27):** Added performance optimizations
- **v1.2 (2025-12-28):** Added parameter types, `array<K, V>` map types, optional keys
- **v1.3 (2025-12-30):** Fixed nested `array<array<T>>` parsing
- **v1.4 (2025-12-31):** Added `shape` keyword, autoloading, `shape_exists()`
- **v2.0 (2026-01-03):** Always-on validation, code quality improvements, updated documentation
- **v2.1 (2026-01-03):** Added property type support for typed arrays and array shapes
- **v2.2 (2026-01-03):** Added closed shapes `array{...}!` for strict key validation
- **v2.3 (2026-01-03):** Added performance optimizations: element/key type caching, SIMD validation, class entry caching, object array optimizations
- **v2.4 (2026-01-04):** Added shape inheritance (`extends`), `::shape` syntax, compile-time validation for shape/class cross-inheritance
- **v2.5 (2026-01-06):** Added shape autoloading, type covariance validation for inheritance, cached expected keys for closed shapes, string interning for shape keys

## References

### Static Analysis Tools

- [Psalm array shapes](https://psalm.dev/docs/annotating_code/type_syntax/array_types/)
- [PHPStan array shapes](https://phpstan.org/writing-php-code/phpdoc-types#array-shapes)

### Related RFCs

- [Generics RFC (2016, Declined)](https://wiki.php.net/rfc/generics)
- [Union Types 2.0 (Accepted)](https://wiki.php.net/rfc/union_types_v2)
- [Typed Properties 2.0 (Accepted)](https://wiki.php.net/rfc/typed_properties_v2)

### Other Languages

- [Hack shapes](https://docs.hhvm.com/hack/built-in-types/shapes)
- [TypeScript](https://www.typescriptlang.org/docs/handbook/2/objects.html)
- [Python TypedDict (PEP 589)](https://peps.python.org/pep-0589/)

## Copyright

This document is placed in the public domain or under CC0-1.0-Universal license, whichever is more permissive.
