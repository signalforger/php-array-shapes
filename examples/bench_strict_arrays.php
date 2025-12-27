<?php
declare(strict_arrays=1);

/**
 * Benchmark: array<T> with strict_arrays=1 (validation ENABLED)
 *
 * Run with: ./php/php examples/bench_strict_arrays.php
 */

const ITERATIONS = 100000;

// Test data
$smallArray = [1, 2, 3, 4, 5];
$mediumArray = range(1, 50);
$largeArray = range(1, 100);

// Functions with array<int> return type
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

// Functions with plain array return type (no element validation)
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
echo "  BENCHMARK: strict_arrays=1 (validation ENABLED)\n";
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
echo "Per-element validation cost:\n";
echo "  Small:  " . number_format(($typedSmall - $plainSmall) / ITERATIONS / 5 * 1000, 4) . " us/element\n";
echo "  Medium: " . number_format(($typedMedium - $plainMedium) / ITERATIONS / 50 * 1000, 4) . " us/element\n";
echo "  Large:  " . number_format(($typedLarge - $plainLarge) / ITERATIONS / 100 * 1000, 4) . " us/element\n";
echo "=======================================================\n";
