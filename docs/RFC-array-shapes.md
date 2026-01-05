# RFC: Typed Arrays & Array Shapes for PHP

* Version: 1.3
* Date: 2026-01-04
* Author: PHP Array Shapes Implementation
* Status: Implemented (Proof of Concept)
* New in 1.3: Compile-time validation for shape/class cross-inheritance
* New in 1.2: Shape inheritance (`extends`) and `::shape` syntax

## Introduction

This RFC proposes adding **Typed Arrays** and **Array Shapes** to PHP—two complementary
features that bring type safety to PHP's most versatile data structure. These features
address different use cases: typed arrays for **collections** and array shapes for
**structured data**.

## Motivation

PHP arrays serve multiple purposes: lists, dictionaries, records, and complex nested
structures. However, the type system only allows declaring a value as `array` without
specifying what it contains:

```php
function getUsers(): array {
    // What's in this array? Objects? Associative arrays? Integers?
    // The type system can't tell you.
}
```

This leads to:

1. **Runtime errors** - Type mismatches discovered only during execution
2. **Poor IDE support** - Limited autocomplete and refactoring capabilities
3. **Documentation burden** - Developers must rely on PHPDoc annotations
4. **Maintenance issues** - Changing array structures requires manual updates

Static analysis tools like PHPStan and Psalm have introduced PHPDoc-based array
shape syntax, demonstrating strong community demand for this feature.

## Two Complementary Features

### Typed Arrays — For Collections

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
```

### Array Shapes — For Structured Data

When you have **structured data with known keys**, like records from a database or
responses from an API, use array shapes:

```php
// Data from a database row
function getUser(int $id): array{id: int, name: string, email: string} {
    return $this->db->fetch("SELECT id, name, email FROM users WHERE id = ?", $id);
}

// Response from an external API
function getWeather(string $city): array{temp: float, humidity: int} {
    return json_decode(file_get_contents("https://api.weather.com/$city"), true);
}
```

### Why Not Just Use Classes/DTOs?

A common question: "With constructor property promotion, classes are almost as concise.
Why do we need array shapes?"

#### Side-by-Side Comparison

```php
// With array shapes (this RFC)
shape UserResponse = array{id: int, name: string, email: ?string};

function getUser(): UserResponse {
    return ['id' => 1, 'name' => 'Alice', 'email' => null];
}

// With classes + constructor property promotion
readonly class UserResponse {
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email,
    ) {}
}

function getUser(): UserResponse {
    return new UserResponse(id: 1, name: 'Alice', email: null);
}
```

At first glance, these look similar. But there are fundamental differences:

#### 1. JSON Serialization

```php
// Array shapes: direct serialization
$user = getUser();  // Returns array
echo json_encode($user);  // {"id":1,"name":"Alice","email":null}

// Classes: requires extra work
$user = getUser();  // Returns object
echo json_encode($user);  // {} (empty without JsonSerializable!)

// Must implement JsonSerializable or add toArray():
readonly class UserResponse implements JsonSerializable {
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email,
    ) {}

    public function jsonSerialize(): array {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email];
    }
}
```

For API responses, you need `json_encode()` to just work. With array shapes, it does.
With classes, you must implement `JsonSerializable` for **every single DTO**.

#### 2. Working with Existing Data Sources

```php
// PDO returns arrays
$row = $pdo->fetch(PDO::FETCH_ASSOC);  // array{id: int, name: string, ...}

// json_decode returns arrays
$data = json_decode($json, true);  // array{...}

// config files return arrays
$config = require 'config.php';  // array{...}

// With array shapes: use directly
function processUser(UserResponse $user): void { ... }
processUser($row);  // Works!

// With classes: must transform everything
processUser(new UserResponse(...$row));  // Extra allocation + mapping
```

Array shapes work with the data you already have. Classes require transformation.

#### 3. Array Functions and Operations

```php
// Array shapes: native array operations work
$users = getUsers();  // array<UserResponse>
$names = array_column($users, 'name');
$filtered = array_filter($users, fn($u) => $u['id'] > 10);
$mapped = array_map(fn($u) => $u['name'], $users);
$merged = [...$user1, ...$user2];  // Spread operator
['id' => $id, 'name' => $name] = $user;  // Destructuring

// Classes: none of these work directly
$users = getUsers();  // array<UserResponse>
$names = array_map(fn($u) => $u->name, $users);  // Must use closures
$filtered = array_filter($users, fn($u) => $u->id > 10);
// No spread, no array_column, no destructuring
```

#### 4. No File/Class Boilerplate

```php
// Array shapes: define inline or in any file
function getPoint(): array{x: int, y: int} {
    return ['x' => 10, 'y' => 20];
}

// Or define once, use anywhere
shape Point = array{x: int, y: int};

// Classes: each needs its own file (PSR-4), own namespace, own declaration
// src/DTO/Point.php
namespace App\DTO;

readonly class Point {
    public function __construct(
        public int $x,
        public int $y,
    ) {}
}
```

For a complex API with 50+ response types, that's 50+ class files vs one `shapes.php`.

#### 5. Memory and Performance

```php
// Array: ~400 bytes for small associative array
$user = ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];

