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

// Combined - array of shapes
function getUsers(): array<array{id: int, name: string}> {
    return [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob']
    ];
}
```

Type mismatches produce clear errors:

```
TypeError: getIds(): Return value must be of type array<int>,
           array element at index 1 is string
```

## Motivation

PHP functions that return arrays provide no information about what's in those arrays. This is problematic when working with data from external sources like `json_decode()`, PDO queries, or webhook payloads, where the structure is known but not enforced by the type system.

Static analysis tools (PHPStan, Psalm) help with this through docblocks, but they cannot validate data at runtime. This implementation provides native syntax with runtime enforcement.

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
