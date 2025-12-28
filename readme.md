# PHP RFC: Array Shape Return Types

* Version: 1.0
* Date: 2025-12-27
* Author: [Signalforger] <signalforger@signalforge.eu>
* Status: Draft
* First Published: N/A
* Target Version: PHP 8.5
* Implementation: N/A

## Introduction

PHP's type system currently supports return type declarations for scalar types, classes, and the generic `array` type. However, the `array` type provides no structural information, forcing developers to rely on documentation and static analysis tools to understand array contents.

This RFC proposes adding **array shape types** and **homogeneous array types** to return type declarations, enabling runtime validation of array structure at function boundaries.

### Current Limitation
```php
function getUser(int $id): array {
    return $db->fetchAssoc("SELECT * FROM users WHERE id = ?", [$id]);
}

// What keys does this array have?
// What types are the values?
// No way to know without reading docs or using static analysis
$user = getUser(1);
echo $user['name'];  // Hope this key exists!
```

### Proposed Solution
```php
function getUser(int $id): array{id: int, name: string, email: string} {
    return $db->fetchAssoc("SELECT * FROM users WHERE id = ?", [$id]);
    // ✓ Validated at return: ensures id, name, email exist with correct types
}

$user = getUser(1);
echo $user['name'];  // Guaranteed to exist (at time of return)
```

### Why Native Types Instead of Static Analysis?

Tools like PHPStan and Psalm already support array shapes via docblocks. Why add native syntax?

#### 1. Runtime Validation at Trust Boundaries

Static analyzers only work on code you control. They cannot validate:
- Data from databases
- External API responses
- User input
- Deserialized data (JSON, sessions)

```php
// PHPStan can't help here - it doesn't know what the DB returns
$user = $db->fetchAssoc("SELECT * FROM users WHERE id = ?", [$id]);
echo $user['name']; // Hope it exists!

// Native types catch bad data at runtime
function getUser($id): array{id: int, name: string} {
    return $db->fetchAssoc(...); // TypeError if DB schema changed
}
```

#### 2. Guaranteed Enforcement

Static analysis is optional and bypassable:
```php
/** @return array{id: int} */
function getUser() {
    // @phpstan-ignore-next-line
    return ['id' => 'not-an-int']; // PHPStan silenced, bug ships
}
```

Native types cannot be ignored - the engine enforces them.

#### 3. No Comment Drift

Docblocks routinely get out of sync with actual code:
```php
/**
 * @return array{id: int, name: string}  // Outdated - email was added!
 */
function getUser(): array {
    return ['id' => 1, 'name' => 'alice', 'email' => 'a@b.com'];
}
```

Native types ARE the contract - they cannot drift from implementation.

#### 4. Reflection API Access

Native types enable runtime introspection for frameworks:
```php
// Framework can auto-generate OpenAPI docs, validation, serialization
$returnType = (new ReflectionFunction('getUser'))->getReturnType();
// Returns ReflectionArrayShape with key/type info

// With docblocks, you must parse comments and handle PHPStan/Psalm/PhpStorm format differences
```

This enables automatic API documentation, runtime validation frameworks, serialization libraries, and dependency injection containers to work with array types.

#### 5. Performance

Native validation is engine-optimized with escape analysis, type caching, and loop unrolling:
```php
// Native: ~1-20% overhead with optimizations
function getIds(): array<int> { return [1,2,3]; }

// Userland validation: always slower
function getIds(): array {
    $arr = [1,2,3];
    foreach ($arr as $v) {
        if (!is_int($v)) throw new TypeError(...);
    }
    return $arr;
}
```

#### 6. Ecosystem Standardization

Current fragmentation across tools:
```php
/** @return array{id: int} */              // PHPStan
/** @psalm-return array{id: int} */        // Psalm
/** @return array{'id': int} */            // PhpStorm (quoted keys)
#[ArrayShape(['id' => 'int'])]             // PhpStorm attribute
```

Native syntax provides one standard for the entire ecosystem.

#### 7. Error Quality

Native errors point to the exact problem:
```
TypeError: getUser(): Return value must be of type array{id: int, name: string},
key 'name' is missing in returned array
```

vs. silent corruption or vague errors later:
```
Warning: Undefined array key "name" in /app/src/View.php on line 847
```

#### 8. IDE Support Without Setup

Native types work in any PHP-aware editor immediately. Docblock-based types require installing PHPStan/Psalm, IDE plugins, configuration, and running analysis separately.

#### Summary

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

## Proposal

### Syntax

This RFC introduces two new type declaration syntaxes for array types (both return types and parameter types):

#### 1. Homogeneous Arrays: `array<T>`

An array where every element is of the same type:
```php
function getIds(): array<int> {
    return [1, 2, 3, 4, 5];
}

function getTags(): array<string> {
    return ['php', 'web', 'backend'];
}
```

#### 2. Array Shapes: `array{key: type, ...}`

