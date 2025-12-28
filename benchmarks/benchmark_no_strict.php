<?php
// NO declare(strict_arrays=1) - validation should be skipped

const ITERATIONS = 100000;
const ARRAY_SIZE = 100;

$testArray = range(1, ARRAY_SIZE);

function getArrayRegular(): array {
    global $testArray;
    return $testArray;
}

function getArrayTyped(): array<int> {
    global $testArray;
    return $testArray;
}

// Warm up
for ($i = 0; $i < 1000; $i++) {
    getArrayRegular();
    getArrayTyped();
}

echo "Benchmarking WITHOUT strict_arrays=1 (no validation)\n";
echo "=====================================================\n\n";

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    $result = getArrayRegular();
}
$regularTime = (hrtime(true) - $start) / 1e6;
echo "Regular array: " . number_format($regularTime, 2) . " ms\n";

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    $result = getArrayTyped();
}
$typedTime = (hrtime(true) - $start) / 1e6;
echo "Typed array<int>: " . number_format($typedTime, 2) . " ms\n";

$overhead = (($typedTime - $regularTime) / $regularTime) * 100;
echo "\nOverhead: " . number_format($overhead, 2) . "%\n";
echo "(Should be near 0% since validation is disabled)\n";
