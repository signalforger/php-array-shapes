--TEST--
array{...} - basic shape validation
--FILE--
<?php

// Test 1: Simple shape with two keys
function getUser(): array{id: int, name: string} {
    return ['id' => 1, 'name' => 'Alice'];
}

$result = getUser();
var_dump($result);

// Test 2: Shape with different types
function getProduct(): array{id: int, name: string, price: float, active: bool} {
    return [
        'id' => 42,
        'name' => 'Widget',
        'price' => 9.99,
        'active' => true
    ];
}

$result = getProduct();
var_dump($result);

// Test 3: Shape with extra keys (should be allowed)
function getUserWithExtra(): array{id: int, name: string} {
    return [
        'id' => 1,
        'name' => 'Bob',
        'email' => 'bob@example.com', // Extra key - should be OK
        'age' => 30 // Extra key - should be OK
    ];
}

$result = getUserWithExtra();
var_dump($result);

echo "All tests passed!\n";

--EXPECT--
array(2) {
  ["id"]=>
  int(1)
  ["name"]=>
  string(5) "Alice"
}
array(4) {
  ["id"]=>
  int(42)
  ["name"]=>
  string(6) "Widget"
  ["price"]=>
  float(9.99)
  ["active"]=>
  bool(true)
}
array(4) {
  ["id"]=>
  int(1)
  ["name"]=>
  string(3) "Bob"
  ["email"]=>
  string(15) "bob@example.com"
  ["age"]=>
  int(30)
}
All tests passed!
