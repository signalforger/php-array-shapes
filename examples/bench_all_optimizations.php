<?php
declare(strict_arrays=1);

/**
 * Comprehensive benchmark showcasing all optimizations
 */

const ITERATIONS = 50000;

// 1. Constant literal arrays - escape analysis applies
function returnConstLiteral(): array<int> {
    return [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
}

function returnConstLiteralPlain(): array {
    return [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
}

// 2. Cached array - same array returned repeatedly
$cached = range(1, 100);
function returnCachedTyped(): array<int> {
    global $cached;
    return $cached;
}

function returnCachedPlain(): array {
    global $cached;
    return $cached;
}

// 3. Fresh array - first-time validation each call
function returnFreshTyped(): array<int> {
    return range(1, 100);
}

function returnFreshPlain(): array {
    return range(1, 100);
}

// Warm up
for ($i = 0; $i < 500; $i++) {
    returnConstLiteral();
    returnCachedTyped();
    returnFreshTyped();
}

echo "=======================================================\n";
echo "  COMPREHENSIVE BENCHMARK: All Optimizations\n";
echo "=======================================================\n";
echo "Iterations: " . number_format(ITERATIONS) . "\n\n";

// Test 1: Constant literals (escape analysis)
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnConstLiteralPlain();
}
$const_plain = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnConstLiteral();
}
$const_typed = (hrtime(true) - $start) / 1e6;

echo "1. ESCAPE ANALYSIS (constant literals, 10 elements):\n";
echo "   Plain:      " . number_format($const_plain, 2) . " ms\n";
echo "   array<int>: " . number_format($const_typed, 2) . " ms\n";
echo "   Overhead:   " . number_format((($const_typed - $const_plain) / $const_plain) * 100, 1) . "%\n";
echo "   Status:     Compile-time check, ZERO runtime cost\n\n";

// Test 2: Cached arrays (type tagging)
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnCachedPlain();
}
$cached_plain = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnCachedTyped();
}
$cached_typed = (hrtime(true) - $start) / 1e6;

echo "2. TYPE TAGGING CACHE (100 elements, same array):\n";
echo "   Plain:      " . number_format($cached_plain, 2) . " ms\n";
echo "   array<int>: " . number_format($cached_typed, 2) . " ms\n";
echo "   Overhead:   " . number_format((($cached_typed - $cached_plain) / $cached_plain) * 100, 1) . "%\n";
echo "   Status:     Validated once, cache hit thereafter\n\n";

// Test 3: Fresh arrays (loop unrolling + prefetch)
$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnFreshPlain();
}
$fresh_plain = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnFreshTyped();
}
$fresh_typed = (hrtime(true) - $start) / 1e6;

echo "3. FRESH VALIDATION (100 elements, new array each time):\n";
echo "   Plain:      " . number_format($fresh_plain, 2) . " ms\n";
echo "   array<int>: " . number_format($fresh_typed, 2) . " ms\n";
echo "   Overhead:   " . number_format((($fresh_typed - $fresh_plain) / $fresh_plain) * 100, 1) . "%\n";
echo "   Status:     4x loop unrolling + cache prefetch\n\n";

echo "=======================================================\n";
echo "                    SUMMARY\n";
echo "=======================================================\n";
echo "Optimization          | Overhead  | Notes\n";
echo "----------------------|-----------|------------------------\n";
printf("Escape analysis       | %6.1f%%  | Zero cost for literals\n", (($const_typed - $const_plain) / $const_plain) * 100);
printf("Type tagging cache    | %6.1f%%  | Near-zero for cached\n", (($cached_typed - $cached_plain) / $cached_plain) * 100);
printf("Loop unrolling        | %6.1f%%  | Acceptable for fresh\n", (($fresh_typed - $fresh_plain) / $fresh_plain) * 100);
echo "=======================================================\n";
