# Integration Guide for PHP Array Shapes RFC

## Overview

This implementation cannot be compiled standalone - it must be integrated into the PHP source tree (`php-src`). The files are designed as patches/additions to the Zend Engine.

## Prerequisites

```bash
# Clone PHP source
git clone https://github.com/php/php-src.git
cd php-src
git checkout PHP-8.5  # or master for development

# Install build dependencies (Debian/Ubuntu)
sudo apt-get install build-essential autoconf bison re2c libxml2-dev libsqlite3-dev
```

## Integration Steps

### 1. Copy Header File

```bash
cp src/Zend/zend_compile_array_shapes.h php-src/Zend/
```

### 2. Modify `Zend/zend_ast.h`

Add new AST node types:

```c
// After existing ZEND_AST_TYPE definitions
ZEND_AST_TYPE_ARRAY_OF,      // array<T>
ZEND_AST_TYPE_ARRAY_SHAPE,   // array{k: T, ...}
ZEND_AST_SHAPE_ELEMENT,      // key: type pair
ZEND_AST_SHAPE_ELEMENT_LIST, // list of shape elements
```

### 3. Modify `Zend/zend_types.h`

Add type mask bits (choose unused high bits):

```c
#define ZEND_TYPE_ARRAY_OF_BIT      (1u << 24)
#define ZEND_TYPE_ARRAY_SHAPE_BIT   (1u << 25)
```

### 4. Integrate Parser Grammar

Merge `zend_language_parser_array_shapes.y` into `Zend/zend_language_parser.y`:

1. Add token declarations in `%token` section
2. Add type declarations in `%type` section
3. Insert grammar rules into `type_without_static` production

### 5. Integrate Lexer Rules

Merge `zend_language_scanner_array_shapes.l` into `Zend/zend_language_scanner.l`:

1. Add start conditions
2. Add type context tracking to scanner globals
3. Insert lexer rules

### 6. Integrate Compilation Functions

Add to `Zend/zend_compile.c`:

```c
#include "zend_compile_array_shapes.h"

// In zend_compile_typename():
case ZEND_AST_TYPE_ARRAY_OF:
    return zend_compile_array_of_type(ast);
case ZEND_AST_TYPE_ARRAY_SHAPE:
    return zend_compile_array_shape_type(ast);
```

### 7. Integrate Runtime Validation

Add to `Zend/zend_execute.c`:

```c
#include "zend_compile_array_shapes.h"

// In ZEND_VERIFY_RETURN_TYPE handler:
if (ZEND_TYPE_HAS_EXTENDED_ARRAY(return_type)) {
    zend_verify_return_type_extended(EX(func), retval);
}
```

### 8. Integrate Reflection

Add to `ext/reflection/php_reflection.c`:

```c
#include "zend_compile_array_shapes.h"

// Call reflection_array_shapes_init() in PHP_MINIT_FUNCTION
```

### 9. Build PHP

```bash
cd php-src
./buildconf
./configure --enable-debug
make -j$(nproc)
```

### 10. Run Tests

```bash
# Copy test files
cp -r tests/* php-src/Zend/tests/type/array_shapes/

# Run tests
make test TESTS=Zend/tests/type/array_shapes/
```

## File Mapping

| This Repo | PHP Source Location |
|-----------|---------------------|
| `src/Zend/zend_compile_array_shapes.h` | `Zend/zend_compile_array_shapes.h` |
| `src/Zend/zend_compile_array_shapes.c` | Merge into `Zend/zend_compile.c` |
| `src/Zend/zend_execute_array_shapes.c` | Merge into `Zend/zend_execute.c` |
| `src/Zend/zend_language_parser_array_shapes.y` | Merge into `Zend/zend_language_parser.y` |
| `src/Zend/zend_language_scanner_array_shapes.l` | Merge into `Zend/zend_language_scanner.l` |
| `src/ext/reflection/php_reflection_array_shapes.c` | Merge into `ext/reflection/php_reflection.c` |
| `tests/*.phpt` | `Zend/tests/type/array_shapes/` |

## Quick Validation

To validate the C code compiles correctly (syntax check only):

```bash
cd src/Zend
gcc -fsyntax-only -I. -I/path/to/php-src -I/path/to/php-src/Zend \
    zend_compile_array_shapes.c zend_execute_array_shapes.c 2>&1 || true
```

Note: This will show missing include errors but validates C syntax.
