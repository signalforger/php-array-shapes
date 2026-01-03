/*
   +----------------------------------------------------------------------+
   | Zend Engine                                                          |
   +----------------------------------------------------------------------+
   | Copyright (c) Zend Technologies Ltd. (http://www.zend.com)           |
   +----------------------------------------------------------------------+
   | This source file is subject to version 2.00 of the Zend license,    |
   | that is bundled with this package in the file LICENSE, and is       |
   | available through the world-wide-web at the following url:          |
   | http://www.zend.com/license/2_00.txt.                                |
   | If you did not receive a copy of the Zend license and are unable to |
   | obtain it through the world-wide-web, please send a note to         |
   | license@zend.com so we can mail you a copy immediately.             |
   +----------------------------------------------------------------------+
   | Authors: PHP RFC Array Shapes Implementation                         |
   +----------------------------------------------------------------------+
*/

#ifndef ZEND_COMPILE_ARRAY_SHAPES_H
#define ZEND_COMPILE_ARRAY_SHAPES_H

#include "zend_types.h"
#include "zend_string.h"

/* ============================================================================
 * AST Node Types for Array Shapes
 * ============================================================================
 * These AST node types are used during parsing to represent the new
 * array type syntaxes.
 *
 * INTEGRATION NOTE:
 * -----------------
 * For production, these constants should be integrated into the PHP source:
 *
 * 1. Add to Zend/zend_ast.h in the ZEND_AST_* enum:
 *
 *    // After ZEND_AST_TYPE (around line 200):
 *    ZEND_AST_TYPE_ARRAY_OF,       // array<T>
 *    ZEND_AST_TYPE_ARRAY_SHAPE,    // array{key: T, ...}
 *    ZEND_AST_SHAPE_ELEMENT,       // Single key: type pair
 *    ZEND_AST_SHAPE_ELEMENT_LIST,  // List of shape elements
 *
 * 2. Update Zend/zend_language_parser.y to parse the new syntax:
 *
 *    type_expr:
 *        T_ARRAY '<' type_expr '>'
 *            { $$ = zend_ast_create(ZEND_AST_TYPE_ARRAY_OF, $3); }
 *      | T_ARRAY '{' shape_element_list '}'
 *            { $$ = zend_ast_create(ZEND_AST_TYPE_ARRAY_SHAPE, $3); }
 *      | ... existing rules ...
 *    ;
 *
 * The values below (200-203) are placeholders chosen to avoid collision
 * with existing AST types. In production, the enum provides proper values.
 */

/* AST node type for array<T> syntax */
#define ZEND_AST_TYPE_ARRAY_OF      200

/* AST node type for array{key: T, ...} syntax */
#define ZEND_AST_TYPE_ARRAY_SHAPE   201

/* AST node type for individual shape elements (key: type pairs) */
#define ZEND_AST_SHAPE_ELEMENT      202

/* AST node type for shape element list */
#define ZEND_AST_SHAPE_ELEMENT_LIST 203

/* ============================================================================
 * Type Mask Bits for Extended Array Types
 * ============================================================================
 * These bits are used in zend_type.type_mask to indicate that the type
 * carries additional array type information beyond just "array".
 *
 * Note: We use high bits to avoid collision with existing type masks.
 * Current zend_type uses bits 0-15 for basic types, so we use bits 24-25.
 */

/* Indicates the type is array<T> with element type descriptor */
#define ZEND_TYPE_ARRAY_OF_BIT      (1u << 24)

/* Indicates the type is array{k: T, ...} with shape descriptor */
#define ZEND_TYPE_ARRAY_SHAPE_BIT   (1u << 25)

/* Combined mask for either extended array type */
#define ZEND_TYPE_EXTENDED_ARRAY_MASK (ZEND_TYPE_ARRAY_OF_BIT | ZEND_TYPE_ARRAY_SHAPE_BIT)

/* ============================================================================
 * Type Check Macros
 * ============================================================================
 */

/* Check if a zend_type represents array<T> */
#define ZEND_TYPE_IS_ARRAY_OF(t) \
	(((t).type_mask & ZEND_TYPE_ARRAY_OF_BIT) != 0)

