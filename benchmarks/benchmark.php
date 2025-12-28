<?php
declare(strict_arrays=1);

/**
 * Benchmark for array<T> type validation
 *
 * Compares performance of:
 * 1. Regular array return type (no element validation)
 * 2. array<int> return type (with element validation in strict mode)
 */

// Number of iterations
const ITERATIONS = 100000;
const ARRAY_SIZE = 100;

// Generate test array
$testArray = range(1, ARRAY_SIZE);

// Function with regular array return type (no validation)
function getArrayRegular(): array {
    global $testArray;
    return $testArray;
}

// Function with array<int> return type (with validation when strict_arrays=1)
function getArrayTyped(): array<int> {
    global $testArray;
    return $testArray;
}

// Warm up
for ($i = 0; $i < 1000; $i++) {
    getArrayRegular();
    getArrayTyped();
}

// Benchmark regular array
echo "Benchmarking array return type (no element validation)...\n";
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    $result = getArrayRegular();
}
$regularTime = (hrtime(true) - $start) / 1e6; // Convert to milliseconds
echo "Regular array: " . number_format($regularTime, 2) . " ms\n";

// Benchmark typed array
echo "\nBenchmarking array<int> return type (with element validation)...\n";
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    $result = getArrayTyped();
}
$typedTime = (hrtime(true) - $start) / 1e6; // Convert to milliseconds
echo "Typed array<int>: " . number_format($typedTime, 2) . " ms\n";

// Calculate overhead
$overhead = (($typedTime - $regularTime) / $regularTime) * 100;
echo "\n=== Results ===\n";
echo "Iterations: " . number_format(ITERATIONS) . "\n";
echo "Array size: " . ARRAY_SIZE . " elements\n";
echo "Regular array time: " . number_format($regularTime, 2) . " ms\n";
echo "Typed array<int> time: " . number_format($typedTime, 2) . " ms\n";
echo "Validation overhead: " . number_format($overhead, 2) . "%\n";
echo "Per-call overhead: " . number_format(($typedTime - $regularTime) / ITERATIONS * 1000, 4) . " microseconds\n";
echo "\nNote: strict_arrays=1 is enabled, so validation is active.\n";
echo "Without declare(strict_arrays=1), array<int> would have no overhead.\n";
