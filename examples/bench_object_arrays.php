<?php
declare(strict_arrays=1);

/**
 * Benchmark: array<ClassName> with object validation
 *
 * Run with: ./php/php examples/bench_object_arrays.php
 */

const ITERATIONS = 50000;

class User {
    public function __construct(
        public int $id,
        public string $name
    ) {}
}

// Create test data
$users = [];
for ($i = 0; $i < 20; $i++) {
    $users[] = new User($i, "User $i");
}

function returnUsersTyped(): array<User> {
    global $users;
    return $users;
}

function returnUsersPlain(): array {
    global $users;
    return $users;
}

// Warm up
for ($i = 0; $i < 500; $i++) {
    returnUsersTyped();
    returnUsersPlain();
}

echo "=======================================================\n";
echo "  BENCHMARK: array<User> object validation\n";
echo "=======================================================\n";
echo "Iterations: " . number_format(ITERATIONS) . "\n";
echo "Array size: 20 User objects\n\n";

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnUsersPlain();
}
$plain = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < ITERATIONS; $i++) {
    returnUsersTyped();
}
$typed = (hrtime(true) - $start) / 1e6;

echo "Results:\n";
echo "  Plain array:     " . number_format($plain, 2) . " ms\n";
echo "  array<User>:     " . number_format($typed, 2) . " ms\n";
echo "  Overhead:        " . number_format((($typed - $plain) / $plain) * 100, 1) . "%\n";
echo "  Per-call:        " . number_format(($typed - $plain) / ITERATIONS * 1000, 4) . " us\n";
echo "  Per-object:      " . number_format(($typed - $plain) / ITERATIONS / 20 * 1000, 4) . " us\n";
echo "=======================================================\n";
