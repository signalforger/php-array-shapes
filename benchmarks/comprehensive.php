<?php
/**
 * Comprehensive benchmark for PHP Array Shapes.
 *
 * Tests various array type scenarios and compares performance.
 *
 * Usage: php benchmarks/comprehensive.php
 */

echo "=== PHP Array Shapes Performance Benchmark ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$iterations = 1000000;

// Warm up
for ($i = 0; $i < 1000; $i++) {
    $x = [1, 2, 3];
}

echo "Running $iterations iterations per test...\n\n";

// Test 1: Plain array return type
function getPlainArray(): array {
    return [1, 2, 3, 4, 5];
}

echo "1. Plain 'array' return type:\n";
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    getPlainArray();
}
$plainTime = (hrtime(true) - $start) / 1e6;
printf("   Time: %.2f ms (%.4f us/call)\n\n", $plainTime, $plainTime * 1000 / $iterations);

// Test 2: array<int> return type
function getTypedArray(): array<int> {
    return [1, 2, 3, 4, 5];
}

echo "2. 'array<int>' return type:\n";
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    getTypedArray();
}
$typedTime = (hrtime(true) - $start) / 1e6;
printf("   Time: %.2f ms (%.4f us/call)\n\n", $typedTime, $typedTime * 1000 / $iterations);

// Test 3: No return type
function getNoType() {
    return [1, 2, 3, 4, 5];
}

echo "3. No return type:\n";
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    getNoType();
}
$noTypeTime = (hrtime(true) - $start) / 1e6;
printf("   Time: %.2f ms (%.4f us/call)\n\n", $noTypeTime, $noTypeTime * 1000 / $iterations);

// Test 4: Associative array with plain type
function getAssocPlain(): array {
    return ['id' => 1, 'name' => 'Test', 'active' => true];
}

echo "4. Associative array with 'array' type:\n";
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    getAssocPlain();
}
$assocPlainTime = (hrtime(true) - $start) / 1e6;
printf("   Time: %.2f ms (%.4f us/call)\n\n", $assocPlainTime, $assocPlainTime * 1000 / $iterations);

// Test 5: Nested array<int>
function getNestedTyped(): array<int> {
    return [1, 2, 3];
}

function callNestedTyped(): array<int> {
    return getNestedTyped();
}

echo "5. Nested array<int> calls:\n";
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    callNestedTyped();
}
$nestedTime = (hrtime(true) - $start) / 1e6;
printf("   Time: %.2f ms (%.4f us/call)\n\n", $nestedTime, $nestedTime * 1000 / $iterations);

// Test 6: array<string> return type
function getStringArray(): array<string> {
    return ['a', 'b', 'c', 'd', 'e'];
}

echo "6. 'array<string>' return type:\n";
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    getStringArray();
}
$stringTime = (hrtime(true) - $start) / 1e6;
printf("   Time: %.2f ms (%.4f us/call)\n\n", $stringTime, $stringTime * 1000 / $iterations);

// Summary
echo "=== Summary ===\n";
echo "Baseline (no type):        " . sprintf("%.2f ms", $noTypeTime) . "\n";
echo "Plain 'array':             " . sprintf("%.2f ms", $plainTime) . " (" . sprintf("%+.1f%%", ($plainTime - $noTypeTime) / $noTypeTime * 100) . ")\n";
echo "Typed 'array<int>':        " . sprintf("%.2f ms", $typedTime) . " (" . sprintf("%+.1f%%", ($typedTime - $noTypeTime) / $noTypeTime * 100) . ")\n";
echo "Typed 'array<string>':     " . sprintf("%.2f ms", $stringTime) . " (" . sprintf("%+.1f%%", ($stringTime - $noTypeTime) / $noTypeTime * 100) . ")\n";
echo "Associative 'array':       " . sprintf("%.2f ms", $assocPlainTime) . " (" . sprintf("%+.1f%%", ($assocPlainTime - $noTypeTime) / $noTypeTime * 100) . ")\n";
echo "Nested array<int>:         " . sprintf("%.2f ms", $nestedTime) . " (" . sprintf("%+.1f%%", ($nestedTime - $noTypeTime) / $noTypeTime * 100) . ")\n";
echo "\n";

$diff = $typedTime - $plainTime;
$pct = ($typedTime - $plainTime) / $plainTime * 100;
echo "array<int> vs array overhead: " . sprintf("%.2f ms (%+.2f%%)\n", $diff, $pct);
