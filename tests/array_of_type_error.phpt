--TEST--
array<T> - type error when element type doesn't match
--FILE--
<?php

function getIntegers(): array<int> {
    return [1, 2, 'three', 4]; // 'three' is not an int
}

try {
    $result = getIntegers();
    echo "Should have thrown TypeError\n";
} catch (TypeError $e) {
    echo "Caught TypeError: " . $e->getMessage() . "\n";
}

--EXPECTF--
Caught TypeError: getIntegers(): Return value must be of type array<int>, array containing string given
