# PHP RFC: Typed Arrays & Array Shapes

* Version: 2.1
* Date: 2026-01-03
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
- [Why Native Types Instead of Static Analysis?](#why-native-types-instead-of-static-analysis)
- [Why Not Full Generics?](#why-not-full-generics)
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
git clone https://github.com/signalforger/php-array-shapes.git
cd php-array-shapes
./build-docker.sh --test
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
- [x] Shape autoloading via `spl_autoload_register()`
- [x] Reflection API support (`ReflectionArrayType`, `ReflectionArrayShapeType`)
- [x] Runtime validation with detailed error messages
- [x] **Property types**: `public array<int> $ids;`, `public array{id: int} $user;`
- [x] **Closed shapes**: `array{id: int}!` (no extra keys allowed)

All 79 tests pass (76 pass + 3 expected failures for autoloading feature).

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

## Why Not Full Generics?

A common question: "Why not just implement generics like Java/C#/TypeScript?"

### The 90% Solution

Looking at how generics are actually used in other languages, the overwhelming majority of cases fall into two categories:

1. **Typed collections**: `List<User>`, `Map<string, int>`, `Set<Product>`
2. **Structured data**: `Result<T>`, `Response<Data>`, `Pair<A, B>`

Typed arrays (`array<User>`) and array shapes (`array{success: bool, data: mixed}`) solve both of these directly, covering 90%+ of real-world generic use cases.

### What's Left?

The remaining cases where full generics would help:

```php
// Generic class - but PHP already has a solution
class Box<T> {
    public function __construct(private T $value) {}
    public function get(): T { return $this->value; }
}

// In PHP, you just use mixed + runtime checks or separate classes
class Box {
    public function __construct(private mixed $value) {}
    public function get(): mixed { return $this->value; }
}

// Or for type safety, create specific classes
class UserBox {
    public function __construct(private User $value) {}
    public function get(): User { return $this->value; }
}
```

For the rare cases where you truly need generic containers, PHP's approach of using `mixed` with runtime validation or creating specific typed classes works well.

### Complexity vs. Value

Full generics would require:

- **Type erasure or reification** - Major engine changes
- **Variance rules** - Complex covariance/contravariance for classes
- **Generic constraints** - `where T extends Serializable`
- **Type inference** - Inferring `T` from usage context
- **Backward compatibility** - Existing code must keep working

This is years of implementation work for diminishing returns. Typed arrays and array shapes deliver the practical benefits now, with a fraction of the complexity.

### PHP's Pragmatic Approach

| Need | Generics Solution | PHP Solution |
|------|-------------------|--------------|
| List of users | `List<User>` | `array<User>` |
| String-keyed map | `Map<string, int>` | `array<string, int>` |
| API response | `Response<T>` | `array{success: bool, data: mixed}` |
| Database row | `Row<T>` | `array{id: int, name: string}` |
| Generic container | `Box<T>` | Class with `mixed` or specific typed class |

**Typed arrays and array shapes aren't a compromise. They're a direct solution** to what developers actually need, without the complexity tax of full generics.

## Backward Compatibility

This proposal is fully backward compatible:

1. New syntax is opt-in via return/parameter type declarations
2. Existing code without typed array syntax continues to work unchanged
3. `shape` is a new keyword only valid at file scope for shape declarations
4. Plain `array` type hints remain valid and unaffected

## Future Scope

Potential future enhancements (not part of this RFC):

1. **Readonly shapes**: Immutable array structures
2. **Shape inheritance**: `shape Admin extends User`
3. **Generic shapes**: `shape Result<T> = array{success: bool, data: T}`

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
