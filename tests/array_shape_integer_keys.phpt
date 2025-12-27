--TEST--
Integer keys in array shapes
--FILE--
<?php

// Test 1: Shape with integer keys
function getTuple(): array{0: string, 1: int, 2: bool} {
    return ['hello', 42, true];
}

$result = getTuple();
var_dump($result);

// Test 2: Mixed string and integer keys
function getMixed(): array{name: string, 0: int, 1: int} {
    return [
        'name' => 'values',
        0 => 100,
        1 => 200
    ];
}

$result = getMixed();
var_dump($result);

// Test 3: Sparse integer keys
function getSparse(): array{0: string, 5: int, 10: bool} {
    return [
        0 => 'first',
        5 => 50,
        10 => false
    ];
}

$result = getSparse();
var_dump($result);

echo "All tests passed!\n";

--EXPECT--
array(3) {
  [0]=>
  string(5) "hello"
  [1]=>
  int(42)
  [2]=>
  bool(true)
}
array(3) {
  ["name"]=>
  string(6) "values"
  [0]=>
  int(100)
  [1]=>
  int(200)
}
array(3) {
  [0]=>
  string(5) "first"
  [5]=>
  int(50)
  [10]=>
  bool(false)
}
All tests passed!
