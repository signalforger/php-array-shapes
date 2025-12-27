--TEST--
Nested array shapes and combinations
--FILE--
<?php

// Test 1: array<array{id: int, name: string}> - Array of shapes
function getUsers(): array<array{id: int, name: string}> {
    return [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 3, 'name' => 'Charlie']
    ];
}

$result = getUsers();
var_dump($result);

// Test 2: Shape containing array<T>
function getResponse(): array{success: bool, data: array<int>, total: int} {
    return [
        'success' => true,
        'data' => [1, 2, 3, 4, 5],
        'total' => 5
    ];
}

$result = getResponse();
var_dump($result);

// Test 3: Shape containing another shape
function getNestedShape(): array{user: array{id: int, name: string}, active: bool} {
    return [
        'user' => ['id' => 1, 'name' => 'Alice'],
        'active' => true
    ];
}

$result = getNestedShape();
var_dump($result);

// Test 4: Complex nested structure
function getComplexResponse(): array{
    success: bool,
    data: array<array{id: int, name: string}>,
    total: int
} {
    return [
        'success' => true,
        'data' => [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob']
        ],
        'total' => 2
    ];
}

$result = getComplexResponse();
var_dump($result);

echo "All tests passed!\n";

--EXPECT--
array(3) {
  [0]=>
  array(2) {
    ["id"]=>
    int(1)
    ["name"]=>
    string(5) "Alice"
  }
  [1]=>
  array(2) {
    ["id"]=>
    int(2)
    ["name"]=>
    string(3) "Bob"
  }
  [2]=>
  array(2) {
    ["id"]=>
    int(3)
    ["name"]=>
    string(7) "Charlie"
  }
}
array(3) {
  ["success"]=>
  bool(true)
  ["data"]=>
  array(5) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    [2]=>
    int(3)
    [3]=>
    int(4)
    [4]=>
    int(5)
  }
  ["total"]=>
  int(5)
}
array(2) {
  ["user"]=>
  array(2) {
    ["id"]=>
    int(1)
    ["name"]=>
    string(5) "Alice"
  }
  ["active"]=>
  bool(true)
}
array(3) {
  ["success"]=>
  bool(true)
  ["data"]=>
  array(2) {
    [0]=>
    array(2) {
      ["id"]=>
      int(1)
      ["name"]=>
      string(5) "Alice"
    }
    [1]=>
    array(2) {
      ["id"]=>
      int(2)
      ["name"]=>
      string(3) "Bob"
    }
  }
  ["total"]=>
  int(2)
}
All tests passed!
