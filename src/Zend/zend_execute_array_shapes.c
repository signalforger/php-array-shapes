/*
   +----------------------------------------------------------------------+
   | Zend Engine - Array Shapes Runtime Validation                       |
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
#include "zend_execute.h"
#include "zend_compile_array_shapes.h"

/* ============================================================================
 * FORWARD DECLARATIONS
 * ============================================================================
 */

static bool zend_check_type_extended(zend_type type, zval *val, zend_class_entry **ce);

/* ============================================================================
 * ARRAY<T> VALIDATION
 * ============================================================================
 *
 * Validates that all elements of a HashTable conform to the element type.
 *
 * Parameters:
 *   array_of    - The array<T> type descriptor
 *   ht          - The HashTable to validate
 *   bad_element - OUT: Pointer to first failing element (if any)
 *
 * Returns:
 *   true if all elements pass validation
 *   false if any element fails (bad_element is set)
 *
 * Performance:
 *   O(n) where n = number of elements in array
 *   Early exit on first failure
 */

ZEND_API bool zend_validate_array_of(zend_array_of *array_of, HashTable *ht, zval **bad_element)
{
	zval *val;
	zend_class_entry *ce = NULL;

	ZEND_ASSERT(array_of != NULL);
	ZEND_ASSERT(ht != NULL);

	/* Empty array always validates */
	if (zend_hash_num_elements(ht) == 0) {
		return true;
	}

	/* Iterate all elements */
	ZEND_HASH_FOREACH_VAL(ht, val) {
		/*
		 * For nested array<array<T>>, we recursively validate.
		 * The depth field helps us know when to recurse.
		 */
		if (ZEND_TYPE_IS_ARRAY_OF(array_of->element_type)) {
			/* Nested array<T> - element must be array */
			if (Z_TYPE_P(val) != IS_ARRAY) {
				if (bad_element) *bad_element = val;
				return false;
			}

			/* Recursively validate inner array */
			zend_array_of *inner = ZEND_TYPE_ARRAY_OF_PTR(array_of->element_type);
			if (!zend_validate_array_of(inner, Z_ARRVAL_P(val), bad_element)) {
				return false;
			}
		}
		else if (ZEND_TYPE_IS_ARRAY_SHAPE(array_of->element_type)) {
			/* Nested array{...} shape - element must be array */
			if (Z_TYPE_P(val) != IS_ARRAY) {
				if (bad_element) *bad_element = val;
				return false;
			}

			/* Validate against shape */
			zend_array_shape *inner_shape = ZEND_TYPE_ARRAY_SHAPE_PTR(array_of->element_type);
			zend_string *missing_key = NULL;
			zend_ulong missing_key_num = 0;
			bool is_string_key = false;
			zval *bad_val = NULL;
			zend_shape_element *failed_elem = NULL;

			if (!zend_validate_array_shape(inner_shape, Z_ARRVAL_P(val),
					&missing_key, &missing_key_num, &is_string_key,
					&bad_val, &failed_elem)) {
				if (bad_element) *bad_element = bad_val ? bad_val : val;
				return false;
			}
		}
		else {
			/* Simple type check */
			if (!zend_check_type_extended(array_of->element_type, val, &ce)) {
				if (bad_element) *bad_element = val;
				return false;
			}
		}
	} ZEND_HASH_FOREACH_END();

	return true;
}

/* ============================================================================
 * ARRAY{...} SHAPE VALIDATION
 * ============================================================================
 *
 * Validates that a HashTable conforms to a shape definition.
 *
 * Parameters:
 *   shape           - The array{...} shape descriptor
 *   ht              - The HashTable to validate
 *   missing_key     - OUT: Name of missing string key (if applicable)
 *   missing_key_num - OUT: Value of missing integer key (if applicable)
 *   is_string_key   - OUT: Whether missing key is string (true) or int (false)
 *   bad_value       - OUT: Pointer to value with wrong type (if applicable)
 *   failed_element  - OUT: Shape element that failed (if applicable)
 *
 * Returns:
 *   true if shape validates successfully
 *   false if validation fails (output params indicate reason)
 *
 * Validation rules:
 *   1. All non-optional keys must be present
 *   2. Each present key's value must match its declared type
 *   3. Extra keys (not in shape) are allowed (permissive mode)
 *
 * Performance:
 *   O(k) where k = number of keys in shape definition
 *   (Not dependent on total array size if only checking declared keys)
 */