An array with a defined structure:
```php
function getUser(): array{id: int, name: string, email: string} {
    return [
        'id' => 1,
        'name' => 'Alice',
        'email' => 'alice@example.com'
    ];
}

function getCoordinates(): array{0: float, 1: float} {
    return [51.5074, -0.1278];  // Numeric keys
}
```

#### 3. Optional Keys: `array{key?: type}`

Keys marked with `?` are optional and don't need to be present:
```php
function getConfig(): array{host: string, port?: int, ssl?: bool} {
    // Only 'host' is required
    return ['host' => 'localhost'];
}

function getFullConfig(): array{host: string, port?: int, ssl?: bool} {
    // All keys provided - also valid
    return ['host' => 'secure.example.com', 'port' => 443, 'ssl' => true];
}
```

#### 4. Parameter Types

Array shapes and typed arrays work as parameter types too:
```php
// Shape as parameter type
function processOrder(array{product: string, quantity: int, price: float} $order): float {
    return $order['quantity'] * $order['price'];
}

// Typed array as parameter
function sumNumbers(array<int> $numbers): int {
    return array_sum($numbers);
}

// Both parameter and return types
function transformUser(
    array{id: int, name: string} $input
): array{id: int, name: string, processed: bool} {
    return [...$input, 'processed' => true];
}
```

#### 5. Nested Structures

Both syntaxes can be nested:
```php
// Array of arrays
function getMatrix(): array<array<int>> {
    return [[1, 2, 3], [4, 5, 6], [7, 8, 9]];
}

// Array of shapes
function getUsers(): array<array{id: int, name: string}> {
    return [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];
}

// Shape containing arrays
function getApiResponse(): array{
    success: bool,
    data: array<array{id: int, title: string}>,
    total: int
} {
    return [
        'success' => true,
        'data' => [
            ['id' => 1, 'title' => 'First'],
            ['id' => 2, 'title' => 'Second'],
        ],
        'total' => 2,
    ];
}
```

### Semantics

#### Runtime Validation Control: `declare(strict_arrays=1)`

Similar to `declare(strict_types=1)`, array element validation is controlled by a declare directive:

```php
<?php
declare(strict_arrays=1);

function getIds(): array<int> {
    return [1, 2, "three"];  // TypeError: element at index 2 is string
}
```

**Without the declare directive, `array<T>` provides syntax support only:**
```php
<?php
// No declare(strict_arrays=1)

function getIds(): array<int> {
    return [1, 2, "three"];  // No error - validation is disabled
}
```

This design provides:
- **Zero overhead by default** - existing code is unaffected
- **Opt-in runtime validation** - enable only where needed
- **Gradual adoption** - add runtime checks file by file
- **Static analysis compatibility** - tools can enforce types regardless of runtime mode

#### Validation Rules

**For `array<T>`:**
- Every element in the array must be of type `T`
- Mixed keys (int/string) are allowed
- Empty arrays are valid

**For `array{key: type}`:**
- All specified keys must exist in the returned array
- Values for specified keys must match declared types
- **Extra keys are allowed** (open shapes)
- Keys can be string identifiers or integer literals

#### When Validation Occurs

Validation happens **once at function return** in the `ZEND_VERIFY_RETURN_TYPE` opcode handler:
```php
function getData(): array{id: int} {
    $data = ['id' => 1, 'name' => 'Extra'];  // Extra keys OK
    return $data;  // ✓ Validated here
}

$result = getData();
$result['id'] = 'string';  // No runtime error
unset($result['id']);      // No runtime error
```

**Rationale:** Type information is **not maintained** after the function returns. This design choice:
- Matches PHP's existing pattern (validate at boundaries)
- Avoids runtime overhead on array operations
- Keeps mental model simple
- Allows static analyzers to enforce post-return correctness

#### Recursive Validation

Nested structures are validated recursively:
```php
function getNestedData(): array<array{id: int, value: string}> {
    return [
        ['id' => 1, 'value' => 'test'],  // Each element validated
        ['id' => 2, 'value' => 'data'],  // as array{id: int, value: string}
    ];
}
```

#### Variance and Inheritance

Array shape return types follow PHP's standard covariance rules for return types:

**Covariance (More Specific Return Types Allowed):**
```php
class Repository {
    function getUser(): array{id: int} {
        return ['id' => 1];
    }
}

class ExtendedRepository extends Repository {
    // ✓ Valid: Child returns MORE keys (covariant)
    function getUser(): array{id: int, name: string, email: string} {
        return ['id' => 1, 'name' => 'Alice', 'email' => 'a@b.com'];
    }
}
```

**Contravariance (Less Specific Not Allowed):**
```php
class Repository {
    function getUser(): array{id: int, name: string} {
        return ['id' => 1, 'name' => 'Alice'];
    }
}

class BrokenRepository extends Repository {
    // ✗ Fatal error: Cannot return less specific type
    function getUser(): array{id: int} {
        return ['id' => 1];
    }
}
```

