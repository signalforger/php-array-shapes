/*
   +----------------------------------------------------------------------+
   | Zend Engine - Array Shapes Compilation                              |
   +----------------------------------------------------------------------+
   | Copyright (c) Zend Technologies Ltd. (http://www.zend.com)           |
   +----------------------------------------------------------------------+
   | This source file is subject to version 2.00 of the Zend license,    |
   | that is bundled with this package in the file LICENSE, and is       |
   | available through the world-wide-web at the following url:          |
   | http://www.zend.com/license/2_00.txt.                                |
   +----------------------------------------------------------------------+
   | Authors: PHP RFC Array Shapes Implementation                         |
   +----------------------------------------------------------------------+
*/

#include "zend.h"
#include "zend_API.h"
#include "zend_compile.h"
#include "zend_language_parser.h"
#include "zend_compile_array_shapes.h"

/* ============================================================================
 * FORWARD DECLARATIONS
 * ============================================================================
 */

static zend_type zend_compile_type_internal(zend_ast *ast, bool persistent);
static uint32_t zend_compute_shape_hash(zend_array_shape *shape);

/* ============================================================================
 * TYPE COMPILATION: array<T>
 * ============================================================================
 *
 * Compiles an array<T> type declaration from its AST representation.
 *
 * AST Structure:
 *   ZEND_AST_TYPE_ARRAY_OF
 *     └── child[0]: element type AST
 *
 * Output:
 *   zend_type with:
 *     - ZEND_TYPE_ARRAY_OF_BIT set in type_mask
 *     - MAY_BE_ARRAY set in type_mask
 *     - ptr pointing to allocated zend_array_of descriptor
 *
 * Memory:
 *   Allocates zend_array_of in persistent memory (compile-time).
 *   Caller is responsible for releasing via zend_array_of_release().
 */

ZEND_API zend_type zend_compile_array_of_type(zend_ast *ast)
{
	zend_type result_type;
	zend_ast *element_type_ast;
	zend_array_of *array_of;
	uint8_t depth = 1;

	ZEND_ASSERT(ast != NULL);
	ZEND_ASSERT(ast->kind == ZEND_AST_TYPE_ARRAY_OF);

	/* Get the element type AST node */
	element_type_ast = ast->child[0];
	ZEND_ASSERT(element_type_ast != NULL);

	/* Allocate the array_of descriptor in persistent memory */
	array_of = zend_array_of_alloc(/* persistent */ true);

	/*
	 * Compile the element type recursively.
	 * This handles cases like array<array<int>> or array<array{id: int}>
	 */
	array_of->element_type = zend_compile_type_internal(element_type_ast, true);

	/*
	 * Calculate nesting depth for nested array<T> types.
	 * This helps with efficient validation at runtime.
	 *
	 * Examples:
	 *   array<int>          -> depth = 1
	 *   array<array<int>>   -> depth = 2
	 *   array<array{id:int}> -> depth = 1 (shape is not counted)
	 */
	if (ZEND_TYPE_IS_ARRAY_OF(array_of->element_type)) {
		zend_array_of *inner = ZEND_TYPE_ARRAY_OF_PTR(array_of->element_type);
		depth = inner->depth + 1;
	}
	array_of->depth = depth;

	/* Initialize the result zend_type */
	ZEND_TYPE_INIT_NONE(result_type);
	ZEND_TYPE_SET_ARRAY_OF_PTR(result_type, array_of);

	return result_type;
}

/* ============================================================================
 * TYPE COMPILATION: array{key: T, ...}
 * ============================================================================
 *
 * Compiles an array{...} shape type declaration from its AST representation.
 *
 * AST Structure:
 *   ZEND_AST_TYPE_ARRAY_SHAPE
 *     └── child[0]: ZEND_AST_SHAPE_ELEMENT_LIST
 *           ├── child[0]: ZEND_AST_SHAPE_ELEMENT (key1: type1)
 *           │     ├── child[0]: key AST (string/int literal)
 *           │     └── child[1]: type AST
 *           ├── child[1]: ZEND_AST_SHAPE_ELEMENT (key2: type2)
 *           └── ...
 *
 * Output:
 *   zend_type with:
 *     - ZEND_TYPE_ARRAY_SHAPE_BIT set in type_mask
 *     - MAY_BE_ARRAY set in type_mask
 *     - ptr pointing to allocated zend_array_shape descriptor
 *
 * Memory:
 *   Allocates zend_array_shape + elements in persistent memory.
 *   String keys are interned for efficient comparison.
 */