// Object: ~600+ bytes (object overhead, property table, class entry reference)
$user = new UserResponse(1, 'Alice', 'alice@example.com');
```

When processing thousands of records, this adds up. Arrays are PHP's most optimized
data structure.

#### 6. Framework Expectations

Many frameworks expect arrays:

```php
// Laravel
return response()->json($data);  // Expects array
Model::create($attributes);       // Expects array
DB::table('users')->insert($data); // Expects array

// Symfony
return $this->json($data);        // Expects array
$serializer->serialize($data);    // Handles arrays natively
```

#### When to Use Classes Instead

Classes are the right choice when you need:

- **Behavior** (methods that operate on the data)
- **Encapsulation** (private properties, validation in constructor)
- **Identity** (instanceof checks, type hierarchies)
- **Mutability control** (readonly properties with controlled modification)

Array shapes are the right choice when you have:

- **Pure data** (no behavior needed)
- **External data sources** (APIs, databases, config files)
- **Serialization needs** (JSON responses)
- **Existing array-based code** (gradual typing of legacy code)

#### Summary

| Feature | Array Shapes | Classes (CPP) |
|---------|--------------|---------------|
| JSON serialization | Direct | Requires JsonSerializable |
| PDO/json_decode | Direct | Requires transformation |
| array_map/filter | Native | Requires closures |
| Spread operator | Yes | No |
| Destructuring | Yes | No |
| Memory overhead | Minimal | Higher |
| File per type | No | Yes (PSR-4) |
| Inline definition | Yes | No |
| Methods | No | Yes |
| Private properties | No | Yes |

Array shapes and classes serve different purposes. This RFC doesn't replace classes—
it provides first-class typing for the millions of lines of PHP that already use
arrays for data interchange.

## Proposal

### 1. Typed Arrays (`array<T>`)

Specify that all elements of an array must be of a certain type:

```php
function getIds(): array<int> {
    return [1, 2, 3];
}

function getUsers(): array<User> {
    return [new User("Alice"), new User("Bob")];
}

function getValues(): array<int|string> {
    return [1, "two", 3];
}
```

### 2. Key-Value Typed Arrays (`array<K, V>`)

Specify both key and value types:

```php
function getScores(): array<string, int> {
    return ['alice' => 95, 'bob' => 87];
}

function getUsersById(): array<int, User> {
    return [1 => new User("Alice"), 2 => new User("Bob")];
}
```

### 3. Array Shapes (`array{key: type}`)

Define the exact structure of associative arrays:

```php
function getUser(): array{id: int, name: string, email: string} {
    return ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
}
```

#### Optional Keys

Use `?` after the key name to mark it as optional:

```php
function getConfig(): array{debug: bool, cache_ttl?: int} {
    return ['debug' => true];  // cache_ttl is optional
}
```

#### Nullable Values

Use `?` before the type to allow null values:

```php
function getUser(): array{name: string, email: ?string} {
    return ['name' => 'Alice', 'email' => null];  // email can be null
}
```

#### Nested Shapes

Shapes can be nested arbitrarily:

```php
function getResponse(): array{
    success: bool,
    data: array{
        user: array{id: int, name: string},
        permissions: array<string>
    },
    error: ?string
} {
    // ...
}
```

### 4. Shape Type Aliases (`shape`)

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

#### Shape Inheritance

Shapes can extend other shapes using the `extends` keyword. The child shape
inherits all properties from the parent and can add new ones or override existing ones:

```php
shape BaseEntity = array{id: int, created_at: string};
shape User extends BaseEntity = array{name: string, email: string};
shape Admin extends User = array{role: string, permissions: array<string>};

// User has: id, created_at, name, email
// Admin has: id, created_at, name, email, role, permissions
```

Inheritance is resolved at compile time (flattened), so there's no runtime
overhead. The child shape contains all properties from the entire inheritance chain.

**Property Override:**

Child shapes can override parent properties with a different type:

```php
shape Base = array{value: string};
shape Child extends Base = array{value: int};  // Override string to int

// Child's 'value' is now int, not string
```

**Restrictions:**

Shapes and classes are separate concepts and cannot be mixed in inheritance:

- Shapes cannot extend classes
- Classes cannot extend shapes

These restrictions are enforced at **compile time** with clear error messages:

```php
// Shape trying to extend a class
class MyClass {}
shape BadShape extends MyClass = array{id: int};
// Fatal error: Shape BadShape cannot extend class MyClass

// Class trying to extend a shape
shape MyShape = array{id: int, name: string};
class BadClass extends MyShape {}
// Fatal error: Class BadClass cannot extend shape MyShape
```

#### The `::shape` Syntax

Similar to `::class` for classes, shapes support the `::shape` syntax to get
the fully qualified name of a shape:

```php
shape UserShape = array{id: int, name: string};

echo UserShape::shape;  // "UserShape"
```

With namespaces:

```php
namespace App\Types;

shape UserShape = array{id: int, name: string};

