#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "=========================================="
echo "  Building PHP 8.5.1 with Array Shapes"
echo "=========================================="
echo ""

# Build the image
echo "[1/3] Building Docker image..."
docker build -t php-array-shapes:8.5 -f Dockerfile ..

echo ""
echo "[2/3] Verifying array shapes work..."
docker run --rm php-array-shapes:8.5 php -r '
declare(strict_arrays=1);

shape User = array{id: int, name: string};

function getUsers(): array<User> {
    return [
        ["id" => 1, "name" => "Alice"],
        ["id" => 2, "name" => "Bob"]
    ];
}

$users = getUsers();
echo "✓ array<User> works: " . count($users) . " users\n";

function getNumbers(): array<int> {
    return [1, 2, 3, 4, 5];
}

$nums = getNumbers();
echo "✓ array<int> works: " . array_sum($nums) . "\n";

echo "\n✓ All array shapes features working!\n";
'

echo ""
echo "[3/3] Image built successfully!"
echo ""
echo "=========================================="
echo "  Usage:"
echo "=========================================="
echo ""
echo "  # Run PHP CLI:"
echo "  docker run --rm -v \$(pwd):/app -w /app php-array-shapes:8.5 php your-script.php"
echo ""
echo "  # Start PHP-FPM + Nginx:"
echo "  cd docker && docker-compose up -d"
echo "  # Then visit: http://localhost:8080/"
echo ""
echo "  # Interactive shell:"
echo "  docker run --rm -it php-array-shapes:8.5 php -a"
echo ""
echo "=========================================="