ZEND_API zend_type zend_compile_array_shape_type(zend_ast *ast)
{
	zend_type result_type;
	zend_ast_list *element_list;
	zend_array_shape *shape;
	uint32_t num_elements;
	uint32_t i;

	ZEND_ASSERT(ast != NULL);
	ZEND_ASSERT(ast->kind == ZEND_AST_TYPE_ARRAY_SHAPE);

	/* Get the element list */
	element_list = zend_ast_get_list(ast->child[0]);
	ZEND_ASSERT(element_list != NULL);
	ZEND_ASSERT(element_list->kind == ZEND_AST_SHAPE_ELEMENT_LIST);

	num_elements = element_list->children;

	/* Allocate shape descriptor with flexible array member */
	shape = zend_array_shape_alloc(num_elements, /* persistent */ true);

	/* Compile each shape element */
	for (i = 0; i < num_elements; i++) {
		zend_ast *element_ast = element_list->child[i];
		zend_ast *key_ast;
		zend_ast *type_ast;
		zend_shape_element *elem;
		zval *key_zval;

		ZEND_ASSERT(element_ast != NULL);
		ZEND_ASSERT(element_ast->kind == ZEND_AST_SHAPE_ELEMENT);

		key_ast = element_ast->child[0];
		type_ast = element_ast->child[1];
		elem = &shape->elements[i];

		/* Initialize element */
		memset(elem, 0, sizeof(*elem));

		/* Extract the key */
		key_zval = zend_ast_get_zval(key_ast);
		if (Z_TYPE_P(key_zval) == IS_STRING) {
			/* String key - intern for efficient comparison */
			elem->key = zend_new_interned_string(zend_string_copy(Z_STR_P(key_zval)));
			elem->is_string_key = 1;
			elem->key_num = 0;
		} else if (Z_TYPE_P(key_zval) == IS_LONG) {
			/* Integer key */
			elem->key = NULL;
			elem->is_string_key = 0;
			elem->key_num = (zend_ulong)Z_LVAL_P(key_zval);
		} else {
			/* Invalid key type - this should have been caught by parser */
			zend_error_noreturn(E_COMPILE_ERROR,
				"Shape key must be a string or integer");
		}

		/* Check for optional key flag (attr bit 0) */
		elem->is_optional = (element_ast->attr & 1) ? 1 : 0;

		/* Compile the element type recursively */
		elem->type = zend_compile_type_internal(type_ast, true);
	}

	/* Compute hash for quick shape comparison */
	shape->shape_hash = zend_compute_shape_hash(shape);

	/* Initialize the result zend_type */
	ZEND_TYPE_INIT_NONE(result_type);
	ZEND_TYPE_SET_ARRAY_SHAPE_PTR(result_type, shape);

	return result_type;
}

/* ============================================================================
 * INTERNAL TYPE COMPILATION
 * ============================================================================
 *
 * Recursively compiles any type AST node into a zend_type.
 * This is the core compilation function that handles all type variants.
 *
 * Parameters:
 *   ast        - The type AST node to compile
 *   persistent - Whether to allocate in persistent memory
 *
 * Returns:
 *   Compiled zend_type structure
 *
 * This function handles:
 *   - Built-in types (int, string, bool, etc.)
 *   - Class types
 *   - Nullable types (?T)
 *   - Union types (T|U)
 *   - Intersection types (T&U)
 *   - array<T> types
 *   - array{...} shape types
 */