echo UserShape::shape;  // "App\Types\UserShape"
```

This is useful for logging, debugging, and working with shape names dynamically.

**Note:** Using `::shape` on a class results in a compile error:

```php
class MyClass {}
echo MyClass::shape;  // Error: Cannot use ::shape on class MyClass, use ::class instead
```

#### Shape Autoloading

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

#### shape_exists() Function

Check if a shape type alias is defined:

```php
// Check without triggering autoload
if (shape_exists('User', false)) { ... }

// Check with autoloading (default)
if (shape_exists('User')) { ... }
```

## Runtime Behavior

### Always-On Validation

Typed array and array shape validation is **always enabled**. When a type constraint
is declared, it is enforced at runtime:

```php
function getIds(): array<int> {
    return [1, "two", 3];  // TypeError at runtime
}

function getUser(): array{id: int, name: string} {
    return ['id' => 1];  // TypeError: missing required key 'name'
}
```

### Error Messages

Type errors provide detailed, actionable information:

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

## Syntax Grammar

```
array_type:
    'array' '<' type_list '>'                    // array<T> or array<K,V>
  | 'array' '{' shape_element_list '}'           // array{...}
  ;

shape_element_list:
    shape_element (',' shape_element)* ','?
  ;

shape_element:
    T_STRING '?'? ':' type                       // key?: type
  ;

shape_declaration:
    'shape' T_STRING shape_extends? '=' array_type ';'
  ;

shape_extends:
    'extends' name                               // shape inheritance
  ;

shape_name_access:
    name '::' 'shape'                            // MyShape::shape
  ;
```

## Comparison with Existing Solutions

### PHPDoc Annotations

```php
/** @return array{id: int, name: string} */
function getUser(): array { ... }
```

**Limitations:**
- No runtime validation
- Inconsistent syntax across tools
- Separated from actual code

### This Proposal

```php
function getUser(): array{id: int, name: string} { ... }
```

**Benefits:**
- Native language syntax
- Optional runtime validation
- IDE support via reflection
- Consistent across all tools

## Implementation Notes

### Compile-Time Validation

The compiler performs several validations at compile time to catch errors early:

| Error Condition | Error Message |
|----------------|---------------|
| Shape extends a class | `Shape X cannot extend class Y` |
| Class extends a shape | `Class X cannot extend shape Y` |
| Shape redeclaration | `Cannot redeclare shape X` |
| `::shape` used on a class | `Cannot use ::shape on class X, use ::class instead` |
| `::class` used on a shape | `Cannot use ::class on shape X, use ::shape instead` |

These compile-time checks ensure that shape inheritance and naming syntax are used correctly, providing immediate feedback during development rather than runtime errors.

### Compile-Time Optimization

The implementation uses escape analysis to optimize constant array validation
at compile time, avoiding runtime overhead where possible.

### Memory Considerations

Shape type information is stored efficiently:
- Inline shapes store structure in the type itself
- Shape aliases store a reference to a global shape table
- Shapes are interned and shared across functions

### Autoloading Integration

Shape autoloading uses the existing `spl_autoload` infrastructure:
- Same autoloader handles both classes and shapes
- Recursive autoload protection
- Thread-safe implementation

## Backward Compatibility

This proposal is fully backward compatible:

1. New syntax is opt-in via return/parameter type declarations
2. Existing code without typed array syntax continues to work unchanged
3. `shape` is a new keyword only valid at file scope for shape declarations
4. Plain `array` type hints remain valid and unaffected

## Future Scope

Potential future enhancements (not part of this RFC):

1. **Class property types**: `public User $user;`
2. **Readonly shapes**: Immutable array structures
3. **Generic shapes**: `shape Result<T> = array{success: bool, data: T}`

**Note:** Shape inheritance (`shape Admin extends User`) and the `::shape` syntax
are now implemented and documented above.

## Examples

### API Response

```php
shape ApiResponse = array{
    success: bool,
    data: mixed,
    error: ?string,
    meta: array{timestamp: string, version: string}
};

function apiSuccess(mixed $data): ApiResponse {
    return [
        'success' => true,
        'data' => $data,
        'error' => null,
        'meta' => ['timestamp' => date('c'), 'version' => '1.0']
    ];
}
```

### Configuration

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
    environment: string,
    database: DatabaseConfig,
    cache: array{driver: string, ttl: int}
};

function loadConfig(string $path): AppConfig { ... }
```

### Repository Pattern

```php
shape UserRecord = array{id: int, name: string, email: string, created_at: string};

class UserRepository {
    // Single record
    public function find(int $id): ?UserRecord { ... }

    // Collection of records — combining both features
    public function findAll(): array<UserRecord> { ... }

    public function save(UserRecord $user): UserRecord { ... }
    public function delete(int $id): bool { ... }
}
```

## Conclusion

This RFC provides two complementary features for typed arrays in PHP:

- **Typed Arrays** (`array<T>`) for collections of the same type
- **Array Shapes** (`array{key: type}`) for structured data with known keys

Together, they address a long-standing limitation in PHP's type system while
maintaining full backward compatibility. These features are designed for working
with arrays—not as a replacement for objects, but as a complement for the many
situations where arrays are the right tool: database results, API responses,
configuration files, and more.

The implementation has been tested and all PHP tests pass.