ZEND_API bool zend_validate_array_shape(
	zend_array_shape *shape,
	HashTable *ht,
	zend_string **missing_key,
	zend_ulong *missing_key_num,
	bool *is_string_key,
	zval **bad_value,
	zend_shape_element **failed_element)
{
	uint32_t i;
	zend_class_entry *ce = NULL;

	ZEND_ASSERT(shape != NULL);
	ZEND_ASSERT(ht != NULL);

	/* Initialize output parameters */
	if (missing_key) *missing_key = NULL;
	if (missing_key_num) *missing_key_num = 0;
	if (is_string_key) *is_string_key = false;
	if (bad_value) *bad_value = NULL;
	if (failed_element) *failed_element = NULL;

	/* Check each required key in the shape */
	for (i = 0; i < shape->num_elements; i++) {
		zend_shape_element *elem = &shape->elements[i];
		zval *val = NULL;

		/* Look up the key in the HashTable */
		if (elem->is_string_key) {
			val = zend_hash_find(ht, elem->key);
		} else {
			val = zend_hash_index_find(ht, elem->key_num);
		}

		/* Check if key exists */
		if (val == NULL) {
			if (elem->is_optional) {
				/* Optional key missing - that's OK */
				continue;
			}

			/* Required key missing - validation fails */
			if (missing_key && elem->is_string_key) {
				*missing_key = elem->key;
			}
			if (missing_key_num && !elem->is_string_key) {
				*missing_key_num = elem->key_num;
			}
			if (is_string_key) {
				*is_string_key = elem->is_string_key;
			}
			if (failed_element) {
				*failed_element = elem;
			}
			return false;
		}

		/*
		 * Validate the value's type.
		 * Handle nested types recursively.
		 */
		if (ZEND_TYPE_IS_ARRAY_OF(elem->type)) {
			/* Nested array<T> */
			if (Z_TYPE_P(val) != IS_ARRAY) {
				if (bad_value) *bad_value = val;
				if (failed_element) *failed_element = elem;
				return false;
			}

			zend_array_of *inner_array_of = ZEND_TYPE_ARRAY_OF_PTR(elem->type);
			zval *inner_bad = NULL;

			if (!zend_validate_array_of(inner_array_of, Z_ARRVAL_P(val), &inner_bad)) {
				if (bad_value) *bad_value = inner_bad ? inner_bad : val;
				if (failed_element) *failed_element = elem;
				return false;
			}
		}
		else if (ZEND_TYPE_IS_ARRAY_SHAPE(elem->type)) {
			/* Nested array{...} shape */
			if (Z_TYPE_P(val) != IS_ARRAY) {
				if (bad_value) *bad_value = val;
				if (failed_element) *failed_element = elem;
				return false;
			}

			zend_array_shape *inner_shape = ZEND_TYPE_ARRAY_SHAPE_PTR(elem->type);
			zend_string *inner_missing = NULL;
			zend_ulong inner_missing_num = 0;
			bool inner_is_string = false;
			zval *inner_bad = NULL;
			zend_shape_element *inner_failed = NULL;

			if (!zend_validate_array_shape(inner_shape, Z_ARRVAL_P(val),
					&inner_missing, &inner_missing_num, &inner_is_string,
					&inner_bad, &inner_failed)) {
				/* Propagate inner failure details */
				if (missing_key) *missing_key = inner_missing;
				if (missing_key_num) *missing_key_num = inner_missing_num;
				if (is_string_key) *is_string_key = inner_is_string;
				if (bad_value) *bad_value = inner_bad ? inner_bad : val;
				if (failed_element) *failed_element = inner_failed ? inner_failed : elem;
				return false;
			}
		}
		else {
			/* Simple type check */
			if (!zend_check_type_extended(elem->type, val, &ce)) {
				if (bad_value) *bad_value = val;
				if (failed_element) *failed_element = elem;
				return false;
			}
		}
	}

	return true;
}