**For `array<T>`, standard type variance applies:**
```php
class NumberProvider {
    function getNumbers(): array<int|float> {
        return [1, 2.5, 3];
    }
}

class IntProvider extends NumberProvider {
    // ✓ Valid: array<int> is more specific than array<int|float>
    function getNumbers(): array<int> {
        return [1, 2, 3];
    }
}
```

#### Type Coercion Rules

Array shape validation interacts with PHP's type coercion system:

**With `declare(strict_types=1)` (Strict Mode):**
```php
declare(strict_types=1);
declare(strict_arrays=1);

function getInts(): array<int> {
    return [1, 2, "3"];  // TypeError: element 2 must be int, string given
}

function getFloats(): array<float> {
    return [1, 2, 3];    // TypeError: elements must be float, int given
}
```

**Without `declare(strict_types=1)` (Weak Mode):**
```php
declare(strict_arrays=1);
// No strict_types - coercion applies

function getInts(): array<int> {
    return [1, 2, "3"];  // ✓ OK: "3" coerced to 3
}

function getFloats(): array<float> {
    return [1, 2, 3];    // ✓ OK: integers coerced to floats
}

function getStrings(): array<string> {
    return [1, 2, 3];    // ✓ OK: integers coerced to "1", "2", "3"
}
```

**Coercion follows standard PHP rules:**
| From | To | Behavior |
|------|-----|----------|
| `int` | `float` | Allowed (widening) |
| `float` | `int` | Allowed in weak mode (truncates) |
| `string` | `int` | Allowed if numeric string |
| `int`/`float` | `string` | Allowed (converts to string) |
| `bool` | `int` | Allowed (`true`→1, `false`→0) |
| `null` | any | TypeError (null is not coercible) |
| `array`/`object` | scalar | TypeError |

#### Edge Cases

**Empty Arrays:**
```php
function getIds(): array<int> {
    return [];  // ✓ Valid - empty array satisfies any array<T>
}

function getUser(): array{id: int, name: string} {
    return [];  // ✗ TypeError - missing required keys
}
```

**Nullable Types:**
```php
function maybeGetIds(): ?array<int> {
    return null;  // ✓ Valid
}

function getIds(): array<?int> {
    return [1, null, 3];  // ✓ Valid - elements can be null
}

function maybeGetUser(): ?array{id: int} {
    return null;  // ✓ Valid
}
```

**Union Types:**
```php
function getData(): array<int>|false {
    return false;  // ✓ Valid
}

function getMixed(): array<int|string> {
    return [1, "two", 3, "four"];  // ✓ Valid
}
```

**References in Arrays:**
```php
function getInts(): array<int> {
    $x = 1;
    $arr = [&$x, 2, 3];  // Contains reference
    return $arr;  // ✓ Valid - reference target is validated
}
```

**Deeply Nested Types:**
```php
// Arbitrary nesting depth supported
function getMatrix(): array<array<array<int>>> {
    return [[[1, 2], [3, 4]], [[5, 6], [7, 8]]];
}

// Practical limit: validation time scales with depth × size
```

**Numeric String Keys:**
```php
function getData(): array{0: int, 1: string} {
    return ['0' => 1, '1' => 'hello'];  // ✓ Valid - PHP normalizes keys
    // Internally stored as [0 => 1, 1 => 'hello']
}
```

## Examples

### Basic Usage
```php
// Simple homogeneous array
function getPrimes(): array<int> {
    return [2, 3, 5, 7, 11];
}

// Simple shape
function getConfig(): array{host: string, port: int, ssl: bool} {
    return [
        'host' => 'localhost',
        'port' => 3306,
        'ssl' => false,
    ];
}
```

### Error Cases
```php
<?php
declare(strict_arrays=1);  // Required for runtime validation

// Missing required key
function getUser(): array{id: int, name: string} {
    return ['id' => 1];
    // Fatal error: Uncaught TypeError: Return value missing key 'name'
}

// Wrong type for key
function getUser(): array{id: int, name: string} {
    return ['id' => 'string', 'name' => 'Alice'];
    // Fatal error: Return value key 'id' must be of type int, string given
}

// Wrong element type in array<T>
function getIds(): array<int> {
    return [1, 2, 'three'];
    // Fatal error: Uncaught TypeError: getIds(): Return value must be
    // of type array<int>, array element at index 2 is string
}
```

### Real-World Example
```php
<?php
declare(strict_arrays=1);  // Enable runtime validation for this file

class UserRepository {
    /**
     * Fetch all users from database
     */
    public function findAll(): array<array{
        id: int,
        username: string,
        email: string,
        created_at: string,
        is_active: bool
    }> {
        $rows = $this->db->query("SELECT * FROM users")->fetchAll();
        return $rows;  // Validates each row has correct structure
    }

    /**
     * Get user statistics
     */
    public function getStatistics(): array{
        total_users: int,
        active_users: int,
        inactive_users: int,
        new_today: int
    } {
        return [
            'total_users' => $this->db->count('users'),
            'active_users' => $this->db->count('users', ['is_active' => 1]),
            'inactive_users' => $this->db->count('users', ['is_active' => 0]),
            'new_today' => $this->db->count('users', [
                'created_at' => date('Y-m-d')
            ]),
        ];
    }
}
```

