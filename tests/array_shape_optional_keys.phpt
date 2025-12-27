--TEST--
Optional keys in array shapes (key?: type syntax)
--FILE--
<?php

// Test 1: Optional key present
function getUserOptional(): array{id: int, name: string, email?: string} {
    return [
        'id' => 1,
        'name' => 'Alice',
        'email' => 'alice@example.com'
    ];
}

$result = getUserOptional();
var_dump($result);

// Test 2: Optional key missing (should not error)
function getUserWithoutEmail(): array{id: int, name: string, email?: string} {
    return [
        'id' => 2,
        'name' => 'Bob'
        // 'email' is optional and not provided
    ];
}

$result = getUserWithoutEmail();
var_dump($result);

// Test 3: Multiple optional keys
function getConfig(): array{host: string, port?: int, ssl?: bool} {
    return ['host' => 'localhost'];
}

$result = getConfig();
var_dump($result);

// Test 4: All optional keys provided
function getConfigFull(): array{host: string, port?: int, ssl?: bool} {
    return [
        'host' => 'localhost',
        'port' => 3306,
        'ssl' => true
    ];
}

$result = getConfigFull();
var_dump($result);

echo "All tests passed!\n";

--EXPECT--
array(3) {
  ["id"]=>
  int(1)
  ["name"]=>
  string(5) "Alice"
  ["email"]=>
  string(17) "alice@example.com"
}
array(2) {
  ["id"]=>
  int(2)
  ["name"]=>
  string(3) "Bob"
}
array(1) {
  ["host"]=>
  string(9) "localhost"
}
array(3) {
  ["host"]=>
  string(9) "localhost"
  ["port"]=>
  int(3306)
  ["ssl"]=>
  bool(true)
}
All tests passed!