static zend_type zend_compile_type_internal(zend_ast *ast, bool persistent)
{
	zend_type type;

	ZEND_ASSERT(ast != NULL);

	switch (ast->kind) {
		case ZEND_AST_TYPE_ARRAY_OF:
			/*
			 * array<T> type
			 * Delegate to specialized compiler
			 */
			return zend_compile_array_of_type(ast);

		case ZEND_AST_TYPE_ARRAY_SHAPE:
			/*
			 * array{key: T, ...} type
			 * Delegate to specialized compiler
			 */
			return zend_compile_array_shape_type(ast);

		case ZEND_AST_TYPE:
			/*
			 * Built-in type (int, string, bool, array, etc.)
			 * The type code is stored in ast->attr
			 */
			ZEND_TYPE_INIT_CODE(type, ast->attr, 0, 0);
			return type;

		case ZEND_AST_CLASS_TYPE:
		case ZEND_AST_NAME:
			/*
			 * Class/interface type reference
			 * Need to resolve the class name
			 */
			{
				zend_string *class_name = zend_ast_get_str(ast);
				zend_class_entry *ce = NULL;

				/*
				 * During compilation, we may not be able to resolve the class
				 * We store the name and resolve lazily at runtime
				 */
				if (persistent) {
					class_name = zend_new_interned_string(zend_string_copy(class_name));
				}

				ZEND_TYPE_INIT_CLASS(type, class_name, 0, 0);
				return type;
			}

		case ZEND_AST_NULLABLE_TYPE:
			/*
			 * Nullable type: ?T
			 * Compile inner type and add NULL to mask
			 */
			{
				type = zend_compile_type_internal(ast->child[0], persistent);
				type.type_mask |= MAY_BE_NULL;
				return type;
			}

		case ZEND_AST_TYPE_UNION:
			/*
			 * Union type: T|U|V
			 * Compile all member types and combine
			 */
			{
				zend_ast_list *list = zend_ast_get_list(ast);
				zend_type_list *type_list;
				uint32_t i;

				/* Allocate type list */
				type_list = (zend_type_list*)pemalloc(
					ZEND_TYPE_LIST_SIZE(list->children), persistent);
				type_list->num_types = list->children;

				/* Compile each union member */
				for (i = 0; i < list->children; i++) {
					type_list->types[i] = zend_compile_type_internal(
						list->child[i], persistent);
				}

				ZEND_TYPE_INIT_NONE(type);
				ZEND_TYPE_SET_LIST(type, type_list);
				type.type_mask |= _ZEND_TYPE_UNION_BIT;
				return type;
			}

		case ZEND_AST_TYPE_INTERSECTION:
			/*
			 * Intersection type: T&U&V
			 * Similar to union but with intersection semantics
			 */
			{
				zend_ast_list *list = zend_ast_get_list(ast);
				zend_type_list *type_list;
				uint32_t i;

				type_list = (zend_type_list*)pemalloc(
					ZEND_TYPE_LIST_SIZE(list->children), persistent);
				type_list->num_types = list->children;

				for (i = 0; i < list->children; i++) {
					type_list->types[i] = zend_compile_type_internal(
						list->child[i], persistent);
				}

				ZEND_TYPE_INIT_NONE(type);
				ZEND_TYPE_SET_LIST(type, type_list);
				type.type_mask |= _ZEND_TYPE_INTERSECTION_BIT;
				return type;
			}

		default:
			/*
			 * Unknown AST node type - shouldn't happen with valid code
			 */
			zend_error_noreturn(E_COMPILE_ERROR,
				"Invalid type AST node kind: %d", ast->kind);
	}

	/* Unreachable */
	ZEND_TYPE_INIT_NONE(type);
	return type;
}

/* ============================================================================
 * SHAPE HASH COMPUTATION
 * ============================================================================
 *
 * Computes a hash value for a shape descriptor.
 * Used for quick comparison of shape types.
 *
 * The hash incorporates:
 *   - Number of elements
 *   - Each key (string or integer)
 *   - Each type (basic type mask)
 *
 * This is not cryptographically secure, just for quick inequality checks.
 */

static uint32_t zend_compute_shape_hash(zend_array_shape *shape)
{
	uint32_t hash = 5381; /* DJB2 initial value */
	uint32_t i;

	/* Mix in element count */
	hash = ((hash << 5) + hash) ^ shape->num_elements;

	for (i = 0; i < shape->num_elements; i++) {
		zend_shape_element *elem = &shape->elements[i];

		/* Mix in key */
		if (elem->is_string_key && elem->key) {
			/* Use the string's hash */
			hash = ((hash << 5) + hash) ^ ZSTR_H(elem->key);
		} else {
			/* Use the integer key */
			hash = ((hash << 5) + hash) ^ (uint32_t)elem->key_num;
		}

		/* Mix in type mask */
		hash = ((hash << 5) + hash) ^ elem->type.type_mask;

		/* Mix in optional flag */
		hash = ((hash << 5) + hash) ^ elem->is_optional;
	}

	return hash;
}

