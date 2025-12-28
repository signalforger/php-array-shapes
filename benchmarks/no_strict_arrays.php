<?php
// No declare(strict_arrays=1) - validation should NOT happen

function test(): array<int> {
    return [1, 2, "hello"];  // Should NOT fail without strict_arrays
}

$result = test();
print_r($result);
echo "No validation was done (as expected)\n";
