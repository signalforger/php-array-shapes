--TEST--
array<T> - basic type validation
--FILE--
<?php

// Test 1: array<int> with valid data
function getIntegers(): array<int> {
    return [1, 2, 3, 4, 5];
}

$result = getIntegers();
var_dump($result);

// Test 2: array<string> with valid data
function getStrings(): array<string> {
    return ['hello', 'world'];
}

$result = getStrings();
var_dump($result);

// Test 3: array<float> with valid data
function getFloats(): array<float> {
    return [1.5, 2.5, 3.5];
}

$result = getFloats();
var_dump($result);

// Test 4: array<bool> with valid data
function getBools(): array<bool> {
    return [true, false, true];
}

$result = getBools();
var_dump($result);

// Test 5: Empty array should always pass
function getEmpty(): array<int> {
    return [];
}

$result = getEmpty();
var_dump($result);

echo "All tests passed!\n";

--EXPECT--
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
array(2) {
  [0]=>
  string(5) "hello"
  [1]=>
  string(5) "world"
}
array(3) {
  [0]=>
  float(1.5)
  [1]=>
  float(2.5)
  [2]=>
  float(3.5)
}
array(3) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
  [2]=>
  bool(true)
}
array(0) {
}
All tests passed!