/* ============================================================================
 * TYPE TO STRING CONVERSION
 * ============================================================================
 *
 * Converts a zend_type (including array shapes) to a human-readable string.
 * Used for reflection, error messages, and debugging.
 *
 * Examples:
 *   array<int>                -> "array<int>"
 *   array<array<string>>      -> "array<array<string>>"
 *   array{id: int}            -> "array{id: int}"
 *   array{id: int, name: ?string} -> "array{id: int, name: ?string}"
 */

ZEND_API zend_string* zend_type_to_string_extended(zend_type type)
{
	smart_str buf = {0};

	if (ZEND_TYPE_IS_ARRAY_OF(type)) {
		/*
		 * array<T> type
		 */
		zend_array_of *array_of = ZEND_TYPE_ARRAY_OF_PTR(type);
		zend_string *element_str;

		smart_str_appends(&buf, "array<");

		/* Recursively stringify element type */
		element_str = zend_type_to_string_extended(array_of->element_type);
		smart_str_append(&buf, element_str);
		zend_string_release(element_str);

		smart_str_appendc(&buf, '>');

	} else if (ZEND_TYPE_IS_ARRAY_SHAPE(type)) {
		/*
		 * array{key: T, ...} type
		 */
		zend_array_shape *shape = ZEND_TYPE_ARRAY_SHAPE_PTR(type);
		uint32_t i;

		smart_str_appends(&buf, "array{");

		for (i = 0; i < shape->num_elements; i++) {
			zend_shape_element *elem = &shape->elements[i];
			zend_string *type_str;

			if (i > 0) {
				smart_str_appends(&buf, ", ");
			}

			/* Output key */
			if (elem->is_string_key && elem->key) {
				smart_str_append(&buf, elem->key);
			} else {
				smart_str_append_long(&buf, (zend_long)elem->key_num);
			}

			/* Optional marker */
			if (elem->is_optional) {
				smart_str_appendc(&buf, '?');
			}

			smart_str_appends(&buf, ": ");

			/* Output type */
			type_str = zend_type_to_string_extended(elem->type);
			smart_str_append(&buf, type_str);
			zend_string_release(type_str);
		}

		smart_str_appendc(&buf, '}');

	} else if (ZEND_TYPE_HAS_LIST(type)) {
		/*
		 * Union or intersection type
		 */
		zend_type_list *list = ZEND_TYPE_LIST(type);
		const char *separator = (type.type_mask & _ZEND_TYPE_INTERSECTION_BIT)
			? "&" : "|";
		uint32_t i;

		for (i = 0; i < list->num_types; i++) {
			zend_string *member_str;

			if (i > 0) {
				smart_str_appends(&buf, separator);
			}

			member_str = zend_type_to_string_extended(list->types[i]);
			smart_str_append(&buf, member_str);
			zend_string_release(member_str);
		}

	} else if (ZEND_TYPE_HAS_NAME(type)) {
		/*
		 * Class/interface type
		 */
		smart_str_append(&buf, ZEND_TYPE_NAME(type));

	} else {
		/*
		 * Built-in type
		 * Convert type mask to string
		 */
		uint32_t mask = ZEND_TYPE_PURE_MASK(type);
		bool first = true;

		/* Handle nullable prefix */
		if (mask & MAY_BE_NULL) {
			/* Check if it's "?T" style or "T|null" style */
			uint32_t non_null = mask & ~MAY_BE_NULL;
			/* If only one non-null type, use ?T notation */
			if (non_null && (non_null & (non_null - 1)) == 0) {
				smart_str_appendc(&buf, '?');
				mask = non_null;
			}
		}

		/* Append type names */
		if (mask & MAY_BE_BOOL) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "bool");
			first = false;
		}
		if (mask & MAY_BE_LONG) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "int");
			first = false;
		}
		if (mask & MAY_BE_DOUBLE) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "float");
			first = false;
		}
		if (mask & MAY_BE_STRING) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "string");
			first = false;
		}
		if (mask & MAY_BE_ARRAY) {
			/* Only if not already handled as array<T> or array{...} */
			if (!ZEND_TYPE_HAS_EXTENDED_ARRAY(type)) {
				if (!first) smart_str_appendc(&buf, '|');
				smart_str_appends(&buf, "array");
				first = false;
			}
		}
		if (mask & MAY_BE_OBJECT) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "object");
			first = false;
		}
		if (mask & MAY_BE_CALLABLE) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "callable");
			first = false;
		}
		if (mask & MAY_BE_ITERABLE) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "iterable");
			first = false;
		}
		if (mask & MAY_BE_VOID) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "void");
			first = false;
		}
		if (mask & MAY_BE_NEVER) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "never");
			first = false;
		}
		if (mask & MAY_BE_NULL) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "null");
			first = false;
		}
		if (mask & MAY_BE_FALSE) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "false");
			first = false;
		}
		if (mask & MAY_BE_TRUE) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "true");
			first = false;
		}
		if (mask & MAY_BE_MIXED) {
			if (!first) smart_str_appendc(&buf, '|');
			smart_str_appends(&buf, "mixed");
			first = false;
		}

		if (first) {
			/* No type bits set - shouldn't happen */
			smart_str_appends(&buf, "unknown");
		}
	}

	smart_str_0(&buf);
	return buf.s ? buf.s : ZSTR_EMPTY_ALLOC();
}

