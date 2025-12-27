--TEST--
array{...} - TypeError when required key is missing
--FILE--
<?php

function getUser(): array{id: int, name: string, email: string} {
    return ['id' => 1, 'name' => 'Alice']; // Missing 'email' key
}

try {
    $result = getUser();
    echo "Should have thrown TypeError\n";
} catch (TypeError $e) {
    echo "Caught TypeError: " . $e->getMessage() . "\n";
}

--EXPECTF--
Caught TypeError: getUser(): Return value missing required key 'email'