/* ============================================================================
 * EXTENDED TYPE CHECK
 * ============================================================================
 *
 * Checks if a zval matches a zend_type (including extended array types).
 *
 * This extends the existing zend_check_type() to handle our new types.
 *
 * Parameters:
 *   type - The type to check against
 *   val  - The value to check
 *   ce   - OUT: Class entry if type is a class (optional)
 *
 * Returns:
 *   true if value matches type
 *   false otherwise
 */

static bool zend_check_type_extended(zend_type type, zval *val, zend_class_entry **ce)
{
	uint32_t type_mask;

	ZEND_ASSERT(val != NULL);

	/* Dereference if needed */
	if (Z_ISREF_P(val)) {
		val = Z_REFVAL_P(val);
	}

	/*
	 * Check for array<T> type
	 */
	if (ZEND_TYPE_IS_ARRAY_OF(type)) {
		zend_array_of *array_of = ZEND_TYPE_ARRAY_OF_PTR(type);

		/* Must be an array */
		if (Z_TYPE_P(val) != IS_ARRAY) {
			return false;
		}

		/* Validate all elements */
		zval *bad_element = NULL;
		return zend_validate_array_of(array_of, Z_ARRVAL_P(val), &bad_element);
	}

	/*
	 * Check for array{...} shape type
	 */
	if (ZEND_TYPE_IS_ARRAY_SHAPE(type)) {
		zend_array_shape *shape = ZEND_TYPE_ARRAY_SHAPE_PTR(type);

		/* Must be an array */
		if (Z_TYPE_P(val) != IS_ARRAY) {
			return false;
		}

		/* Validate shape */
		return zend_validate_array_shape(shape, Z_ARRVAL_P(val),
			NULL, NULL, NULL, NULL, NULL);
	}

	/*
	 * Check union/intersection types
	 */
	if (ZEND_TYPE_HAS_LIST(type)) {
		zend_type_list *list = ZEND_TYPE_LIST(type);
		bool is_intersection = (type.type_mask & _ZEND_TYPE_INTERSECTION_BIT) != 0;
		uint32_t i;

		if (is_intersection) {
			/* All types must match */
			for (i = 0; i < list->num_types; i++) {
				if (!zend_check_type_extended(list->types[i], val, ce)) {
					return false;
				}
			}
			return true;
		} else {
			/* Any type must match (union) */
			for (i = 0; i < list->num_types; i++) {
				if (zend_check_type_extended(list->types[i], val, ce)) {
					return true;
				}
			}
			return false;
		}
	}

	/*
	 * Check class type
	 */
	if (ZEND_TYPE_HAS_NAME(type)) {
		zend_string *name = ZEND_TYPE_NAME(type);
		zend_class_entry *expected_ce;

		if (Z_TYPE_P(val) != IS_OBJECT) {
			return false;
		}

		/* Resolve class entry */
		expected_ce = zend_lookup_class(name);
		if (!expected_ce) {
			return false;
		}

		if (ce) *ce = expected_ce;

		return instanceof_function(Z_OBJCE_P(val), expected_ce);
	}

	/*
	 * Check built-in types via type mask
	 */
	type_mask = ZEND_TYPE_PURE_MASK(type);

	switch (Z_TYPE_P(val)) {
		case IS_NULL:
			return (type_mask & MAY_BE_NULL) != 0;

		case IS_FALSE:
			return (type_mask & (MAY_BE_FALSE | MAY_BE_BOOL)) != 0;

		case IS_TRUE:
			return (type_mask & (MAY_BE_TRUE | MAY_BE_BOOL)) != 0;

		case IS_LONG:
			return (type_mask & MAY_BE_LONG) != 0;

		case IS_DOUBLE:
			return (type_mask & MAY_BE_DOUBLE) != 0;

		case IS_STRING:
			return (type_mask & MAY_BE_STRING) != 0;

		case IS_ARRAY:
			return (type_mask & MAY_BE_ARRAY) != 0;

		case IS_OBJECT:
			if (type_mask & MAY_BE_OBJECT) {
				return true;
			}
			/* Check if iterable is accepted and object is Traversable */
			if ((type_mask & MAY_BE_ITERABLE) &&
				instanceof_function(Z_OBJCE_P(val), zend_ce_traversable)) {
				return true;
			}
			return false;

		case IS_RESOURCE:
			return (type_mask & MAY_BE_RESOURCE) != 0;

		default:
			return false;
	}
}