/* Check if a zend_type represents array{k: T, ...} */
#define ZEND_TYPE_IS_ARRAY_SHAPE(t) \
	(((t).type_mask & ZEND_TYPE_ARRAY_SHAPE_BIT) != 0)

/* Check if a zend_type has any extended array info */
#define ZEND_TYPE_HAS_EXTENDED_ARRAY(t) \
	(((t).type_mask & ZEND_TYPE_EXTENDED_ARRAY_MASK) != 0)

/* ============================================================================
 * Shape Element Structure
 * ============================================================================
 * Represents a single key-type pair in an array shape definition.
 *
 * Examples:
 *   array{id: int}         -> key="id", key_num=0, is_string_key=1, type=int
 *   array{0: string}       -> key=NULL, key_num=0, is_string_key=0, type=string
 *   array{name: ?string}   -> key="name", type=nullable string
 */
typedef struct _zend_shape_element {
	/* String key name (interned), or NULL if integer key */
	zend_string *key;

	/* Integer key value (only used when key == NULL) */
	zend_ulong key_num;

	/* Flag: 1 if this is a string key, 0 if integer key */
	uint8_t is_string_key;

	/* Flag: 1 if this key is optional (syntax: key?: type) */
	uint8_t is_optional;

	/* Reserved for alignment */
	uint8_t reserved[2];

	/* Type constraint for this element's value (can be nested) */
	zend_type type;
} zend_shape_element;

/* ============================================================================
 * Array Shape Descriptor
 * ============================================================================
 * Describes a complete array shape type.
 *
 * Memory layout uses flexible array member for elements.
 * Allocated in persistent memory during compilation.
 *
 * Example:
 *   array{id: int, name: string, email: ?string}
 *   -> num_elements = 3
 *   -> elements[0] = {key="id", type=int}
 *   -> elements[1] = {key="name", type=string}
 *   -> elements[2] = {key="email", type=?string}
 */
typedef struct _zend_array_shape {
	/* Number of defined shape elements */
	uint32_t num_elements;

	/* Reference count for memory management */
	uint32_t refcount;

	/* Hash of the shape for quick comparison */
	uint32_t shape_hash;

	/* Reserved for future use / alignment */
	uint32_t reserved;

	/* Flexible array member containing element descriptors */
	zend_shape_element elements[];
} zend_array_shape;

/* ============================================================================
 * Array-Of Descriptor
 * ============================================================================
 * Describes an array<T> type where all elements must be of type T.
 *
 * Supports nesting: array<array<int>> has depth=2.
 *
 * Example:
 *   array<int>                -> element_type=int, depth=1
 *   array<array<int>>         -> element_type=int, depth=2
 *   array<array{id: int}>     -> element_type=shape{id:int}, depth=1
 */
typedef struct _zend_array_of {
	/* Type constraint for each array element */
	zend_type element_type;

	/* Reference count for memory management */
	uint32_t refcount;

	/* Nesting depth (1 for array<T>, 2 for array<array<T>>, etc.) */
	uint8_t depth;

	/* Reserved for alignment / future use */
	uint8_t reserved[3];
} zend_array_of;

/* ============================================================================
 * Extended zend_type Union
 * ============================================================================
 * To avoid modifying the core zend_type structure size, we use
 * the existing pointer field in the union to store our descriptors.
 *
 * The zend_type structure already has a union that includes:
 *   - void *ptr
 *   - zend_class_entry *ce
 *   - zend_type_list *list
 *
 * We add conceptual mappings (these are accessed via casting):
 *   - zend_array_shape *shape
 *   - zend_array_of *array_of
 */

/* Get the array_of descriptor from a zend_type */
#define ZEND_TYPE_ARRAY_OF_PTR(t) \
	((zend_array_of*)(t).ptr)

/* Get the array_shape descriptor from a zend_type */
#define ZEND_TYPE_ARRAY_SHAPE_PTR(t) \
	((zend_array_shape*)(t).ptr)

/* Set the array_of descriptor in a zend_type */
#define ZEND_TYPE_SET_ARRAY_OF_PTR(t, p) do { \
	(t).ptr = (void*)(p); \
	(t).type_mask |= ZEND_TYPE_ARRAY_OF_BIT | MAY_BE_ARRAY; \
} while (0)

