#!/bin/bash
#
# Build PHP 8.5.1 with Typed Arrays & Array Shapes Docker image locally
#
# Usage:
#   ./build-docker.sh              # Build CLI image with default tag
#   ./build-docker.sh --fpm        # Build FPM image
#   ./build-docker.sh --tag 8.5.1  # Build with custom tag
#   ./build-docker.sh --test       # Build and run tests
#   ./build-docker.sh --push       # Build and push to registry
#

set -e

# Default values
VARIANT="cli"
TAG="latest"
IMAGE_NAME="php-array-shapes"
REGISTRY=""
PUSH=false
TEST=false

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --cli           Build CLI variant (default)"
    echo "  --fpm           Build FPM variant"
    echo "  --tag TAG       Set image tag (default: latest)"
    echo "  --name NAME     Set image name (default: php-array-shapes)"
    echo "  --registry REG  Set registry (e.g., ghcr.io/username)"
    echo "  --push          Push to registry after build"
    echo "  --test          Run tests after build"
    echo "  --all           Build both CLI and FPM variants"
    echo "  --help          Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                                    # Build CLI image"
    echo "  $0 --fpm --tag 8.5.1                  # Build FPM with tag"
    echo "  $0 --all --registry ghcr.io/user     # Build all, set registry"
    echo "  $0 --push --registry ghcr.io/user    # Build and push"
}

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Parse arguments
BUILD_ALL=false
while [[ $# -gt 0 ]]; do
    case $1 in
        --cli)
            VARIANT="cli"
            shift
            ;;
        --fpm)
            VARIANT="fpm"
            shift
            ;;
        --tag)
            TAG="$2"
            shift 2
            ;;
        --name)
            IMAGE_NAME="$2"
            shift 2
            ;;
        --registry)
            REGISTRY="$2/"
            shift 2
            ;;
        --push)
            PUSH=true
            shift
            ;;
        --test)
            TEST=true
            shift
            ;;
        --all)
            BUILD_ALL=true
            shift
            ;;
        --help)
            print_usage
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            print_usage
            exit 1
            ;;
    esac
done

build_image() {
    local variant=$1
    local full_tag="${REGISTRY}${IMAGE_NAME}:${TAG}-${variant}"

    # Also tag as just TAG if it's the CLI variant
    local extra_tags=""
    if [ "$variant" = "cli" ]; then
        extra_tags="-t ${REGISTRY}${IMAGE_NAME}:${TAG}"
    fi

    log_info "Building ${variant} image: ${full_tag}"
    log_info "This may take 15-30 minutes on first build..."
    echo ""

    docker build \
        --target "$variant" \
        -t "$full_tag" \
        $extra_tags \
        -f Dockerfile \
        .

    log_info "Successfully built: ${full_tag}"

    if [ "$variant" = "cli" ]; then
        log_info "Also tagged as: ${REGISTRY}${IMAGE_NAME}:${TAG}"
    fi
}

test_image() {
    local variant=$1
    local full_tag="${REGISTRY}${IMAGE_NAME}:${TAG}-${variant}"

    log_info "Testing image: ${full_tag}"
    echo ""

    # Test PHP version
    log_info "PHP Version:"
    docker run --rm "$full_tag" php -v
    echo ""

    # Test array shapes
    log_info "Testing typed arrays and array shapes:"
    docker run --rm "$full_tag" php -r "
// Test typed array
function getIds(): array<int> {
    return [1, 2, 3];
}

// Test array shape
function getUser(): array{id: int, name: string} {
    return ['id' => 1, 'name' => 'Alice'];
}

// Test shape type alias
// Note: shapes must be defined at file scope, so inline test uses inline syntax

echo \"Typed array test: \";
var_export(getIds());
echo \"\n\";

echo \"Array shape test: \";
var_export(getUser());
echo \"\n\";

echo \"\n\" . str_repeat('=', 50) . \"\n\";
echo \"All tests passed! Typed arrays and array shapes working.\n\";
echo str_repeat('=', 50) . \"\n\";
"
}

push_image() {
    local variant=$1
    local full_tag="${REGISTRY}${IMAGE_NAME}:${TAG}-${variant}"

    if [ -z "$REGISTRY" ]; then
        log_error "Registry not specified. Use --registry to set it."
        exit 1
    fi

    log_info "Pushing: ${full_tag}"
    docker push "$full_tag"

    if [ "$variant" = "cli" ]; then
        local base_tag="${REGISTRY}${IMAGE_NAME}:${TAG}"
        log_info "Pushing: ${base_tag}"
        docker push "$base_tag"
    fi
}

# Main execution
echo ""
echo "=========================================="
echo " PHP 8.5.1 + Typed Arrays & Array Shapes"
echo "=========================================="
echo ""

if [ "$BUILD_ALL" = true ]; then
    VARIANTS=("cli" "fpm")
else
    VARIANTS=("$VARIANT")
fi

for v in "${VARIANTS[@]}"; do
    build_image "$v"
    echo ""

    if [ "$TEST" = true ]; then
        test_image "$v"
        echo ""
    fi

    if [ "$PUSH" = true ]; then
        push_image "$v"
        echo ""
    fi
done

log_info "Done!"
echo ""
echo "To run the image:"
echo "  docker run --rm ${REGISTRY}${IMAGE_NAME}:${TAG} php -v"
echo ""
echo "To start an interactive PHP shell:"
echo "  docker run -it --rm ${REGISTRY}${IMAGE_NAME}:${TAG} php -a"
echo ""
echo "To run a PHP file:"
echo "  docker run --rm -v \$(pwd):/app ${REGISTRY}${IMAGE_NAME}:${TAG} php /app/your-script.php"
echo ""
