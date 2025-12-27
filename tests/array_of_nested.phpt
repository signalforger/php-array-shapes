--TEST--
array<array<T>> - nested array type validation
--FILE--
<?php

// Test 1: array<array<int>> - 2D integer array
function getMatrix(): array<array<int>> {
    return [
        [1, 2, 3],
        [4, 5, 6],
        [7, 8, 9]
    ];
}

$result = getMatrix();
var_dump($result);

// Test 2: array<array<string>> - 2D string array
function getWords(): array<array<string>> {
    return [
        ['hello', 'world'],
        ['foo', 'bar', 'baz']
    ];
}

$result = getWords();
var_dump($result);

// Test 3: array<array<array<int>>> - 3D integer array
function getCube(): array<array<array<int>>> {
    return [
        [[1, 2], [3, 4]],
        [[5, 6], [7, 8]]
    ];
}

$result = getCube();
var_dump($result);

echo "All tests passed!\n";

--EXPECT--
array(3) {
  [0]=>
  array(3) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    [2]=>
    int(3)
  }
  [1]=>
  array(3) {
    [0]=>
    int(4)
    [1]=>
    int(5)
    [2]=>
    int(6)
  }
  [2]=>
  array(3) {
    [0]=>
    int(7)
    [1]=>
    int(8)
    [2]=>
    int(9)
  }
}
array(2) {
  [0]=>
  array(2) {
    [0]=>
    string(5) "hello"
    [1]=>
    string(5) "world"
  }
  [1]=>
  array(3) {
    [0]=>
    string(3) "foo"
    [1]=>
    string(3) "bar"
    [2]=>
    string(3) "baz"
  }
}
array(2) {
  [0]=>
  array(2) {
    [0]=>
    array(2) {
      [0]=>
      int(1)
      [1]=>
      int(2)
    }
    [1]=>
    array(2) {
      [0]=>
      int(3)
      [1]=>
      int(4)
    }
  }
  [1]=>
  array(2) {
    [0]=>
    array(2) {
      [0]=>
      int(5)
      [1]=>
      int(6)
    }
    [1]=>
    array(2) {
      [0]=>
      int(7)
      [1]=>
      int(8)
    }
  }
}
All tests passed!
