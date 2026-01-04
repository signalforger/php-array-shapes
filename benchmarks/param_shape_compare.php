<?php
/**
 * Benchmark: Array Shape Parameter Type Overhead - Side by Side Comparison
 *
 * This script runs both plain array and shaped array benchmarks and compares results.
 */

$iterations = 1_000_000;

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║     Array Shape Parameter Overhead Benchmark                         ║\n";
echo "║     Iterations: " . str_pad(number_format($iterations), 52) . "║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

// Test data
$point = ['x' => 10, 'y' => 20];
$user = ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'active' => true];
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'secret'
];
$nested = [
    'user' => ['id' => 1, 'name' => 'Alice'],
    'settings' => ['theme' => 'dark', 'lang' => 'en']
];

// Results storage
$results = [];

// ============================================================================
// PLAIN ARRAY FUNCTIONS (no validation)
// ============================================================================

function plainPoint(array $p): int {
    return $p['x'] + $p['y'];
}

function plainUser(array $u): string {
    return $u['name'];
}

function plainConfig(array $c): string {
    return $c['host'];
}

function plainNested(array $n): string {
    return $n['user']['name'];
}

// ============================================================================
// RUN PLAIN BENCHMARKS
// ============================================================================

echo "Running plain array benchmarks (no type hints)...\n";

// Warm up
for ($i = 0; $i < 10000; $i++) {
    plainPoint($point);
    plainUser($user);
    plainConfig($config);
    plainNested($nested);
}

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    plainPoint($point);
}
$results['point']['plain'] = (hrtime(true) - $start) / 1_000_000;

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    plainUser($user);
}
$results['user']['plain'] = (hrtime(true) - $start) / 1_000_000;

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    plainConfig($config);
}
$results['config']['plain'] = (hrtime(true) - $start) / 1_000_000;

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    plainNested($nested);
}
$results['nested']['plain'] = (hrtime(true) - $start) / 1_000_000;

echo "Done.\n\n";

// ============================================================================
// NOW INCLUDE STRICT ARRAYS FILE
// ============================================================================

echo "Running shaped array benchmarks (validated)...\n";

// We use a separate file to avoid function redefinition
$shapeCode = <<<'PHP'
<?php
// Note: declare(strict_arrays=1) is no longer required - validation is always enabled

$iterations = $argv[1];
$testCase = $argv[2];

$point = ['x' => 10, 'y' => 20];
$user = ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'active' => true];
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'secret'
];
$nested = [
    'user' => ['id' => 1, 'name' => 'Alice'],
    'settings' => ['theme' => 'dark', 'lang' => 'en']
];

function shapedPoint(array{x: int, y: int} $p): int {
    return $p['x'] + $p['y'];
}

function shapedUser(array{id: int, name: string, email: string, active: bool} $u): string {
    return $u['name'];
}

function shapedConfig(array{host: string, port: int, database: string, username: string, password: string} $c): string {
    return $c['host'];
}

function shapedNested(array{user: array{id: int, name: string}, settings: array{theme: string, lang: string}} $n): string {
    return $n['user']['name'];
}

// Warm up
for ($i = 0; $i < 10000; $i++) {
    shapedPoint($point);
    shapedUser($user);
    shapedConfig($config);
    shapedNested($nested);
}

$data = match($testCase) {
    'point' => $point,
    'user' => $user,
    'config' => $config,
    'nested' => $nested,
};

$func = match($testCase) {
    'point' => 'shapedPoint',
    'user' => 'shapedUser',
    'config' => 'shapedConfig',
    'nested' => 'shapedNested',
};

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $func($data);
}
$time = (hrtime(true) - $start) / 1_000_000;

echo $time;
PHP;

$tmpFile = sys_get_temp_dir() . '/shape_bench_' . getmypid() . '.php';
file_put_contents($tmpFile, $shapeCode);

$phpBinary = PHP_BINARY;

foreach (['point', 'user', 'config', 'nested'] as $case) {
    $output = shell_exec("$phpBinary $tmpFile $iterations $case 2>&1");
    $results[$case]['shaped'] = (float) trim($output);
}

unlink($tmpFile);

echo "Done.\n\n";

// ============================================================================
// DISPLAY RESULTS
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                            RESULTS                                   ║\n";
echo "╠══════════════════════════════════════════════════════════════════════╣\n";
echo "║ Test Case          │ Plain (ms) │ Shaped (ms) │ Overhead │ % Slower ║\n";
echo "╠══════════════════════════════════════════════════════════════════════╣\n";

$cases = [
    'point' => 'Point (2 keys)',
    'user' => 'User (4 keys)',
    'config' => 'Config (5 keys)',
    'nested' => 'Nested (2+2+2 keys)',
];

foreach ($cases as $key => $label) {
    $plain = $results[$key]['plain'];
    $shaped = $results[$key]['shaped'];
    $overhead = $shaped - $plain;
    $percent = (($shaped / $plain) - 1) * 100;

    printf("║ %-18s │ %10.2f │ %11.2f │ %+8.2f │ %+7.1f%% ║\n",
        $label, $plain, $shaped, $overhead, $percent);
}

echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

// Per-call overhead
echo "Per-call overhead (nanoseconds):\n";
foreach ($cases as $key => $label) {
    $plain = $results[$key]['plain'];
    $shaped = $results[$key]['shaped'];
    $overheadNs = (($shaped - $plain) / $iterations) * 1_000_000;
    printf("  %-20s %+.1f ns/call\n", $label . ':', $overheadNs);
}

echo "\n";
