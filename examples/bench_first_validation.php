<?php
declare(strict_arrays=1);

/**
 * Benchmark: First-time validation performance (no caching)
 *
 * Creates fresh arrays each iteration to ensure no caching benefits.
 */

const ITERATIONS = 20000;

function validateInts(array $arr): array<int> {
    return $arr;
}

function validatePlain(array $arr): array {
    return $arr;
}

echo "=======================================================\n";
echo "  BENCHMARK: First-time validation (fresh arrays)\n";
echo "=======================================================\n";
echo "Iterations: " . number_format(ITERATIONS) . "\n\n";

// Small arrays
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    validateInts([1, 2, 3, 4, 5]);
}
$small_typed = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    validatePlain([1, 2, 3, 4, 5]);
}
$small_plain = (hrtime(true) - $start) / 1e6;

echo "Small array (5 elements):\n";
echo "  Plain:     " . number_format($small_plain, 2) . " ms\n";
echo "  array<int>: " . number_format($small_typed, 2) . " ms\n";
echo "  Overhead:  " . number_format((($small_typed - $small_plain) / $small_plain) * 100, 1) . "%\n\n";

// Medium arrays (50 elements)
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    validateInts(range(1, 50));
}
$medium_typed = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    validatePlain(range(1, 50));
}
$medium_plain = (hrtime(true) - $start) / 1e6;

echo "Medium array (50 elements):\n";
echo "  Plain:     " . number_format($medium_plain, 2) . " ms\n";
echo "  array<int>: " . number_format($medium_typed, 2) . " ms\n";
echo "  Overhead:  " . number_format((($medium_typed - $medium_plain) / $medium_plain) * 100, 1) . "%\n\n";

// Large arrays (100 elements)
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    validateInts(range(1, 100));
}
$large_typed = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    validatePlain(range(1, 100));
}
$large_plain = (hrtime(true) - $start) / 1e6;

echo "Large array (100 elements):\n";
echo "  Plain:     " . number_format($large_plain, 2) . " ms\n";
echo "  array<int>: " . number_format($large_typed, 2) . " ms\n";
echo "  Overhead:  " . number_format((($large_typed - $large_plain) / $large_plain) * 100, 1) . "%\n";

echo "=======================================================\n";