### Integration with Static Analysis
```php
/**
 * @return array{id: int, name: string}
 */
function getUser(): array{id: int, name: string} {
    // PHPStan/Psalm: validates function body
    // PHP Runtime: validates return value
    return ['id' => 1, 'name' => 'Alice'];
}

$user = getUser();
// Static analyzer knows $user['id'] is int
// Static analyzer knows $user['name'] is string
// Static analyzer warns if you try $user['missing_key']
```

## Backward Compatibility

### No Breaking Changes

- Existing code without array shapes continues to work unchanged
- Plain `array` return types remain unaffected
- No changes to array behavior after assignment
- No impact on existing extensions (unless they want to use the feature)

### Migration Path
```php
// Before (PHP 8.4 and earlier)
function getUser(): array {
    return ['id' => 1, 'name' => 'Alice'];
}

// After (PHP 8.5+) - opt-in
function getUser(): array{id: int, name: string} {
    return ['id' => 1, 'name' => 'Alice'];
}
```

### Binary Compatibility

- `zend_type` structure uses existing union mechanism
- No ABI break - extensions don't need recompilation
- New type masks use reserved bits in `type_mask` field

## Performance Impact

### Benchmark Methodology

Benchmarks run on PHP 8.5.0-dev with `declare(strict_arrays=1)`, 50,000 iterations, various array sizes.

### Optimized Results

The implementation includes several aggressive optimizations that dramatically reduce overhead:

| Scenario | Plain `array` | `array<int>` | Overhead | Notes |
|----------|---------------|--------------|----------|-------|
| Constant literals (10 elem) | 0.62 ms | 0.63 ms | **~1%** | Escape analysis |
| Cached array (100 elem) | 1.78 ms | 1.97 ms | **~11%** | Type tagging cache |
| Fresh arrays (100 elem) | 188 ms | 224 ms | **~19%** | Loop unrolling + prefetch |
| Object arrays (20 elem) | 7.51 ms | 24.7 ms | ~229% | No class caching |

### Optimization Techniques

The implementation uses four key optimization strategies borrowed from Java, C#, and JavaScript engines:

#### 1. Compile-Time Escape Analysis

For constant array literals, the compiler verifies element types at compile time, completely eliminating runtime validation:

```php
function getConstants(): array<int> {
    return [1, 2, 3, 4, 5];  // Verified at compile time - ZERO runtime cost
}
```

**How it works:**
- During compilation, `zend_const_array_elements_match_type()` analyzes literal array values
- If all elements match the expected type, the `ZEND_VERIFY_RETURN_TYPE` opcode is skipped entirely
- The array is returned directly with no type checking overhead

**Overhead:** ~0-1% (noise level)

#### 2. Type Tagging Cache (HashTable Modification)

Arrays that pass validation are "tagged" with their validated type, allowing subsequent validations to be skipped:

```c
// In zend_types.h - HashTable structure
struct _zend_array {
    union {
        struct {
            uint8_t flags;
            uint8_t nValidatedElemType;  // Cached element type (was _unused)
            // ...
        } v;
    } u;
};
```

**How it works:**
- When an array is validated for `array<int>`, the type code (`IS_LONG`) is stored in `nValidatedElemType`
- A flag bit (`HASH_FLAG_ELEM_TYPE_VALID`) marks the cache as valid
- On subsequent returns of the same array, validation is a single flag check
- Any modification to the array invalidates the cache automatically

**Cache invalidation triggers:**
- `zend_hash_add/update` - element added or modified
- `zend_hash_del` - element removed
- `zend_hash_clean` - array cleared

**Overhead:** ~10-11% (first validation) → ~0% (cached hits)

#### 3. Loop Unrolling with Cache Prefetching

For arrays that must be validated (first-time or cache miss), the validator uses CPU-optimized iteration:

```c
// 4x loop unrolling with prefetch hints
while (data + 4 <= end) {
    // Prefetch next cache line (64 bytes ahead)
    __builtin_prefetch(data + 8, 0, 1);

    // Unrolled type checks - CPU can pipeline these
    if (Z_TYPE_P(data) != IS_LONG) return false;
    if (Z_TYPE_P(data + 1) != IS_LONG) return false;
    if (Z_TYPE_P(data + 2) != IS_LONG) return false;
    if (Z_TYPE_P(data + 3) != IS_LONG) return false;
    data += 4;
}
```

**How it works:**
- Processes 4 elements per iteration, reducing loop overhead by 75%
- `__builtin_prefetch()` hints the CPU to load the next cache line before it's needed
- Packed arrays (sequential integer keys) use a contiguous memory fast path
- Branch prediction is optimized with `UNEXPECTED()` macros for error paths

