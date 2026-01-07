# Typed Arrays & Array Shapes for PHP

Ever written `function getUsers(): array` and wondered what's actually in that array? Yeah, us too.

This is a working proof-of-concept that adds **typed arrays** and **array shapes** to PHP. No more guessing. No more runtime surprises. Just tell PHP what's in your arrays and let it validate for you.

## Try It

```bash
docker run -it --rm ghcr.io/signalforger/php-array-shapes:latest php -a
```

Then:

```php
// Typed arrays - for collections
function getIds(): array<int> {
    return [1, 2, 3];
}

// Array shapes - for structured data
function getUser(): array{id: int, name: string, email: string} {
    return ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
}

// They compose nicely
function getUsers(): array<array{id: int, name: string}> {
    return [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob']
    ];
}
```

If you return the wrong type, you get a clear error:

```
TypeError: getIds(): Return value must be of type array<int>,
           array element at index 1 is string
```

## Why?

Because `json_decode()` returns arrays. Because PDO returns arrays. Because config files are arrays. Because webhooks are arrays.

Every PHP app is full of arrays, and right now the type system can't help you with any of them.

Static analysis tools help, but they can't validate data that comes from outside your code - API responses, database queries, user input. That's where runtime validation matters.

## What's Implemented

Everything you'd expect:

- `array<int>`, `array<User>`, `array<string, int>` - typed collections
- `array{id: int, name: string}` - structured shapes
- `array{email?: string}` - optional keys
- `array{id: int}!` - closed shapes (no extra keys allowed)
- `shape UserRecord = array{...}` - reusable type aliases
- `shape Admin extends User = array{...}` - shape inheritance
- Property types, interface/trait support, variance checking
- Full reflection API

47 tests. All passing.

## Documentation

- **[proposal.md](proposal.md)** - The RFC proposal (focused on typed arrays)
- **[examples.md](examples.md)** - Usage examples and patterns
- **[implementation.md](implementation.md)** - How it works under the hood (C implementation details)

## Quick Reference

```php
// Typed arrays
array<int>                    // list of integers
array<User>                   // list of objects
array<string, float>          // string keys, float values

// Array shapes
array{id: int, name: string}  // required keys
array{id: int, bio?: string}  // optional key
array{data: ?string}          // nullable value
array{id: int}!               // closed (no extra keys)

// Type aliases
shape User = array{id: int, name: string};
shape Admin extends User = array{role: string};
```

## The Idea

**Arrays are data. Objects are behavior.**

Use shapes at the boundaries of your app (API responses, database rows, config files) where data enters as arrays. Convert to proper objects inside your domain where you need methods and business logic.

Shapes don't replace DTOs - they complement them. Validate the structure when data comes in, then work with rich objects internally.

## Source

- **Docker:** `ghcr.io/signalforger/php-array-shapes:latest`
- **Code:** [github.com/signalforger/php-src](https://github.com/signalforger/php-src/tree/feature/array-shapes) (feature/array-shapes branch)
- **Patch:** `array-shapes.patch` in this repo

## Build Locally

```bash
git clone --recursive https://github.com/signalforger/php-array-shapes.git
cd php-array-shapes
docker build --target cli -t php-array-shapes:latest .
```

---

Built by [Signalforger](https://github.com/signalforger). Feedback welcome.
