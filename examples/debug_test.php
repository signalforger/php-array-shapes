<?php
declare(strict_arrays=1);

function test(): array<int> {
    return [1, 2, "hello"];  // Should fail - string in array<int>
}

$result = test();
print_r($result);