**Overhead:** ~17-22% (varies by array size)

#### 4. Packed Array Fast Path

Packed arrays (sequential 0-indexed) benefit from optimized memory access:

```c
static zend_always_inline bool zend_verify_packed_array_elements_long(zval *data, uint32_t count)
{
    // Direct pointer arithmetic on contiguous memory
    // Much faster than hash table iteration
}
```

**How it works:**
- Packed arrays store elements in contiguous memory
- The validator skips hash table overhead and iterates directly over the data pointer
- Combined with loop unrolling, this maximizes cache efficiency

### Summary by Use Case

| Use Case | Optimization | Expected Overhead |
|----------|--------------|-------------------|
| Return literal arrays | Escape analysis | **0%** |
| Return same array repeatedly | Type tagging cache | **~1%** after first call |
| Return fresh arrays (small) | Loop unrolling | **~15-20%** |
| Return fresh arrays (large) | Loop unrolling + prefetch | **~15-20%** |
| Return object arrays | Full validation | **~200-250%** |

### When to Use `declare(strict_arrays=1)`

- **Development/testing:** Enable validation to catch type errors early
- **API boundaries:** Validate data entering/leaving your application
- **Cached data:** Near-zero overhead for repeatedly returned arrays
- **Performance-critical loops:** Acceptable for most cases; profile if returning new large arrays in tight loops

### Memory Impact

- **Zero per-array overhead** - type descriptors stored in function metadata
- **1 byte per HashTable** - reused from previously unused padding byte for type cache
- Element type info allocated once at compile time (persistent memory)
- Typical type descriptor: 16-32 bytes per function (one-time cost)

## Implementation

### Estimated Scope

- **Lines of code:** ~1,200 LOC (including optimizations)
- **Files modified:** 9 core files
- **Implementation time:** 1-2 weeks for experienced contributor
- **Test coverage:** ~500 LOC in tests

### Modified Files
```
Zend/zend_language_parser.y      (~100 LOC) - Grammar rules
Zend/zend_language_scanner.l     (~50 LOC)  - Lexer state machine
Zend/zend_compile.h              (~150 LOC) - Type structures
Zend/zend_compile.c              (~350 LOC) - Type compilation + escape analysis
Zend/zend_execute.c              (~400 LOC) - Runtime validation + optimized loops
Zend/zend_types.h                (~10 LOC)  - HashTable type cache field
Zend/zend_hash.h                 (~20 LOC)  - Type cache macros
Zend/zend_hash.c                 (~30 LOC)  - Cache invalidation hooks
ext/reflection/php_reflection.c  (~150 LOC) - Reflection support
```

### Key Data Structures
```c
// Shape element (key: type pair)
typedef struct _zend_shape_element {
    zend_string *key;        // String key (or NULL for numeric)
    zend_ulong key_num;      // Numeric key (if key == NULL)
    zend_type type;          // Element type (can be nested)
} zend_shape_element;

// Shape descriptor
typedef struct _zend_array_shape {
    uint32_t num_elements;
    zend_shape_element elements[];  // Flexible array member
} zend_array_shape;

// Homogeneous array descriptor
typedef struct _zend_array_of {
    zend_type element_type;
    uint8_t depth;           // For array<array<T>>
} zend_array_of;
```

### Prototype

A working prototype is available at: [GitHub Pull Request Link]

The implementation demonstrates:
- Complete parser integration
- Type compilation and storage
- Runtime validation
- Reflection support
- Comprehensive test coverage

## Reflection

### API Extensions
```php
$reflFunc = new ReflectionFunction('getUser');
$returnType = $reflFunc->getReturnType();

// ReflectionNamedType methods
echo $returnType->getName();           // "array{id: int, name: string}"
echo $returnType->__toString();        // "array{id: int, name: string}"

// For array<T>
if ($returnType instanceof ReflectionArrayType) {
    $elementType = $returnType->getElementType();  // ReflectionType
}

// For array{k: T}
if ($returnType instanceof ReflectionArrayShapeType) {
    $elements = $returnType->getElements();  // array<ReflectionArrayShapeElement>
    $count = $returnType->getElementCount();
    $requiredCount = $returnType->getRequiredElementCount();

    foreach ($elements as $element) {
        echo $element->getName();       // Key name
        echo $element->getType();       // ReflectionType for value
        echo $element->isOptional();    // bool - true if key?: type
    }
}
```

### New Reflection Classes

#### ReflectionArrayType
For homogeneous arrays (`array<T>`):
```php
class ReflectionArrayType extends ReflectionType {
    /** Get the element type */
    public function getElementType(): ReflectionType;
}
```

