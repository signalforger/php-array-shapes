<?php
declare(strict_arrays=1);

/**
 * Benchmark: First-time validation (cache miss case)
 *
 * This benchmark modifies arrays to force revalidation each time.
 */

const ITERATIONS = 50000;

function returnIntsCached(array $arr): array<int> {
    return $arr;
}

function returnIntsUncached(array &$arr): array<int> {
    $arr[0] = $arr[0]; // Modification invalidates cache
    return $arr;
}

function returnPlainCached(array $arr): array {
    return $arr;
}

function returnPlainUncached(array &$arr): array {
    $arr[0] = $arr[0]; // Modification for fair comparison
    return $arr;
}

// Test arrays
$small = range(1, 5);
$medium = range(1, 50);
$large = range(1, 100);

// Warm up
for ($i = 0; $i < 500; $i++) {
    returnIntsCached($large);
    returnIntsUncached($large);
}

echo "=======================================================\n";
echo "  BENCHMARK: Cached vs Uncached validation\n";
echo "=======================================================\n";
echo "Iterations: " . number_format(ITERATIONS) . "\n\n";

// Small array - Cached
$arr = $small;
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnIntsCached($arr);
}
$small_cached = (hrtime(true) - $start) / 1e6;

// Small array - Uncached (forced revalidation)
$arr = $small;
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnIntsUncached($arr);
}
$small_uncached = (hrtime(true) - $start) / 1e6;

// Medium array - Cached
$arr = $medium;
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnIntsCached($arr);
}
$medium_cached = (hrtime(true) - $start) / 1e6;

// Medium array - Uncached
$arr = $medium;
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnIntsUncached($arr);
}
$medium_uncached = (hrtime(true) - $start) / 1e6;

// Large array - Cached
$arr = $large;
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnIntsCached($arr);
}
$large_cached = (hrtime(true) - $start) / 1e6;

// Large array - Uncached
$arr = $large;
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnIntsUncached($arr);
}
$large_uncached = (hrtime(true) - $start) / 1e6;

echo "Small array (5 elements):\n";
echo "  Cached:    " . number_format($small_cached, 2) . " ms\n";
echo "  Uncached:  " . number_format($small_uncached, 2) . " ms\n";
echo "  Speedup:   " . number_format($small_uncached / $small_cached, 1) . "x\n\n";

echo "Medium array (50 elements):\n";
echo "  Cached:    " . number_format($medium_cached, 2) . " ms\n";
echo "  Uncached:  " . number_format($medium_uncached, 2) . " ms\n";
echo "  Speedup:   " . number_format($medium_uncached / $medium_cached, 1) . "x\n\n";

echo "Large array (100 elements):\n";
echo "  Cached:    " . number_format($large_cached, 2) . " ms\n";
echo "  Uncached:  " . number_format($large_uncached, 2) . " ms\n";
echo "  Speedup:   " . number_format($large_uncached / $large_cached, 1) . "x\n";

echo "=======================================================\n";