/* ============================================================================
 * RETURN TYPE VERIFICATION
 * ============================================================================
 *
 * Verifies that a return value matches the declared return type.
 * This integrates with the existing zend_verify_return_type().
 *
 * Parameters:
 *   func   - The function being returned from
 *   retval - The return value to verify
 *
 * Returns:
 *   true if return value is valid
 *   false and throws TypeError if validation fails
 */

ZEND_API bool zend_verify_return_type_extended(
	const zend_function *func,
	zval *retval)
{
	zend_type return_type;
	zend_class_entry *ce = NULL;

	ZEND_ASSERT(func != NULL);

	/* Get the return type */
	if (func->type == ZEND_USER_FUNCTION) {
		return_type = func->common.arg_info[-1].type;
	} else {
		return_type = func->internal_function.arg_info[-1].type;
	}

	/* No return type declared - always valid */
	if (!ZEND_TYPE_IS_SET(return_type)) {
		return true;
	}

	/* Handle void return type */
	if (ZEND_TYPE_CONTAINS_CODE(return_type, IS_VOID)) {
		if (Z_TYPE_P(retval) != IS_NULL) {
			zend_type_error(
				"%s%s%s(): Return value must be of type void, %s returned",
				func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
				func->common.scope ? "::" : "",
				ZSTR_VAL(func->common.function_name),
				zend_zval_type_name(retval)
			);
			return false;
		}
		return true;
	}

	/* Handle never return type */
	if (ZEND_TYPE_CONTAINS_CODE(return_type, IS_NEVER)) {
		zend_type_error(
			"%s%s%s(): never-returning function must not return",
			func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
			func->common.scope ? "::" : "",
			ZSTR_VAL(func->common.function_name)
		);
		return false;
	}

	/*
	 * Handle array<T> return type
	 */
	if (ZEND_TYPE_IS_ARRAY_OF(return_type)) {
		zend_array_of *array_of = ZEND_TYPE_ARRAY_OF_PTR(return_type);
		zend_string *expected_str;
		zval *bad_element = NULL;

		/* Must be an array */
		if (Z_TYPE_P(retval) != IS_ARRAY) {
			expected_str = zend_type_to_string_extended(return_type);
			zend_type_error(
				"%s%s%s(): Return value must be of type %s, %s returned",
				func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
				func->common.scope ? "::" : "",
				ZSTR_VAL(func->common.function_name),
				ZSTR_VAL(expected_str),
				zend_zval_type_name(retval)
			);
			zend_string_release(expected_str);
			return false;
		}

		/* Validate all elements */
		if (!zend_validate_array_of(array_of, Z_ARRVAL_P(retval), &bad_element)) {
			zend_string *element_type_str = zend_type_to_string_extended(array_of->element_type);

			zend_type_error(
				"%s%s%s(): Return value must be of type array<%s>, "
				"array containing %s given",
				func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
				func->common.scope ? "::" : "",
				ZSTR_VAL(func->common.function_name),
				ZSTR_VAL(element_type_str),
				bad_element ? zend_zval_type_name(bad_element) : "invalid value"
			);
			zend_string_release(element_type_str);
			return false;
		}

		return true;
	}

	/*
	 * Handle array{...} shape return type
	 */
	if (ZEND_TYPE_IS_ARRAY_SHAPE(return_type)) {
		zend_array_shape *shape = ZEND_TYPE_ARRAY_SHAPE_PTR(return_type);
		zend_string *missing_key = NULL;
		zend_ulong missing_key_num = 0;
		bool is_string_key = false;
		zval *bad_value = NULL;
		zend_shape_element *failed_element = NULL;
		zend_string *expected_str;

		/* Must be an array */
		if (Z_TYPE_P(retval) != IS_ARRAY) {
			expected_str = zend_type_to_string_extended(return_type);
			zend_type_error(
				"%s%s%s(): Return value must be of type %s, %s returned",
				func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
				func->common.scope ? "::" : "",
				ZSTR_VAL(func->common.function_name),
				ZSTR_VAL(expected_str),
				zend_zval_type_name(retval)
			);
			zend_string_release(expected_str);
			return false;
		}

		/* Validate shape */
		if (!zend_validate_array_shape(shape, Z_ARRVAL_P(retval),
				&missing_key, &missing_key_num, &is_string_key,
				&bad_value, &failed_element)) {

			/*
			 * Determine the type of error:
			 * 1. Missing key error: failed_element is set but bad_value is NULL
			 * 2. Type mismatch error: both failed_element and bad_value are set
			 */
			bool is_missing_key_error = (failed_element != NULL && bad_value == NULL);

			if (is_missing_key_error) {
				/* Missing required key */
				if (is_string_key) {
					zend_type_error(
						"%s%s%s(): Return value missing required key '%s'",
						func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
						func->common.scope ? "::" : "",
						ZSTR_VAL(func->common.function_name),
						ZSTR_VAL(missing_key)
					);
				} else {
					zend_type_error(
						"%s%s%s(): Return value missing required key %lu",
						func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
						func->common.scope ? "::" : "",
						ZSTR_VAL(func->common.function_name),
						missing_key_num
					);
				}
			} else if (bad_value && failed_element) {
				/* Type mismatch for a key */
				zend_string *expected_type_str = zend_type_to_string_extended(failed_element->type);

				if (failed_element->is_string_key && failed_element->key) {
					zend_type_error(
						"%s%s%s(): Return value key '%s' must be of type %s, %s given",
						func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
						func->common.scope ? "::" : "",
						ZSTR_VAL(func->common.function_name),
						ZSTR_VAL(failed_element->key),
						ZSTR_VAL(expected_type_str),
						zend_zval_type_name(bad_value)
					);
				} else {
					zend_type_error(
						"%s%s%s(): Return value key %lu must be of type %s, %s given",
						func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
						func->common.scope ? "::" : "",
						ZSTR_VAL(func->common.function_name),
						failed_element->key_num,
						ZSTR_VAL(expected_type_str),
						zend_zval_type_name(bad_value)
					);
				}
				zend_string_release(expected_type_str);
			} else {
				/* Generic shape validation failure */
				expected_str = zend_type_to_string_extended(return_type);
				zend_type_error(
					"%s%s%s(): Return value does not match type %s",
					func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
					func->common.scope ? "::" : "",
					ZSTR_VAL(func->common.function_name),
					ZSTR_VAL(expected_str)
				);
				zend_string_release(expected_str);
			}
			return false;
		}

		return true;
	}

	/*
	 * Fall through to standard type checking for non-extended types
	 */
	if (!zend_check_type_extended(return_type, retval, &ce)) {
		zend_string *expected_str = zend_type_to_string_extended(return_type);
		zend_type_error(
			"%s%s%s(): Return value must be of type %s, %s returned",
			func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
			func->common.scope ? "::" : "",
			ZSTR_VAL(func->common.function_name),
			ZSTR_VAL(expected_str),
			zend_zval_type_name(retval)
		);
		zend_string_release(expected_str);
		return false;
	}

	return true;
}

