#!/bin/bash
set -e

#===============================================================================
# PHP 8.5.1 with Array Shapes - Build Script
#===============================================================================
#
# This script compiles PHP 8.5.1 with the array shapes feature from:
# https://github.com/signalforger/php-array-shapes
#
# Usage:
#   ./build-php-array-shapes.sh [OPTIONS]
#
# Options:
#   --install-deps    Install build dependencies (requires sudo)
#   --prefix=PATH     Installation prefix (default: ./php-install)
#   --jobs=N          Number of parallel jobs (default: nproc)
#   --run-tests       Run test suite after build
#   --help            Show this help
#
#===============================================================================

PREFIX="$(pwd)/php-install"
JOBS=$(nproc 2>/dev/null || echo 4)
INSTALL_DEPS=0
RUN_TESTS=0
BUILD_DIR="$(pwd)/php-array-shapes-build"

# Parse arguments
for arg in "$@"; do
    case $arg in
        --install-deps)
            INSTALL_DEPS=1
            ;;
        --prefix=*)
            PREFIX="${arg#*=}"
            ;;
        --jobs=*)
            JOBS="${arg#*=}"
            ;;
        --run-tests)
            RUN_TESTS=1
            ;;
        --help)
            head -25 "$0" | tail -20
            exit 0
            ;;
        *)
            echo "Unknown option: $arg"
            exit 1
            ;;
    esac
done

echo "=============================================="
echo "PHP 8.5.1 with Array Shapes - Build Script"
echo "=============================================="
echo ""
echo "Build directory: $BUILD_DIR"
echo "Install prefix:  $PREFIX"
echo "Parallel jobs:   $JOBS"
echo ""

#-------------------------------------------------------------------------------
# Install dependencies
#-------------------------------------------------------------------------------
install_deps_debian() {
    echo "[*] Installing dependencies (Debian/Ubuntu)..."
    sudo apt-get update
    sudo apt-get install -y \
        build-essential \
        autoconf \
        automake \
        libtool \
        bison \
        re2c \
        pkg-config \
        libxml2-dev \
        libsqlite3-dev \
        libreadline-dev \
        libzip-dev \
        libssl-dev \
        libcurl4-openssl-dev \
        libonig-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype-dev \
        libwebp-dev \
        libxpm-dev \
        git
}

install_deps_fedora() {
    echo "[*] Installing dependencies (Fedora/RHEL)..."
    sudo dnf install -y \
        gcc \
        gcc-c++ \
        make \
        autoconf \
        automake \
        libtool \
        bison \
        re2c \
        pkgconfig \
        libxml2-devel \
        sqlite-devel \
        readline-devel \
        libzip-devel \
        openssl-devel \
        libcurl-devel \
        oniguruma-devel \
        libpng-devel \
        libjpeg-devel \
        freetype-devel \
        libwebp-devel \
        libXpm-devel \
        git
}

install_deps_arch() {
    echo "[*] Installing dependencies (Arch Linux)..."
    sudo pacman -S --needed --noconfirm \
        base-devel \
        autoconf \
        automake \
        libtool \
        bison \
        re2c \
        pkgconf \
        libxml2 \
        sqlite \
        readline \
        libzip \
        openssl \
        curl \
        oniguruma \
        libpng \
        libjpeg \
        freetype2 \
        libwebp \
        libxpm \
        git
}

install_deps_macos() {
    echo "[*] Installing dependencies (macOS)..."
    if ! command -v brew &> /dev/null; then
        echo "Error: Homebrew not found. Install from https://brew.sh"
        exit 1
    fi
    brew install \
        autoconf \
        automake \
        libtool \
        bison \
        re2c \
        pkg-config \
        libxml2 \
        sqlite \
        readline \
        libzip \
        openssl \
        curl \
        oniguruma \
        libpng \
        jpeg \
        freetype \
        webp \
        git
}

if [ "$INSTALL_DEPS" -eq 1 ]; then
    if [ -f /etc/debian_version ]; then
        install_deps_debian
    elif [ -f /etc/fedora-release ] || [ -f /etc/redhat-release ]; then
        install_deps_fedora
    elif [ -f /etc/arch-release ]; then
        install_deps_arch
    elif [ "$(uname)" == "Darwin" ]; then
        install_deps_macos
    else
        echo "Warning: Unknown OS. Please install dependencies manually."
        echo "Required: gcc, make, autoconf, bison, re2c, libxml2-dev, libsqlite3-dev"
    fi
fi

#-------------------------------------------------------------------------------
# Clone and build
#-------------------------------------------------------------------------------
mkdir -p "$BUILD_DIR"
cd "$BUILD_DIR"

# Clone or update php-src with array shapes
if [ -d "php-src" ]; then
    echo "[*] Updating existing php-src..."
    cd php-src
    git fetch origin
    git checkout feature/array-shapes
    git pull origin feature/array-shapes
else
    echo "[*] Cloning php-src with array shapes feature..."
    git clone --depth 1 -b feature/array-shapes \
        https://github.com/signalforger/php-src.git
    cd php-src
fi

# Generate configure script
echo "[*] Running buildconf..."
./buildconf --force

# Configure PHP
echo "[*] Configuring PHP..."
./configure \
    --prefix="$PREFIX" \
    --enable-debug \
    --enable-opcache \
    --enable-mbstring \
    --enable-bcmath \
    --enable-pcntl \
    --enable-sockets \
    --enable-intl \
    --with-readline \
    --with-curl \
    --with-openssl \
    --with-zlib \
    --with-zip \
    --without-pear

# Build
echo "[*] Building PHP (this may take a few minutes)..."
make -j"$JOBS"

# Run tests if requested
if [ "$RUN_TESTS" -eq 1 ]; then
    echo "[*] Running Zend tests..."
    ./sapi/cli/php run-tests.php -q -j"$JOBS" Zend/tests/type_declarations/array_shapes/
fi

# Install
echo "[*] Installing to $PREFIX..."
make install

#-------------------------------------------------------------------------------
# Done
#-------------------------------------------------------------------------------
echo ""
echo "=============================================="
echo "Build complete!"
echo "=============================================="
echo ""
echo "PHP binary: $PREFIX/bin/php"
echo "Version:    $($PREFIX/bin/php -v | head -1)"
echo ""
echo "Test the array shapes feature:"
echo ""
cat << 'EXAMPLE'
$PREFIX/bin/php -r '
declare(strict_arrays=1);

function getUser(): array{id: int, name: string} {
    return ["id" => 1, "name" => "Alice"];
}

function getScores(): array<string, int> {
    return ["alice" => 100, "bob" => 85];
}

var_dump(getUser());
var_dump(getScores());
echo "Array shapes work!\n";
'
EXAMPLE
echo ""
echo "Or add to your PATH:"
echo "  export PATH=\"$PREFIX/bin:\$PATH\""
echo ""
