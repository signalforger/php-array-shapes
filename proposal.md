# RFC: Typed Arrays for PHP

* Version: 1.0
* Date: 2026-01-06
* Author: Signalforger
* Status: Draft
* Target Version: PHP 8.5

## Introduction

This RFC proposes adding **typed arrays** to PHP: a focused enhancement that brings type safety to PHP's most used data structure without the complexity of full generics.

## The Problem

PHP's `array` type provides no information about its contents:

```php
function getUsers(): array {
    // Returns what? User objects? Associative arrays? Integers?
    // The type system provides no guidance.
}

function processIds(array $ids): void {
    // Caller can pass anything: strings, objects, mixed types
    // Errors discovered only when code runs
}
```

This leads to:
- **Runtime errors** discovered late in execution
- **Poor IDE support** - no autocomplete for array contents
- **Documentation burden** - PHPDoc comments that can drift from reality
- **Defensive coding** - manual `is_array()` and type checks throughout code

## The Solution: Typed Arrays

### Basic Syntax

```php
// List of integers
function getIds(): array<int> {
    return [1, 2, 3];
}

// List of objects
function getUsers(): array<User> {
    return [$user1, $user2];
}

// Union types
function getValues(): array<int|string> {
    return [1, "two", 3];
}
```

### Map Types

```php
// String keys, integer values
function getScores(): array<string, int> {
    return ['alice' => 95, 'bob' => 87];
}

// Integer keys, object values
function getUsersById(): array<int, User> {
    return [1 => $admin, 2 => $guest];
}
```

### Nested Arrays

```php
// Matrix of integers
function getMatrix(): array<array<int>> {
    return [[1, 2], [3, 4]];
}

// List of score maps
function getAllScores(): array<array<string, int>> {
    return [
        ['math' => 95, 'english' => 87],
        ['math' => 82, 'english' => 91]
    ];
}
```

## Why Typed Arrays?

### 1. Arrays Are Ubiquitous in PHP

Unlike languages where arrays are rarely used directly (Java's `ArrayList`, C#'s `List<T>`), PHP developers use arrays constantly:

```php
// Database results
$users = $pdo->fetchAll(PDO::FETCH_ASSOC);

// API responses
$data = json_decode($response, true);

// Configuration
$config = require 'config.php';

// Collection operations
$ids = array_map(fn($u) => $u->id, $users);
```

Every PHP application has hundreds of array usages. Typed arrays address this directly.

### 2. Runtime Validation Matters

Static analysis tools (PHPStan, Psalm) provide compile-time checking via docblocks. But PHP is a runtime language - data comes from:

- Database queries (PDO returns arrays)
- API responses (`json_decode` returns arrays)
- User input (`$_POST`, `$_GET`)
- File parsing (CSV, JSON, XML)

Static analysis cannot validate external data. Native typed arrays catch errors when they happen:

```php
function processIds(array<int> $ids): void {
    // Type error thrown immediately if $ids contains non-integers
    foreach ($ids as $id) {
        $this->process($id);  // Guaranteed to be int
    }
}

// Called with API data
$data = json_decode($apiResponse, true);
processIds($data['user_ids']);  // Validated at runtime
```

### 3. Simpler Than Generics

Full generics would require:
- Type parameter syntax for classes/functions
- Variance declarations (covariant, contravariant, invariant)
- Type erasure vs reification decisions
- Bounded type parameters
- Generic method syntax

Typed arrays provide 80% of the benefit with 20% of the complexity:

| Feature | Typed Arrays | Full Generics |
|---------|--------------|---------------|
| Type-safe collections | Yes | Yes |
| Runtime validation | Yes | Depends on implementation |
| Syntax complexity | Minimal | Significant |
| Learning curve | Low | High |
| Implementation effort | Moderate | Very high |

### 4. Follows Existing PHP Patterns

The syntax mirrors what developers already write in PHPDoc:

```php
// Current (docblock)
/** @param int[] $ids */
function process(array $ids): void {}

// Proposed (native)
function process(array<int> $ids): void {}
```

And matches static analysis tool syntax:

```php
// PHPStan/Psalm
/** @return array<string, User> */

// Proposed
function getUsers(): array<string, User> {}
```

## Validation Behavior

### Always-On Validation

When a typed array is declared, validation is enforced:

```php
function getIds(): array<int> {
    return [1, "two", 3];  // TypeError thrown
}
```

### Clear Error Messages

```php
TypeError: getIds(): Return value must be of type array<int>,
           array element at index 1 is string
```

```php
TypeError: processScores(): Argument #1 ($scores) must be of type
           array<string, int>, array element at key "bob" is string
```

### Property Types

```php
class UserRepository {
    public array<User> $users = [];

    public function add(User $user): void {
        $this->users[] = $user;  // Validated
    }
}

$repo = new UserRepository();
$repo->users = ["not a user"];  // TypeError
```

## Performance

The implementation includes optimizations to minimize validation overhead:

1. **Type caching** - Validated type stored in array metadata; subsequent checks are O(1)
2. **Packed array fast path** - Sequential integer-keyed arrays skip key validation
3. **Class entry caching** - Class lookups cached thread-locally
4. **SIMD validation** - Large numeric arrays validated 8 elements at a time (AVX2)

Benchmarks show negligible impact for typical array sizes (<1000 elements) and ~15% overhead for very large arrays - comparable to existing type declaration overhead.

## Backward Compatibility

This proposal is fully backward compatible:

1. New syntax is opt-in via type declarations
2. Existing `array` type hints continue to work unchanged
3. No reserved words added
4. Code without typed arrays behaves identically

## Extension: Array Shapes (Structured Data)

While typed arrays handle collections, PHP developers also need structured data validation. Array shapes complement typed arrays:

```php
// Typed array: list of things
function getIds(): array<int> { ... }

// Array shape: structured record
function getUser(): array{id: int, name: string, email: string} { ... }
```

Array shapes are covered in detail in the companion RFC section, but the key insight is:
- **Typed arrays** = collections (homogeneous elements)
- **Array shapes** = records (heterogeneous fields)

Both address the "array contains what?" problem from different angles.

## Comparison to Other Languages

| Language | Collection Types | How |
|----------|------------------|-----|
| TypeScript | `number[]`, `Array<T>` | Compile-time only |
| Python | `list[int]` (PEP 585) | Runtime via typing module |
| Java | `List<Integer>` | Type erasure (compile-time) |
| C# | `List<int>` | Reified generics (runtime) |
| Hack | `vec<int>` | Runtime validated |
| **PHP (proposed)** | `array<int>` | Runtime validated |

PHP's approach is closest to Hack's - runtime validation with native syntax.

## Implementation Status

A working proof-of-concept implementation exists:

- Full parser/lexer integration
- Runtime validation in Zend engine
- Reflection API support (`ReflectionTypedArrayType`)
- 47 passing tests
- Performance optimizations implemented

Docker image available: `ghcr.io/signalforger/php-array-shapes:latest`

## Vote

This RFC requires 2/3 majority to pass.

## Conclusion

Typed arrays address a real pain point for PHP developers: knowing what's in an array. The proposal is:

- **Focused** - Solves one problem well
- **Pragmatic** - Runtime validation for a runtime language
- **Familiar** - Matches existing PHPDoc/static analysis syntax
- **Backward compatible** - Opt-in, no breaking changes
- **Implementable** - Working proof-of-concept exists

PHP arrays are powerful and ubiquitous. Typed arrays make them type-safe.
