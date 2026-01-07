# Typed Arrays & Array Shapes for PHP

A proof-of-concept implementation that adds typed arrays (`array<int>`) and array shapes (`array{id: int, name: string}`) to PHP's type system with full runtime validation.

## Usage

```bash
docker run -it --rm ghcr.io/signalforger/php-array-shapes:latest php -a
```

```php
// Typed arrays for homogeneous collections
function getIds(): array<int> {
    return [1, 2, 3];
}

// Array shapes for structured data
function getUser(): array{id: int, name: string, email: string} {
    return ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
}
```

Type mismatches produce clear errors:

```
TypeError: getIds(): Return value must be of type array<int>,
           array element at index 1 is string
```

## More Examples

Typed arrays work on function parameters, return types, and class properties:

```php
// Function parameters
function processUsers(array<User> $users): void {
    foreach ($users as $user) {
        // $user is guaranteed to be a User object
    }
}

// Class properties
class UserRepository {
    private array<User> $users = [];
    private array<string, array<int>> $groupedIds = [];

    public function add(User $user): void {
        $this->users[] = $user;
    }
}

// Maps with key and value types
function getScoresByName(): array<string, int> {
    return ['alice' => 95, 'bob' => 87];
}

// Nested typed arrays
function getMatrix(): array<array<float>> {
    return [
        [1.0, 0.0, 0.0],
        [0.0, 1.0, 0.0],
        [0.0, 0.0, 1.0]
    ];
}

// Objects in arrays
function getActiveUsers(): array<User> {
    return $this->repository->findByStatus('active');
}

// Union types in arrays
function getIdentifiers(): array<int|string> {
    return [1, 'abc-123', 2, 'def-456'];
}
```

Array shapes also work everywhere types are accepted:

```php
// As function parameters
function saveUser(array{id: int, name: string, email: string} $user): void {
    $this->db->insert('users', $user);
}

// As class properties
class Config {
    public array{host: string, port: int, ssl?: bool} $database;
    public array{level: string, path: string} $logging;
}

// Combined - array of shapes
function getAllUsers(): array<array{id: int, name: string}> {
    return $this->db->fetchAll('SELECT id, name FROM users');
}
```

## Motivation

PHP functions that return arrays provide no information about what's in those arrays. This is problematic when working with data from external sources like `json_decode()`, PDO queries, or webhook payloads, where the structure is known but not enforced by the type system.

Static analysis tools (PHPStan, Psalm) help with this through docblocks, but they cannot validate data at runtime. This implementation provides native syntax with runtime enforcement.

## Not Generics

This is not a generics implementation. Generics allow you to parameterize classes and functions with type variables (`class Box<T>`). This proposal is narrower: it lets you describe what's inside arrays using inline type annotations.

The syntax `array<int>` might look like generics, but it's specifically array type declarations with runtime validation. You cannot create a `Collection<T>` class or write generic functions. The scope is intentionally limited to arrays, which covers the majority of cases where PHP developers need better type information.

## Features

- `array<T>` and `array<K, V>` for typed collections
- `array{key: type}` for structured shapes
- Optional keys (`key?: type`) and nullable values (`?type`)
- Closed shapes (`array{...}!`) that reject extra keys
- Type aliases (`shape User = array{...}`)
- Shape inheritance (`shape Admin extends User = array{...}`)
- Property types, variance checking, reflection API

All 47 tests pass.

## Documentation

- [proposal.md](proposal.md) - RFC proposal
- [examples.md](examples.md) - Usage examples and patterns
- [implementation.md](implementation.md) - C implementation details

## Syntax Reference

```php
// Typed arrays
array<int>                    // list of integers
array<User>                   // list of objects
array<string, float>          // map with string keys, float values

// Array shapes
array{id: int, name: string}  // required keys
array{id: int, bio?: string}  // optional key
array{data: ?string}          // nullable value
array{id: int}!               // closed shape

// Type aliases
shape User = array{id: int, name: string};
shape Admin extends User = array{role: string};
```

## Design

Array shapes are intended for validating data at application boundaries - where arrays come in from databases, APIs, or configuration files. They complement DTOs rather than replacing them: use shapes to validate incoming data structures, then convert to objects where you need behavior and business logic.

## Source Code

- Docker image: `ghcr.io/signalforger/php-array-shapes:latest`
- PHP fork: [github.com/signalforger/php-src](https://github.com/signalforger/php-src/tree/feature/array-shapes) (feature/array-shapes branch)
- Patch file: `array-shapes.patch`

## Building

```bash
git clone --recursive https://github.com/signalforger/php-array-shapes.git
cd php-array-shapes
docker build --target cli -t php-array-shapes:latest .
```
