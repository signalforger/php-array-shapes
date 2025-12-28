<?php
// NO declare(strict_arrays=1) - validation DISABLED

/**
 * Benchmark: array<T> WITHOUT strict_arrays (validation DISABLED)
 *
 * Run with: ./php/php examples/bench_no_strict_arrays.php
 */

const ITERATIONS = 100000;

// Test data
$smallArray = [1, 2, 3, 4, 5];
$mediumArray = range(1, 50);
$largeArray = range(1, 100);

// Functions with array<int> return type (but NO validation without strict_arrays)
function returnSmallTyped(): array<int> {
    global $smallArray;
    return $smallArray;
}

function returnMediumTyped(): array<int> {
    global $mediumArray;
    return $mediumArray;
}

function returnLargeTyped(): array<int> {
    global $largeArray;
    return $largeArray;
}

// Functions with plain array return type
function returnSmallPlain(): array {
    global $smallArray;
    return $smallArray;
}

function returnMediumPlain(): array {
    global $mediumArray;
    return $mediumArray;
}

function returnLargePlain(): array {
    global $largeArray;
    return $largeArray;
}

// Warm up
for ($i = 0; $i < 1000; $i++) {
    returnSmallTyped();
    returnSmallPlain();
}

echo "=======================================================\n";
echo "  BENCHMARK: NO strict_arrays (validation DISABLED)\n";
echo "=======================================================\n";
echo "Iterations: " . number_format(ITERATIONS) . "\n\n";

// Small array (5 elements)
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnSmallPlain();
}
$plainSmall = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnSmallTyped();
}
$typedSmall = (hrtime(true) - $start) / 1e6;

echo "Small array (5 elements):\n";
echo "  Plain array:     " . number_format($plainSmall, 2) . " ms\n";
echo "  array<int>:      " . number_format($typedSmall, 2) . " ms\n";
echo "  Overhead:        " . number_format((($typedSmall - $plainSmall) / $plainSmall) * 100, 1) . "%\n\n";

// Medium array (50 elements)
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnMediumPlain();
}
$plainMedium = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnMediumTyped();
}
$typedMedium = (hrtime(true) - $start) / 1e6;

echo "Medium array (50 elements):\n";
echo "  Plain array:     " . number_format($plainMedium, 2) . " ms\n";
echo "  array<int>:      " . number_format($typedMedium, 2) . " ms\n";
echo "  Overhead:        " . number_format((($typedMedium - $plainMedium) / $plainMedium) * 100, 1) . "%\n\n";

// Large array (100 elements)
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnLargePlain();
}
$plainLarge = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnLargeTyped();
}
$typedLarge = (hrtime(true) - $start) / 1e6;

echo "Large array (100 elements):\n";
echo "  Plain array:     " . number_format($plainLarge, 2) . " ms\n";
echo "  array<int>:      " . number_format($typedLarge, 2) . " ms\n";
echo "  Overhead:        " . number_format((($typedLarge - $plainLarge) / $plainLarge) * 100, 1) . "%\n\n";

echo "-------------------------------------------------------\n";
echo "Expected: ~0% overhead (validation is disabled)\n";
echo "=======================================================\n";