/* ============================================================================
 * INTEGRATION WITH zend_compile_typename
 * ============================================================================
 *
 * The existing zend_compile_typename() function needs to be modified to
 * handle our new AST node types. Here's how to integrate:
 *
 * In zend_compile.c, modify zend_compile_typename():
 */

/*
zend_type zend_compile_typename(zend_ast *ast, bool force_allow_null)
{
	// ... existing code for other type AST nodes ...

	switch (ast->kind) {
		case ZEND_AST_TYPE_ARRAY_OF:
			return zend_compile_array_of_type(ast);

		case ZEND_AST_TYPE_ARRAY_SHAPE:
			return zend_compile_array_shape_type(ast);

		// ... existing cases ...
	}
}
*/

/* ============================================================================
 * TYPE COMPARISON
 * ============================================================================
 *
 * Checks if two array shape types are equivalent.
 * Used for return type covariance checking and instanceof.
 */

ZEND_API bool zend_types_are_equivalent(zend_type a, zend_type b)
{
	/* Quick rejection via type mask */
	if ((a.type_mask & ZEND_TYPE_EXTENDED_ARRAY_MASK) !=
		(b.type_mask & ZEND_TYPE_EXTENDED_ARRAY_MASK)) {
		return false;
	}

	if (ZEND_TYPE_IS_ARRAY_OF(a)) {
		zend_array_of *ao_a = ZEND_TYPE_ARRAY_OF_PTR(a);
		zend_array_of *ao_b = ZEND_TYPE_ARRAY_OF_PTR(b);

		if (!ZEND_TYPE_IS_ARRAY_OF(b)) {
			return false;
		}

		/* Compare element types recursively */
		return zend_types_are_equivalent(ao_a->element_type, ao_b->element_type);
	}

	if (ZEND_TYPE_IS_ARRAY_SHAPE(a)) {
		zend_array_shape *shape_a = ZEND_TYPE_ARRAY_SHAPE_PTR(a);
		zend_array_shape *shape_b = ZEND_TYPE_ARRAY_SHAPE_PTR(b);
		uint32_t i;

		if (!ZEND_TYPE_IS_ARRAY_SHAPE(b)) {
			return false;
		}

		/* Quick hash comparison */
		if (shape_a->shape_hash != shape_b->shape_hash) {
			return false;
		}

		/* Element count must match */
		if (shape_a->num_elements != shape_b->num_elements) {
			return false;
		}

		/* Compare each element */
		for (i = 0; i < shape_a->num_elements; i++) {
			zend_shape_element *elem_a = &shape_a->elements[i];
			zend_shape_element *elem_b = &shape_b->elements[i];

			/* Keys must match */
			if (elem_a->is_string_key != elem_b->is_string_key) {
				return false;
			}
			if (elem_a->is_string_key) {
				if (!zend_string_equals(elem_a->key, elem_b->key)) {
					return false;
				}
			} else {
				if (elem_a->key_num != elem_b->key_num) {
					return false;
				}
			}

			/* Optional flag must match */
			if (elem_a->is_optional != elem_b->is_optional) {
				return false;
			}

			/* Types must match */
			if (!zend_types_are_equivalent(elem_a->type, elem_b->type)) {
				return false;
			}
		}

		return true;
	}

	/* For non-extended types, compare masks and class names */
	if (ZEND_TYPE_PURE_MASK(a) != ZEND_TYPE_PURE_MASK(b)) {
		return false;
	}

	if (ZEND_TYPE_HAS_NAME(a) && ZEND_TYPE_HAS_NAME(b)) {
		return zend_string_equals_ci(ZEND_TYPE_NAME(a), ZEND_TYPE_NAME(b));
	}

	return !ZEND_TYPE_HAS_NAME(a) && !ZEND_TYPE_HAS_NAME(b);
}

