--TEST--
Nullable types in array shapes
--FILE--
<?php

// Test 1: Shape with nullable value type
function getUserWithOptionalEmail(): array{id: int, name: string, email: ?string} {
    return [
        'id' => 1,
        'name' => 'Alice',
        'email' => null
    ];
}

$result = getUserWithOptionalEmail();
var_dump($result);

// Test 2: array<?int> - nullable element type
function getMaybeIntegers(): array<?int> {
    return [1, null, 3, null, 5];
}

$result = getMaybeIntegers();
var_dump($result);

// Test 3: ?array<int> - nullable array
function getMaybeArray(): ?array<int> {
    return null;
}

$result = getMaybeArray();
var_dump($result);

// Test 4: ?array<int> with actual array
function getMaybeArrayWithValue(): ?array<int> {
    return [1, 2, 3];
}

$result = getMaybeArrayWithValue();
var_dump($result);

echo "All tests passed!\n";

--EXPECT--
array(3) {
  ["id"]=>
  int(1)
  ["name"]=>
  string(5) "Alice"
  ["email"]=>
  NULL
}
array(5) {
  [0]=>
  int(1)
  [1]=>
  NULL
  [2]=>
  int(3)
  [3]=>
  NULL
  [4]=>
  int(5)
}
NULL
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
All tests passed!
