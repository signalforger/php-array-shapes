--TEST--
array{...} - TypeError when key has wrong type
--FILE--
<?php

function getUser(): array{id: int, name: string} {
    return ['id' => 'not-an-int', 'name' => 'Alice']; // 'id' should be int
}

try {
    $result = getUser();
    echo "Should have thrown TypeError\n";
} catch (TypeError $e) {
    echo "Caught TypeError: " . $e->getMessage() . "\n";
}

--EXPECTF--
Caught TypeError: getUser(): Return value key 'id' must be of type int, string given