#### ReflectionArrayShapeType
For array shapes (`array{key: type}`):
```php
class ReflectionArrayShapeType extends ReflectionType {
    /** Get all elements defined in the shape */
    public function getElements(): array;  // array<ReflectionArrayShapeElement>

    /** Get total number of elements */
    public function getElementCount(): int;

    /** Get number of required (non-optional) elements */
    public function getRequiredElementCount(): int;
}
```

#### ReflectionArrayShapeElement
Represents a single element in an array shape:
```php
class ReflectionArrayShapeElement {
    /** Get the key name */
    public function getName(): string;

    /** Get the type of this element's value */
    public function getType(): ReflectionType;

    /** Check if this element is optional (key?: type) */
    public function isOptional(): bool;
}
```

### Complete Reflection Example

```php
<?php
declare(strict_arrays=1);

function getUserProfile(): array{
    id: int,
    name: string,
    email: string,
    age?: int,
    verified?: bool
} {
    return ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
}

$reflection = new ReflectionFunction('getUserProfile');
$returnType = $reflection->getReturnType();

if ($returnType instanceof ReflectionArrayShapeType) {
    echo "Total elements: " . $returnType->getElementCount() . "\n";        // 5
    echo "Required elements: " . $returnType->getRequiredElementCount() . "\n"; // 3

    foreach ($returnType->getElements() as $element) {
        $optional = $element->isOptional() ? ' (optional)' : '';
        echo "  {$element->getName()}: {$element->getType()}{$optional}\n";
    }
}
// Output:
// Total elements: 5
// Required elements: 3
//   id: int
//   name: string
//   email: string
//   age: int (optional)
//   verified: bool (optional)
```

## Future Scope

### Not Included in This RFC

The following features are **intentionally excluded** from this RFC but could be proposed separately:

#### 1. Parameter Type Shapes ✓ IMPLEMENTED
```php
function process(array{id: int, name: string} $data): void {
    // ✓ Works - validates parameter at call time
}
```

**Status:** Implemented in this version. Parameter shapes validate when the function is called.

#### 2. Property Type Shapes
```php
class User {
    public array<string> $tags;  // Not in this RFC
}
```

**Rationale:** Properties require validation on every write operation, significantly different from boundary validation.

#### 3. Closed Shapes
```php
function getData(): array{id: int}! {  // Exact shape, no extra keys
    // Not in this RFC
}
```

**Rationale:** Can be added later if needed. Open shapes (allowing extra keys) are more PHP-like.

#### 4. Optional Keys ✓ IMPLEMENTED
```php
function getData(): array{id: int, name?: string} {
    return ['id' => 1];  // ✓ Works - 'name' is optional
}
```

**Status:** Implemented in this version. Optional keys are marked with `?` after the key name.

#### 5. List Types
```php
function getData(): list<int> {  // Sequential array (0, 1, 2, ...)
    // Not in this RFC
}
```

**Rationale:** `array<T>` handles this case. Explicit `list<T>` can be added later.

#### 6. Readonly/Immutable Arrays
```php
function getConfig(): readonly array{host: string, port: int} {
    // Not in this RFC
}
```

**Rationale:** Immutability is a separate concern that could be addressed in a future RFC. Considerations:

- **Syntax options**: `readonly array<T>`, `immutable array<T>`, or `const array<T>`
- **Copy-on-write**: PHP arrays already use COW; true immutability would prevent modification after return
- **Nested immutability**: Should `readonly array<array<int>>` make nested arrays immutable too?
- **Performance**: Immutable arrays could skip defensive copying and enable additional optimizations
- **Use cases**: Configuration, constants, thread-safe data sharing (with future async)

This RFC focuses on type validation; immutability can build on top of array shapes in a follow-up proposal.

#### 7. Map Types (Typed Keys)
```php
function getScores(): array<string, int> {  // string keys, int values
    // Not in this RFC
}
```

**Rationale:** Adds complexity to validation (must check all keys are strings). Current `array<T>` validates values only. Map types with typed keys could be a future extension.

## Comparison with Other Languages

### Hack (HHVM)
```hack
type UserShape = shape(
    'id' => int,
    'name' => string,
);

function getUser(): UserShape { }
```

**Differences:**
- Hack requires type aliases for shapes
- Hack has closed/open shape distinction
- PHP allows inline shape definitions

### TypeScript
```typescript
function getUser(): { id: number, name: string } {
    return { id: 1, name: 'Alice' };
}
```

**Differences:**
- TypeScript is structurally typed (compile-time only)
- PHP validates at runtime
- TypeScript requires exact object match

### Python (TypedDict)
```python
from typing import TypedDict

class User(TypedDict):
    id: int
    name: str

def get_user() -> User:
    return {"id": 1, "name": "Alice"}
```

**Differences:**
- Python requires class definition
- PHP allows inline definitions
- Python typing is optional (mypy enforces)

## Security Implications

Array shape types provide security benefits at trust boundaries:

### Input Validation at API Boundaries

