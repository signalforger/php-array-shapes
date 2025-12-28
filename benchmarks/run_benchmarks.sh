#!/bin/bash

# Benchmark runner for array<T> type validation
# Usage: ./examples/run_benchmarks.sh

PHP="./php/php"
DIR="$(dirname "$0")"

echo ""
echo "###################################################"
echo "#     ARRAY<T> TYPE VALIDATION BENCHMARKS         #"
echo "###################################################"
echo ""

echo ">>> Without JIT <<<"
echo ""

echo "--- Running: bench_no_strict_arrays.php (no validation) ---"
$PHP "$DIR/bench_no_strict_arrays.php"
echo ""

echo "--- Running: bench_strict_arrays.php (with validation) ---"
$PHP "$DIR/bench_strict_arrays.php"
echo ""

echo ">>> With JIT enabled <<<"
echo ""

JIT_OPTS="-d opcache.enable=1 -d opcache.enable_cli=1 -d opcache.jit_buffer_size=100M -d opcache.jit=1255"

echo "--- Running: bench_no_strict_arrays.php (no validation, JIT) ---"
$PHP $JIT_OPTS "$DIR/bench_no_strict_arrays.php"
echo ""

echo "--- Running: bench_strict_arrays.php (with validation, JIT) ---"
$PHP $JIT_OPTS "$DIR/bench_strict_arrays.php"
echo ""

echo "###################################################"
echo "#                  COMPLETE                       #"
echo "###################################################"