/* Set the array_shape descriptor in a zend_type */
#define ZEND_TYPE_SET_ARRAY_SHAPE_PTR(t, p) do { \
	(t).ptr = (void*)(p); \
	(t).type_mask |= ZEND_TYPE_ARRAY_SHAPE_BIT | MAY_BE_ARRAY; \
} while (0)

/* ============================================================================
 * Memory Allocation Helpers
 * ============================================================================
 */

/* Allocate a shape descriptor with n elements in persistent memory */
static inline zend_array_shape* zend_array_shape_alloc(uint32_t num_elements, bool persistent)
{
	size_t size = sizeof(zend_array_shape) + (num_elements * sizeof(zend_shape_element));
	zend_array_shape *shape = (zend_array_shape*)pemalloc(size, persistent);
	shape->num_elements = num_elements;
	shape->refcount = 1;
	shape->shape_hash = 0;
	shape->reserved = 0;
	return shape;
}

/* Allocate an array_of descriptor in persistent memory */
static inline zend_array_of* zend_array_of_alloc(bool persistent)
{
	zend_array_of *array_of = (zend_array_of*)pemalloc(sizeof(zend_array_of), persistent);
	array_of->refcount = 1;
	array_of->depth = 1;
	memset(array_of->reserved, 0, sizeof(array_of->reserved));
	return array_of;
}

/* Reference counting for shape descriptors */
static inline void zend_array_shape_addref(zend_array_shape *shape)
{
	if (shape) {
		shape->refcount++;
	}
}

static inline void zend_array_shape_release(zend_array_shape *shape, bool persistent)
{
	if (shape && --shape->refcount == 0) {
		/* Free any interned strings in elements */
		for (uint32_t i = 0; i < shape->num_elements; i++) {
			if (shape->elements[i].key) {
				zend_string_release(shape->elements[i].key);
			}
			/* Note: nested types are handled by zend_type_release() */
		}
		pefree(shape, persistent);
	}
}

/* Reference counting for array_of descriptors */
static inline void zend_array_of_addref(zend_array_of *array_of)
{
	if (array_of) {
		array_of->refcount++;
	}
}

static inline void zend_array_of_release(zend_array_of *array_of, bool persistent)
{
	if (array_of && --array_of->refcount == 0) {
		/* Note: element_type is handled by zend_type_release() */
		pefree(array_of, persistent);
	}
}

/* ============================================================================
 * Function Declarations
 * ============================================================================
 * These functions are implemented in zend_compile.c and zend_execute.c
 */

/* Compile array<T> type from AST (zend_compile.c) */
ZEND_API zend_type zend_compile_array_of_type(zend_ast *ast);

/* Compile array{k: T, ...} type from AST (zend_compile.c) */
ZEND_API zend_type zend_compile_array_shape_type(zend_ast *ast);

/* Convert extended array type to string for reflection/errors */
ZEND_API zend_string* zend_type_to_string_extended(zend_type type);

/* Validate array against array<T> constraint (zend_execute.c) */
ZEND_API bool zend_validate_array_of(zend_array_of *array_of, HashTable *ht, zval **bad_element);

/* Validate array against array{k: T} constraint (zend_execute.c) */
ZEND_API bool zend_validate_array_shape(zend_array_shape *shape, HashTable *ht,
	zend_string **missing_key, zend_ulong *missing_key_num, bool *is_string_key,
	zval **bad_value, zend_shape_element **failed_element);

/* ============================================================================
 * Error Message Formatting
 * ============================================================================
 */

/* Format a type name for error messages */
static inline const char* zend_get_type_name_for_error(zend_type type)
{
	if (ZEND_TYPE_IS_ARRAY_OF(type)) {
		return "array<T>";
	}
	if (ZEND_TYPE_IS_ARRAY_SHAPE(type)) {
		return "array{...}";
	}
	/* Fall back to standard type name */
	return zend_get_type_by_const(ZEND_TYPE_PURE_MASK(type));
}

#endif /* ZEND_COMPILE_ARRAY_SHAPES_H */