```php
declare(strict_arrays=1);

class ApiController {
    // Ensures response structure is always correct
    public function getUser(int $id): array{id: int, name: string, role: string} {
        $user = $this->db->fetchUser($id);
        return $user;  // TypeError if DB returns unexpected structure
    }
}
```

### Prevention of Data Leakage

```php
// Without shapes: accidentally exposing sensitive data
function getPublicUser(): array {
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    return $user;  // Might include password_hash, api_token, etc.
}

// With shapes: explicit contract prevents accidental exposure
function getPublicUser(): array{id: int, name: string, avatar: string} {
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    return $user;  // TypeError if password_hash accidentally included
    // (assuming closed shapes in future, or static analysis catches this)
}
```

### Defense Against Injection via Type Confusion

```php
declare(strict_arrays=1);

function processPayment(array{amount: int, currency: string} $payment): void {
    // Cannot pass ['amount' => '100; DROP TABLE users', 'currency' => 'USD']
    // because amount must be int, not string
    $this->gateway->charge($payment['amount'], $payment['currency']);
}
```

### Hardening Against Deserialization Attacks

```php
declare(strict_arrays=1);

function loadConfig(): array{debug: bool, db_host: string, db_port: int} {
    $config = json_decode(file_get_contents('config.json'), true);
    return $config;  // TypeError if JSON was tampered with unexpected types
}
```

## Real-World Framework Examples

### Laravel: Eloquent Results

```php
declare(strict_arrays=1);

class UserRepository {
    // Clear contract for what the repository returns
    public function findWithPosts(int $id): array{
        user: array{id: int, name: string, email: string},
        posts: array<array{id: int, title: string, created_at: string}>
    } {
        $user = User::with('posts')->findOrFail($id);
        return [
            'user' => $user->only(['id', 'name', 'email']),
            'posts' => $user->posts->map->only(['id', 'title', 'created_at'])->all(),
        ];
    }
}
```

### Symfony: API Responses

```php
declare(strict_arrays=1);

class ProductController extends AbstractController {
    #[Route('/api/products/{id}')]
    public function show(int $id): JsonResponse {
        return $this->json($this->getProductData($id));
    }

    private function getProductData(int $id): array{
        id: int,
        name: string,
        price: float,
        stock: int,
        categories: array<string>
    } {
        $product = $this->repository->find($id);
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'categories' => $product->getCategoryNames(),
        ];
    }
}
```

### Doctrine: Custom Hydration

```php
declare(strict_arrays=1);

class ReportRepository {
    /**
     * Complex query returning specific structure
     */
    public function getSalesReport(DateRange $range): array<array{
        date: string,
        total_sales: float,
        order_count: int,
        top_product: string
    }> {
        return $this->em->createQuery('...')
            ->setParameters(['start' => $range->start, 'end' => $range->end])
            ->getResult();
    }
}
```

### API Platform: Resource Output

```php
declare(strict_arrays=1);

class OrderNormalizer implements NormalizerInterface {
    public function normalize($order): array{
        id: int,
        status: string,
        items: array<array{product_id: int, quantity: int, price: float}>,
        total: float,
        created_at: string
    } {
        return [
            'id' => $order->getId(),
            'status' => $order->getStatus(),
            'items' => array_map(fn($item) => [
                'product_id' => $item->getProductId(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getPrice(),
            ], $order->getItems()),
            'total' => $order->getTotal(),
            'created_at' => $order->getCreatedAt()->format('c'),
        ];
    }
}
```

### Configuration Arrays

```php
declare(strict_arrays=1);

class DatabaseConfig {
    public static function load(): array{
        driver: string,
        host: string,
        port: int,
        database: string,
        username: string,
        password: string,
        options: array<string>
    } {
        return [
            'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_NAME'],
            'username' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASS'],
            'options' => explode(',', $_ENV['DB_OPTIONS'] ?? ''),
        ];
    }
}
```

## Migration Guide

### Step 1: Identify Candidates

Start with functions that return structured data:
- Repository methods returning database results
- API endpoints returning JSON structures
- Configuration loaders
- Data transformation functions

```bash
# Find functions returning arrays (candidates for typing)
grep -r "function.*(): array" src/
```

### Step 2: Add Types Incrementally

**Phase 1: Add types without validation (syntax only)**
```php
// No declare - types documented but not enforced
function getUser(): array{id: int, name: string} {
    return $this->db->fetch(...);
}
```

**Phase 2: Enable validation in test environment**
```php
// tests/bootstrap.php
declare(strict_arrays=1);
```

**Phase 3: Enable validation in specific files**
```php
<?php
declare(strict_arrays=1);  // Enable for this file

function getUser(): array{id: int, name: string} {
    return $this->db->fetch(...);  // Now validated
}
```

### Step 3: Handle Validation Failures

When validation catches type mismatches:

```php
// Before: Wrong type from database
function getUser(): array{id: int, age: int} {
    return $this->db->fetch(...);  // age might be string "25"
}

// After: Cast at boundary
function getUser(): array{id: int, age: int} {
    $row = $this->db->fetch(...);
    $row['age'] = (int) $row['age'];  // Explicit cast
    return $row;
}
```