/* ============================================================================
 * PARAMETER TYPE VERIFICATION
 * ============================================================================
 *
 * Verifies that an argument matches the declared parameter type.
 * Similar to return type verification but for function arguments.
 */

ZEND_API bool zend_verify_arg_type_extended(
	const zend_function *func,
	uint32_t arg_num,
	zval *arg)
{
	zend_type arg_type;
	zend_arg_info *arg_info;
	zend_class_entry *ce = NULL;

	ZEND_ASSERT(func != NULL);
	ZEND_ASSERT(arg_num >= 1);

	/* Get argument info */
	if (arg_num <= func->common.num_args) {
		arg_info = &func->common.arg_info[arg_num - 1];
	} else if (func->common.fn_flags & ZEND_ACC_VARIADIC) {
		arg_info = &func->common.arg_info[func->common.num_args];
	} else {
		/* No type info for this argument */
		return true;
	}

	arg_type = arg_info->type;

	/* No type declared - always valid */
	if (!ZEND_TYPE_IS_SET(arg_type)) {
		return true;
	}

	/*
	 * Handle array<T> parameter type
	 */
	if (ZEND_TYPE_IS_ARRAY_OF(arg_type)) {
		zend_array_of *array_of = ZEND_TYPE_ARRAY_OF_PTR(arg_type);
		zend_string *expected_str;
		zval *bad_element = NULL;

		if (Z_TYPE_P(arg) != IS_ARRAY) {
			expected_str = zend_type_to_string_extended(arg_type);
			zend_type_error(
				"%s%s%s(): Argument #%d ($%s) must be of type %s, %s given",
				func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
				func->common.scope ? "::" : "",
				ZSTR_VAL(func->common.function_name),
				arg_num,
				ZSTR_VAL(arg_info->name),
				ZSTR_VAL(expected_str),
				zend_zval_type_name(arg)
			);
			zend_string_release(expected_str);
			return false;
		}

		if (!zend_validate_array_of(array_of, Z_ARRVAL_P(arg), &bad_element)) {
			zend_string *element_type_str = zend_type_to_string_extended(array_of->element_type);

			zend_type_error(
				"%s%s%s(): Argument #%d ($%s) must be of type array<%s>, "
				"array containing %s given",
				func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
				func->common.scope ? "::" : "",
				ZSTR_VAL(func->common.function_name),
				arg_num,
				ZSTR_VAL(arg_info->name),
				ZSTR_VAL(element_type_str),
				bad_element ? zend_zval_type_name(bad_element) : "invalid value"
			);
			zend_string_release(element_type_str);
			return false;
		}

		return true;
	}

	/*
	 * Handle array{...} shape parameter type
	 */
	if (ZEND_TYPE_IS_ARRAY_SHAPE(arg_type)) {
		zend_array_shape *shape = ZEND_TYPE_ARRAY_SHAPE_PTR(arg_type);
		zend_string *missing_key = NULL;
		zend_ulong missing_key_num = 0;
		bool is_string_key = false;
		zval *bad_value = NULL;
		zend_shape_element *failed_element = NULL;
		zend_string *expected_str;

		if (Z_TYPE_P(arg) != IS_ARRAY) {
			expected_str = zend_type_to_string_extended(arg_type);
			zend_type_error(
				"%s%s%s(): Argument #%d ($%s) must be of type %s, %s given",
				func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
				func->common.scope ? "::" : "",
				ZSTR_VAL(func->common.function_name),
				arg_num,
				ZSTR_VAL(arg_info->name),
				ZSTR_VAL(expected_str),
				zend_zval_type_name(arg)
			);
			zend_string_release(expected_str);
			return false;
		}

		if (!zend_validate_array_shape(shape, Z_ARRVAL_P(arg),
				&missing_key, &missing_key_num, &is_string_key,
				&bad_value, &failed_element)) {

			/*
			 * Determine the type of error:
			 * 1. Missing key error: failed_element is set but bad_value is NULL
			 * 2. Type mismatch error: both failed_element and bad_value are set
			 */
			bool is_missing_key_error = (failed_element != NULL && bad_value == NULL);

			if (is_missing_key_error) {
				if (is_string_key) {
					zend_type_error(
						"%s%s%s(): Argument #%d ($%s) missing required key '%s'",
						func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
						func->common.scope ? "::" : "",
						ZSTR_VAL(func->common.function_name),
						arg_num,
						ZSTR_VAL(arg_info->name),
						ZSTR_VAL(missing_key)
					);
				} else {
					zend_type_error(
						"%s%s%s(): Argument #%d ($%s) missing required key %lu",
						func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
						func->common.scope ? "::" : "",
						ZSTR_VAL(func->common.function_name),
						arg_num,
						ZSTR_VAL(arg_info->name),
						missing_key_num
					);
				}
			} else if (bad_value && failed_element) {
				zend_string *expected_type_str = zend_type_to_string_extended(failed_element->type);

				if (failed_element->is_string_key && failed_element->key) {
					zend_type_error(
						"%s%s%s(): Argument #%d ($%s) key '%s' must be of type %s, %s given",
						func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
						func->common.scope ? "::" : "",
						ZSTR_VAL(func->common.function_name),
						arg_num,
						ZSTR_VAL(arg_info->name),
						ZSTR_VAL(failed_element->key),
						ZSTR_VAL(expected_type_str),
						zend_zval_type_name(bad_value)
					);
				} else {
					zend_type_error(
						"%s%s%s(): Argument #%d ($%s) key %lu must be of type %s, %s given",
						func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
						func->common.scope ? "::" : "",
						ZSTR_VAL(func->common.function_name),
						arg_num,
						ZSTR_VAL(arg_info->name),
						failed_element->key_num,
						ZSTR_VAL(expected_type_str),
						zend_zval_type_name(bad_value)
					);
				}
				zend_string_release(expected_type_str);
			} else {
				expected_str = zend_type_to_string_extended(arg_type);
				zend_type_error(
					"%s%s%s(): Argument #%d ($%s) does not match type %s",
					func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
					func->common.scope ? "::" : "",
					ZSTR_VAL(func->common.function_name),
					arg_num,
					ZSTR_VAL(arg_info->name),
					ZSTR_VAL(expected_str)
				);
				zend_string_release(expected_str);
			}
			return false;
		}

		return true;
	}

	/*
	 * Fall through to standard type checking
	 */
	if (!zend_check_type_extended(arg_type, arg, &ce)) {
		zend_string *expected_str = zend_type_to_string_extended(arg_type);
		zend_type_error(
			"%s%s%s(): Argument #%d ($%s) must be of type %s, %s given",
			func->common.scope ? ZSTR_VAL(func->common.scope->name) : "",
			func->common.scope ? "::" : "",
			ZSTR_VAL(func->common.function_name),
			arg_num,
			ZSTR_VAL(arg_info->name),
			ZSTR_VAL(expected_str),
			zend_zval_type_name(arg)
		);
		zend_string_release(expected_str);
		return false;
	}

	return true;
}

/* ============================================================================
 * INTEGRATION HOOKS
 * ============================================================================
 *
 * These functions modify the execution behavior to call our extended
 * type checking. They should be integrated into zend_execute.c.
 */

/*
 * Hook into ZEND_VERIFY_RETURN_TYPE_SPEC_*_HANDLER:
 *
 * Instead of:
 *   zend_verify_return_type(EX(func), retval, cache_slot);
 *
 * Use:
 *   if (ZEND_TYPE_HAS_EXTENDED_ARRAY(EX(func)->common.arg_info[-1].type)) {
 *       zend_verify_return_type_extended(EX(func), retval);
 *   } else {
 *       zend_verify_return_type(EX(func), retval, cache_slot);
 *   }
 */

/*
 * Hook into ZEND_RECV_SPEC_*_HANDLER:
 *
 * Similarly, check for extended array types before calling standard
 * argument verification.
 */
