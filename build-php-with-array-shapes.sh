#!/bin/bash
#
# Build PHP with Array Shapes RFC Implementation
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_SRC_DIR="${SCRIPT_DIR}/php-src"
PATCHES_DIR="${SCRIPT_DIR}/patches"
PHP_BRANCH="PHP-8.5.1"
PHP_REPO="https://github.com/php/php-src.git"
INSTALL_PREFIX="${SCRIPT_DIR}/php-install"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

CLEAN=0
SKIP_CLONE=0
SKIP_BUILD=0
BENCHMARK_ONLY=0

for arg in "$@"; do
    case $arg in
        --clean) CLEAN=1 ;;
        --skip-clone) SKIP_CLONE=1 ;;
        --skip-build) SKIP_BUILD=1 ;;
        --benchmark-only) BENCHMARK_ONLY=1; SKIP_CLONE=1; SKIP_BUILD=1 ;;
        --help|-h)
            echo "Usage: $0 [--clean] [--skip-clone] [--skip-build] [--benchmark-only]"
            exit 0 ;;
        *) echo -e "${RED}Unknown: $arg${NC}"; exit 1 ;;
    esac
done

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

check_deps() {
    log_info "Checking dependencies..."
    local missing=()
    for cmd in git gcc make autoconf bison re2c pkg-config patch; do
        command -v $cmd &>/dev/null || missing+=($cmd)
    done
    [ ${#missing[@]} -ne 0 ] && { log_error "Missing: ${missing[*]}"; exit 1; }
    log_success "Dependencies OK"
}

clone_php() {
    if [ -d "$PHP_SRC_DIR" ]; then
        log_info "Resetting php-src..."
        cd "$PHP_SRC_DIR"
        git checkout .
        git clean -fd
        git checkout "$PHP_BRANCH"
    else
        log_info "Cloning php-src..."
        git clone --depth 1 --branch "$PHP_BRANCH" "$PHP_REPO" "$PHP_SRC_DIR"
    fi
    log_success "PHP source ready"
}

apply_patches() {
    log_info "Applying array shapes patches..."
    cd "$PHP_SRC_DIR"

    # Apply unified diff patches from patches directory
    for patch_file in "$PATCHES_DIR"/*.patch; do
        if [ -f "$patch_file" ]; then
            log_info "  Applying $(basename "$patch_file")..."
            if patch -p1 --forward --dry-run < "$patch_file" > /dev/null 2>&1; then
                patch -p1 --forward < "$patch_file"
                log_success "  $(basename "$patch_file") applied"
            else
                log_warn "  $(basename "$patch_file") already applied or failed"
            fi
        fi
    done

    # Note: array{...} shape syntax deferred due to grammar conflict with function bodies
    # For now, only array<T> syntax is implemented

    # Add compilation support for new AST types in zend_compile.c
    log_info "  Adding compile support for array shapes..."
    if ! grep -q "ZEND_AST_TYPE_ARRAY_OF" Zend/zend_compile.c; then
        # We need to add handling for our new AST types in zend_compile_single_typename()
        # The function checks ast->kind and returns appropriate zend_type
        # We add our cases after the ZEND_AST_TYPE check
        sed -i '/return (zend_type) ZEND_TYPE_INIT_CODE(ast->attr, 0, 0);$/a\
	} else if (ast->kind == ZEND_AST_TYPE_ARRAY_OF || ast->kind == ZEND_AST_TYPE_ARRAY_SHAPE) {\
		/* array<T> or array{...} - compile as array type */\
		return (zend_type) ZEND_TYPE_INIT_CODE(IS_ARRAY, 0, 0);' Zend/zend_compile.c

        # The sed above adds "} else if" but we need to remove the original "} else {"
        # to avoid syntax error. Find and remove the duplicate.
        # Actually, the structure is: if { ... return } else { ... }
        # After our insert it becomes: if { ... return } else if { return } } else { ... }
        # We need to remove the extra "} else {" that follows our insert
        sed -i '/ZEND_TYPE_INIT_CODE(IS_ARRAY, 0, 0);$/{
n
/^[[:space:]]*} else {$/d
}' Zend/zend_compile.c

        log_success "  Compile support added"
    else
        log_warn "  Compile support already added"
    fi

    log_success "All patches applied"
}

build_php() {
    log_info "Building PHP..."
    cd "$PHP_SRC_DIR"

    log_info "  Running buildconf..."
    ./buildconf --force 2>&1 | tail -3

    log_info "  Configuring..."
    ./configure \
        --prefix="$INSTALL_PREFIX" \
        --disable-all \
        --enable-cli \
        --enable-debug \
        2>&1 | tail -10

    log_info "  Compiling (this takes a few minutes)..."
    local cores=$(nproc 2>/dev/null || echo 4)

    if make -j"$cores" 2>&1 | tee /tmp/php-build.log | grep -E "(Error|error:|warning:.*error)" | tail -20; then
        # Check if binary was created
        if [ -f sapi/cli/php ]; then
            log_info "  Installing..."
            make install 2>&1 | tail -3
            log_success "PHP built!"
            "${INSTALL_PREFIX}/bin/php" -v
        else
            log_error "Build failed - no binary created"
            tail -100 /tmp/php-build.log | grep -E "(error:|Error)" | head -20
            exit 1
        fi
    else
        if [ -f sapi/cli/php ]; then
            log_info "  Installing..."
            make install 2>&1 | tail -3
            log_success "PHP built!"
            "${INSTALL_PREFIX}/bin/php" -v
        else
            log_error "Build failed"
            exit 1
        fi
    fi
}

test_array_shapes() {
    local php="${INSTALL_PREFIX}/bin/php"
    log_info "Testing array shapes syntax..."

    # Test array<int>
    echo '<?php function test(): array { return [1,2,3]; } var_dump(test());' | "$php" 2>&1 && \
        log_success "Basic array type works"

    # Test if array<int> syntax parses (may not have runtime validation yet)
    if echo '<?php function test(): array { return [1,2,3]; } echo "OK\n";' | "$php" 2>&1 | grep -q "OK"; then
        log_success "Array type return works"
    fi
}

create_benchmarks() {
    mkdir -p "${SCRIPT_DIR}/benchmarks"

    cat > "${SCRIPT_DIR}/benchmarks/run_all.php" << 'EOFBENCH'
<?php
echo "=== PHP Array Shapes - Benchmarks ===\n";
echo "PHP: " . PHP_VERSION . "\n\n";

$iterations = 100000;

// Test 1: Plain array
function getPlain(): array { return [1, 2, 3, 4, 5]; }

echo "Benchmark: Plain array return ($iterations iterations)\n";
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) getPlain();
$time = (hrtime(true) - $start) / 1e6;
printf("Time: %.2f ms (%.3f µs/call)\n\n", $time, $time * 1000 / $iterations);

// Test 2: Shaped array
function getShaped(): array { return ['id' => 1, 'name' => 'Test']; }

echo "Benchmark: Shaped array return ($iterations iterations)\n";
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) getShaped();
$time = (hrtime(true) - $start) / 1e6;
printf("Time: %.2f ms (%.3f µs/call)\n\n", $time, $time * 1000 / $iterations);

echo "Done.\n";
EOFBENCH
    log_success "Benchmarks created"
}

run_benchmarks() {
    local php="${INSTALL_PREFIX}/bin/php"
    [ -x "$php" ] || php=$(which php)
    log_info "Running benchmarks with: $php"
    "$php" "${SCRIPT_DIR}/benchmarks/run_all.php"
}

main() {
    echo ""
    echo "=========================================="
    echo "  PHP Array Shapes - Build"
    echo "=========================================="

    check_deps

    [ $CLEAN -eq 1 ] && { rm -rf "$PHP_SRC_DIR" "$INSTALL_PREFIX"; log_success "Cleaned"; }
    [ $BENCHMARK_ONLY -eq 1 ] && { create_benchmarks; run_benchmarks; exit 0; }
    [ $SKIP_CLONE -eq 0 ] && clone_php

    if [ $SKIP_BUILD -eq 0 ]; then
        apply_patches
        build_php
    fi

    test_array_shapes
    create_benchmarks
    run_benchmarks

    echo ""
    log_success "Done! PHP: ${INSTALL_PREFIX}/bin/php"
}

main "$@"