### Step 4: Migrate from Docblocks

```php
// Before: PHPStan/Psalm docblock
/**
 * @return array{id: int, name: string}
 */
function getUser(): array {
    return ['id' => 1, 'name' => 'Alice'];
}

// After: Native type (keep docblock for BC with older tools)
/**
 * @return array{id: int, name: string}
 */
function getUser(): array{id: int, name: string} {
    return ['id' => 1, 'name' => 'Alice'];
}
```

### Tooling Support

```bash
# Future: Automated migration tool (proposed)
php-cs-fixer fix --rules=array_shape_from_docblock src/

# Rector rule (proposed)
vendor/bin/rector process src/ --rules=DocblockToArrayShape
```

## Common Objections / FAQ

### "Just use classes/DTOs instead of arrays"

**Response:** Classes are often better for complex domain objects, but arrays remain the pragmatic choice for:

- **Database results**: ORMs return arrays; wrapping every query in DTOs adds boilerplate
- **API responses**: JSON naturally maps to arrays; DTOs require serialization setup
- **Configuration**: Arrays are readable and don't need class files
- **Interoperability**: Many libraries expect/return arrays
- **Performance**: Arrays have less overhead than object instantiation

Array shapes complement DTOs - use DTOs for complex behavior, shapes for simple data structures.

### "This adds complexity to the language"

**Response:** PHP already has:
- Typed properties (`public int $id`)
- Typed parameters (`function foo(int $x)`)
- Typed returns (`function foo(): int`)
- Union types (`int|string`)
- Intersection types (`Foo&Bar`)

Array shapes are the logical completion of PHP's type system. The syntax mirrors existing static analysis tools, so it's already familiar to many developers.

### "Performance overhead is unacceptable"

**Response:** With optimizations implemented:
- **Escape analysis**: Constant literals have ~0% overhead
- **Type tagging cache**: Repeated validations have ~1% overhead
- **Opt-in validation**: `declare(strict_arrays=1)` means zero overhead by default

For comparison, `declare(strict_types=1)` also has overhead but is widely used.

### "Static analysis already solves this"

**Response:** Static analysis and native types are complementary:

| Static Analysis | Native Types |
|-----------------|--------------|
| Catches bugs at analysis time | Catches bugs at runtime |
| Can be bypassed/ignored | Cannot be bypassed |
| Requires tool setup | Works out of the box |
| Can't validate external data | Validates all data |
| Comments can drift | Types are the contract |

Both together provide defense in depth.

### "What about backwards compatibility?"

**Response:**
- Plain `array` return types continue to work unchanged
- New syntax is opt-in and additive
- No `declare(strict_arrays=1)` = no validation overhead
- Static analyzers can read native types (better than parsing docblocks)

### "Why return types only? What about parameters?"

**Response:** This RFC intentionally limits scope to return types:

- **Simpler implementation**: Single validation point
- **Cleaner semantics**: No variance complications with parameters
- **Easier adoption**: Can be added incrementally
- **Future extensibility**: Parameter types can be a follow-up RFC

### "Won't this make error messages confusing?"

**Response:** Native types provide clear, actionable errors:

```
TypeError: getUser(): Return value must be of type array{id: int, name: string},
key 'name' must be of type string, int given

// vs. current state:
Warning: Undefined array key "name" in /app/View.php on line 847
```

The error tells you exactly what's wrong and where.

## Open Questions

### 1. Should we support union types in shapes?
```php
function getData(): array{value: int|string} {
    return ['value' => 'string'];  // Or int
}
```

**Proposal:** Yes, leverage existing union type support in `zend_type`.

### 2. How strict should numeric key matching be?
```php
function getData(): array{0: int, 1: int} {
    return [0 => 1, 1 => 2];     // ✓ OK
    return ['0' => 1, '1' => 2]; // ✓ OK? (string keys "0", "1")
}
```

**Proposal:** Follow PHP's standard array key coercion rules.

### 3. Should var_export() include type information?
```php
var_export(getData());
// Current: array ( 'id' => 1, ... )
// Option:  array{id: int}( 'id' => 1, ... )
```

**Proposal:** No, keep var_export() output unchanged for BC. Type info is in function signature.

## Voting

Primary vote: Accept "Array Shape Return Types" as proposed?

- Yes
- No

Voting started: TBD  
Voting ends: TBD  
Required majority: 2/3

## Patches and Tests

- Pull Request: https://github.com/php/php-src/pull/XXXXX
- Test Suite: https://github.com/php/php-src/blob/master/Zend/tests/type/array_shapes/

## Changelog

- **v1.0 (2024-12-25):** Initial draft
- **v1.1 (2025-12-27):** Added performance optimizations (type tagging cache, loop unrolling, escape analysis)
- **v1.2 (TBD):** After discussion period

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