/* ============================================================================
 * TYPE RELEASE
 * ============================================================================
 *
 * Frees memory allocated for a type, including array shape descriptors.
 * Called when a function/method is destroyed.
 */

ZEND_API void zend_type_release_extended(zend_type *type, bool persistent)
{
	if (ZEND_TYPE_IS_ARRAY_OF(*type)) {
		zend_array_of *array_of = ZEND_TYPE_ARRAY_OF_PTR(*type);
		if (array_of) {
			/* Recursively release element type */
			zend_type_release_extended(&array_of->element_type, persistent);
			zend_array_of_release(array_of, persistent);
		}
	} else if (ZEND_TYPE_IS_ARRAY_SHAPE(*type)) {
		zend_array_shape *shape = ZEND_TYPE_ARRAY_SHAPE_PTR(*type);
		if (shape) {
			/* Release element types */
			for (uint32_t i = 0; i < shape->num_elements; i++) {
				zend_type_release_extended(&shape->elements[i].type, persistent);
			}
			zend_array_shape_release(shape, persistent);
		}
	} else if (ZEND_TYPE_HAS_LIST(*type)) {
		zend_type_list *list = ZEND_TYPE_LIST(*type);
		for (uint32_t i = 0; i < list->num_types; i++) {
			zend_type_release_extended(&list->types[i], persistent);
		}
		pefree(list, persistent);
	} else if (ZEND_TYPE_HAS_NAME(*type)) {
		zend_string_release(ZEND_TYPE_NAME(*type));
	}

	ZEND_TYPE_INIT_NONE(*type);
}

/* ============================================================================
 * DEBUG HELPERS
 * ============================================================================
 */

#ifdef ZEND_DEBUG
ZEND_API void zend_dump_array_shape(zend_array_shape *shape)
{
	uint32_t i;

	php_printf("array shape (%u elements, hash=%u) {\n",
		shape->num_elements, shape->shape_hash);

	for (i = 0; i < shape->num_elements; i++) {
		zend_shape_element *elem = &shape->elements[i];
		zend_string *type_str = zend_type_to_string_extended(elem->type);

		if (elem->is_string_key) {
			php_printf("  '%s'%s: %s\n",
				ZSTR_VAL(elem->key),
				elem->is_optional ? "?" : "",
				ZSTR_VAL(type_str));
		} else {
			php_printf("  %lu%s: %s\n",
				elem->key_num,
				elem->is_optional ? "?" : "",
				ZSTR_VAL(type_str));
		}

		zend_string_release(type_str);
	}

	php_printf("}\n");
}

ZEND_API void zend_dump_array_of(zend_array_of *array_of)
{
	zend_string *type_str = zend_type_to_string_extended(array_of->element_type);

	php_printf("array<%s> (depth=%u)\n",
		ZSTR_VAL(type_str),
		array_of->depth);

	zend_string_release(type_str);
}
#endif /* ZEND_DEBUG */
