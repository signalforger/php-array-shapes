<?php
/**
 * Basic benchmark comparing array<T> vs plain array return types.
 *
 * Usage: php benchmarks/basic.php
 */

$rounds = 5;
$iterations = 2000000;

function getPlain(): array { return [1, 2, 3, 4, 5]; }
function getTyped(): array<int> { return [1, 2, 3, 4, 5]; }

// Warmup
for ($i = 0; $i < 10000; $i++) { getPlain(); getTyped(); }

$plainTimes = [];
$typedTimes = [];

for ($r = 0; $r < $rounds; $r++) {
    // Test plain
    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) { getPlain(); }
    $plainTimes[] = (hrtime(true) - $start) / 1e6;

    // Test typed
    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) { getTyped(); }
    $typedTimes[] = (hrtime(true) - $start) / 1e6;
}

$avgPlain = array_sum($plainTimes) / count($plainTimes);
$avgTyped = array_sum($typedTimes) / count($typedTimes);

echo "=== Benchmark Results ($iterations iterations x $rounds rounds) ===\n\n";
echo "Plain 'array':      " . sprintf("%.2f ms", $avgPlain) . " (avg)\n";
echo "Typed 'array<int>': " . sprintf("%.2f ms", $avgTyped) . " (avg)\n\n";

$overhead = $avgTyped - $avgPlain;
$pct = ($overhead / $avgPlain) * 100;
echo "Overhead: " . sprintf("%.2f ms (%+.2f%%)\n", $overhead, $pct);
echo "Per-call: " . sprintf("%.4f ns\n", $overhead * 1e6 / $iterations);
