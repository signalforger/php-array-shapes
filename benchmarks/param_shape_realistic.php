<?php
/**
 * Realistic Benchmark: Array Shape Parameter Overhead
 *
 * This benchmark simulates more realistic function bodies to show
 * the relative overhead in context of actual work.
 */

$iterations = 500_000;

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║     Realistic Array Shape Parameter Overhead Benchmark               ║\n";
echo "║     Iterations: " . str_pad(number_format($iterations), 52) . "║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

// Test data
$order = [
    'id' => 'ORD-12345',
    'customer' => 'Alice Smith',
    'items' => [
        ['sku' => 'WIDGET-A', 'qty' => 2, 'price' => 29.99],
        ['sku' => 'GADGET-B', 'qty' => 1, 'price' => 49.99],
        ['sku' => 'THING-C', 'qty' => 3, 'price' => 9.99],
    ],
    'discount' => 0.1,
    'tax_rate' => 0.08,
];

// ============================================================================
// PLAIN ARRAY VERSION
// ============================================================================

function calculateOrderTotalPlain(array $order): array {
    $subtotal = 0;
    foreach ($order['items'] as $item) {
        $subtotal += $item['qty'] * $item['price'];
    }

    $discount = $subtotal * $order['discount'];
    $afterDiscount = $subtotal - $discount;
    $tax = $afterDiscount * $order['tax_rate'];
    $total = $afterDiscount + $tax;

    return [
        'subtotal' => round($subtotal, 2),
        'discount' => round($discount, 2),
        'tax' => round($tax, 2),
        'total' => round($total, 2),
    ];
}

// ============================================================================
// RUN PLAIN BENCHMARK
// ============================================================================

echo "Running plain array benchmark...\n";

// Warm up
for ($i = 0; $i < 10000; $i++) {
    calculateOrderTotalPlain($order);
}

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    calculateOrderTotalPlain($order);
}
$plainTime = (hrtime(true) - $start) / 1_000_000;

echo "Done.\n\n";

// ============================================================================
// SHAPED VERSION (via external file)
// ============================================================================

echo "Running shaped array benchmark (strict_arrays=1)...\n";

$shapeCode = <<<'PHP'
<?php
declare(strict_arrays=1);

$iterations = $argv[1];

$order = [
    'id' => 'ORD-12345',
    'customer' => 'Alice Smith',
    'items' => [
        ['sku' => 'WIDGET-A', 'qty' => 2, 'price' => 29.99],
        ['sku' => 'GADGET-B', 'qty' => 1, 'price' => 49.99],
        ['sku' => 'THING-C', 'qty' => 3, 'price' => 9.99],
    ],
    'discount' => 0.1,
    'tax_rate' => 0.08,
];

function calculateOrderTotalShaped(array{
    id: string,
    customer: string,
    items: array,
    discount: float,
    tax_rate: float
} $order): array{subtotal: float, discount: float, tax: float, total: float} {
    $subtotal = 0;
    foreach ($order['items'] as $item) {
        $subtotal += $item['qty'] * $item['price'];
    }

    $discount = $subtotal * $order['discount'];
    $afterDiscount = $subtotal - $discount;
    $tax = $afterDiscount * $order['tax_rate'];
    $total = $afterDiscount + $tax;

    return [
        'subtotal' => round($subtotal, 2),
        'discount' => round($discount, 2),
        'tax' => round($tax, 2),
        'total' => round($total, 2),
    ];
}

// Warm up
for ($i = 0; $i < 10000; $i++) {
    calculateOrderTotalShaped($order);
}

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    calculateOrderTotalShaped($order);
}
$time = (hrtime(true) - $start) / 1_000_000;

echo $time;
PHP;

$tmpFile = sys_get_temp_dir() . '/shape_realistic_' . getmypid() . '.php';
file_put_contents($tmpFile, $shapeCode);

$shapedTime = (float) trim(shell_exec(PHP_BINARY . " $tmpFile $iterations 2>&1"));

unlink($tmpFile);

echo "Done.\n\n";

// ============================================================================
// RESULTS
// ============================================================================

$overhead = $shapedTime - $plainTime;
$percent = (($shapedTime / $plainTime) - 1) * 100;
$perCallNs = ($overhead / $iterations) * 1_000_000;

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                     REALISTIC BENCHMARK RESULTS                      ║\n";
echo "╠══════════════════════════════════════════════════════════════════════╣\n";
printf("║  Plain array (no validation):     %10.2f ms                     ║\n", $plainTime);
printf("║  Array shapes (strict_arrays=1): %10.2f ms                     ║\n", $shapedTime);
echo "╠══════════════════════════════════════════════════════════════════════╣\n";
printf("║  Absolute overhead:               %+10.2f ms                     ║\n", $overhead);
printf("║  Relative overhead:               %+10.1f%%                       ║\n", $percent);
printf("║  Per-call overhead:               %+10.1f ns                      ║\n", $perCallNs);
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

echo "Context: This function performs actual work (loops through items,\n";
echo "calculates totals). The overhead is the cost of validating 5 shape\n";
echo "keys on input + 4 shape keys on output.\n\n";

// Verify results are correct
$result = calculateOrderTotalPlain($order);
echo "Verification - Order total: \$" . number_format($result['total'], 2) . "\n";
