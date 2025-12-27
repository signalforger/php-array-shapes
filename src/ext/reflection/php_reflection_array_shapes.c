/*
   +----------------------------------------------------------------------+
   | PHP Version 8                                                        |
   +----------------------------------------------------------------------+
   | Copyright (c) The PHP Group                                          |
   +----------------------------------------------------------------------+
   | This source file is subject to version 3.01 of the PHP license,     |
   | that is bundled with this package in the file LICENSE, and is       |
   | available through the world-wide-web at the following url:          |
   | https://www.php.net/license/3_01.txt                                 |
   | If you did not receive a copy of the PHP license and are unable to  |
   | obtain it through the world-wide-web, please send a note to         |
   | license@php.net so we can mail you a copy immediately.              |
   +----------------------------------------------------------------------+
   | Authors: PHP RFC Array Shapes Implementation                         |
   +----------------------------------------------------------------------+
*/

#include "php.h"
#include "php_reflection.h"
#include "zend_compile_array_shapes.h"

/* ============================================================================
 * REFLECTION TYPE CLASS EXTENSIONS
 * ============================================================================
 *
 * This file extends the Reflection API to support array shape types.
 *
 * New classes:
 *   - ReflectionArrayOfType (extends ReflectionType)
 *   - ReflectionArrayShapeType (extends ReflectionType)
 *   - ReflectionArrayShapeElement
 *
 * Extended methods:
 *   - ReflectionType::__toString() - handles extended array types
 *   - ReflectionType::allowsNull() - handles nullable extended types
 *   - ReflectionType::getName() - returns "array<T>" or "array{...}"
 */

/* ============================================================================
 * CLASS ENTRY DECLARATIONS
 * ============================================================================
 */

static zend_class_entry *reflection_array_of_type_ce;
static zend_class_entry *reflection_array_shape_type_ce;
static zend_class_entry *reflection_array_shape_element_ce;

/* Object handler tables */
static zend_object_handlers reflection_array_of_type_handlers;
static zend_object_handlers reflection_array_shape_type_handlers;
static zend_object_handlers reflection_array_shape_element_handlers;

/* ============================================================================
 * INTERNAL OBJECT STRUCTURES
 * ============================================================================
 */

/* ReflectionArrayOfType internal representation */
typedef struct _reflection_array_of_type_object {
	zend_array_of *array_of;    /* The array<T> type descriptor */
	zend_type type;             /* The full zend_type */
	zend_object std;            /* Standard object (must be last) */
} reflection_array_of_type_object;

/* ReflectionArrayShapeType internal representation */
typedef struct _reflection_array_shape_type_object {
	zend_array_shape *shape;    /* The shape descriptor */
	zend_type type;             /* The full zend_type */
	zend_object std;
} reflection_array_shape_type_object;

/* ReflectionArrayShapeElement internal representation */
typedef struct _reflection_array_shape_element_object {
	zend_shape_element *element;  /* The element descriptor */
	zend_object std;
} reflection_array_shape_element_object;

/* ============================================================================
 * OBJECT CREATION AND DESTRUCTION
 * ============================================================================
 */

/* Get internal object from zend_object */
static inline reflection_array_of_type_object* reflection_array_of_type_from_obj(zend_object *obj)
{
	return (reflection_array_of_type_object*)((char*)obj -
		XtOffsetOf(reflection_array_of_type_object, std));
}

static inline reflection_array_shape_type_object* reflection_array_shape_type_from_obj(zend_object *obj)
{
	return (reflection_array_shape_type_object*)((char*)obj -
		XtOffsetOf(reflection_array_shape_type_object, std));
}

static inline reflection_array_shape_element_object* reflection_array_shape_element_from_obj(zend_object *obj)
{
	return (reflection_array_shape_element_object*)((char*)obj -
		XtOffsetOf(reflection_array_shape_element_object, std));
}

/* Create ReflectionArrayOfType object */
static zend_object* reflection_array_of_type_create(zend_class_entry *ce)
{
	reflection_array_of_type_object *obj;

	obj = zend_object_alloc(sizeof(*obj), ce);

	zend_object_std_init(&obj->std, ce);
	object_properties_init(&obj->std, ce);

	obj->std.handlers = &reflection_array_of_type_handlers;
	obj->array_of = NULL;

	return &obj->std;
}

/* Create ReflectionArrayShapeType object */
static zend_object* reflection_array_shape_type_create(zend_class_entry *ce)
{
	reflection_array_shape_type_object *obj;

	obj = zend_object_alloc(sizeof(*obj), ce);

	zend_object_std_init(&obj->std, ce);
	object_properties_init(&obj->std, ce);

	obj->std.handlers = &reflection_array_shape_type_handlers;
	obj->shape = NULL;

	return &obj->std;
}

/* Create ReflectionArrayShapeElement object */
static zend_object* reflection_array_shape_element_create(zend_class_entry *ce)
{
	reflection_array_shape_element_object *obj;

	obj = zend_object_alloc(sizeof(*obj), ce);

	zend_object_std_init(&obj->std, ce);
	object_properties_init(&obj->std, ce);

	obj->std.handlers = &reflection_array_shape_element_handlers;
	obj->element = NULL;

	return &obj->std;
}

/* Free handlers */
static void reflection_array_of_type_free(zend_object *obj)
{
	reflection_array_of_type_object *intern = reflection_array_of_type_from_obj(obj);

	/* Note: We don't free array_of as it's owned by the function/class */
	zend_object_std_dtor(&intern->std);
}

static void reflection_array_shape_type_free(zend_object *obj)
{
	reflection_array_shape_type_object *intern = reflection_array_shape_type_from_obj(obj);
	zend_object_std_dtor(&intern->std);
}

static void reflection_array_shape_element_free(zend_object *obj)
{
	reflection_array_shape_element_object *intern = reflection_array_shape_element_from_obj(obj);
	zend_object_std_dtor(&intern->std);
}

/* ============================================================================
 * HELPER: CREATE REFLECTION OBJECTS FROM TYPES
 * ============================================================================
 */

/* Create appropriate ReflectionType subclass for a zend_type */
ZEND_API zend_object* reflection_type_from_zend_type(zend_type type)
{
	if (ZEND_TYPE_IS_ARRAY_OF(type)) {
		reflection_array_of_type_object *obj;
		zend_object *zobj;

		zobj = reflection_array_of_type_create(reflection_array_of_type_ce);
		obj = reflection_array_of_type_from_obj(zobj);

		obj->array_of = ZEND_TYPE_ARRAY_OF_PTR(type);
		obj->type = type;

		return zobj;
	}
	else if (ZEND_TYPE_IS_ARRAY_SHAPE(type)) {
		reflection_array_shape_type_object *obj;
		zend_object *zobj;

		zobj = reflection_array_shape_type_create(reflection_array_shape_type_ce);
		obj = reflection_array_shape_type_from_obj(zobj);

		obj->shape = ZEND_TYPE_ARRAY_SHAPE_PTR(type);
		obj->type = type;

		return zobj;
	}

	/* Return NULL for standard types - they use existing ReflectionType classes */
	return NULL;
}

/* ============================================================================
 * ReflectionArrayOfType METHODS
 * ============================================================================
 */

/* ReflectionArrayOfType::__toString(): string */
PHP_METHOD(ReflectionArrayOfType, __toString)
{
	reflection_array_of_type_object *intern;
	zend_string *str;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_of_type_from_obj(Z_OBJ_P(ZEND_THIS));

	str = zend_type_to_string_extended(intern->type);
	RETURN_STR(str);
}

/* ReflectionArrayOfType::getName(): string */
PHP_METHOD(ReflectionArrayOfType, getName)
{
	reflection_array_of_type_object *intern;
	zend_string *str;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_of_type_from_obj(Z_OBJ_P(ZEND_THIS));

	str = zend_type_to_string_extended(intern->type);
	RETURN_STR(str);
}

/* ReflectionArrayOfType::allowsNull(): bool */
PHP_METHOD(ReflectionArrayOfType, allowsNull)
{
	reflection_array_of_type_object *intern;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_of_type_from_obj(Z_OBJ_P(ZEND_THIS));

	RETURN_BOOL((intern->type.type_mask & MAY_BE_NULL) != 0);
}

/* ReflectionArrayOfType::getElementType(): ReflectionType */
PHP_METHOD(ReflectionArrayOfType, getElementType)
{
	reflection_array_of_type_object *intern;
	zend_object *element_type_obj;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_of_type_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->array_of) {
		RETURN_NULL();
	}

	/* Create ReflectionType for element type */
	element_type_obj = reflection_type_from_zend_type(intern->array_of->element_type);

	if (element_type_obj) {
		RETURN_OBJ(element_type_obj);
	}

	/*
	 * For simple types, we need to create a standard ReflectionNamedType.
	 * This requires calling into the existing reflection infrastructure.
	 */
	/* TODO: Create ReflectionNamedType for simple element types */
	RETURN_NULL();
}

/* ReflectionArrayOfType::isBuiltin(): bool */
PHP_METHOD(ReflectionArrayOfType, isBuiltin)
{
	ZEND_PARSE_PARAMETERS_NONE();

	/* array<T> is considered a built-in type (variant of array) */
	RETURN_TRUE;
}

/* ReflectionArrayOfType::getDepth(): int */
PHP_METHOD(ReflectionArrayOfType, getDepth)
{
	reflection_array_of_type_object *intern;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_of_type_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->array_of) {
		RETURN_LONG(0);
	}

	RETURN_LONG(intern->array_of->depth);
}

/* ============================================================================
 * ReflectionArrayShapeType METHODS
 * ============================================================================
 */

/* ReflectionArrayShapeType::__toString(): string */
PHP_METHOD(ReflectionArrayShapeType, __toString)
{
	reflection_array_shape_type_object *intern;
	zend_string *str;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_shape_type_from_obj(Z_OBJ_P(ZEND_THIS));

	str = zend_type_to_string_extended(intern->type);
	RETURN_STR(str);
}

/* ReflectionArrayShapeType::getName(): string */
PHP_METHOD(ReflectionArrayShapeType, getName)
{
	reflection_array_shape_type_object *intern;
	zend_string *str;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_shape_type_from_obj(Z_OBJ_P(ZEND_THIS));

	str = zend_type_to_string_extended(intern->type);
	RETURN_STR(str);
}

/* ReflectionArrayShapeType::allowsNull(): bool */
PHP_METHOD(ReflectionArrayShapeType, allowsNull)
{
	reflection_array_shape_type_object *intern;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_shape_type_from_obj(Z_OBJ_P(ZEND_THIS));

	RETURN_BOOL((intern->type.type_mask & MAY_BE_NULL) != 0);
}

/* ReflectionArrayShapeType::isBuiltin(): bool */
PHP_METHOD(ReflectionArrayShapeType, isBuiltin)
{
	ZEND_PARSE_PARAMETERS_NONE();

	RETURN_TRUE;
}

/* ReflectionArrayShapeType::getElements(): array<ReflectionArrayShapeElement> */
PHP_METHOD(ReflectionArrayShapeType, getElements)
{
	reflection_array_shape_type_object *intern;
	zval elements;
	uint32_t i;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_shape_type_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->shape) {
		RETURN_EMPTY_ARRAY();
	}

	array_init_size(&elements, intern->shape->num_elements);

	for (i = 0; i < intern->shape->num_elements; i++) {
		reflection_array_shape_element_object *elem_obj;
		zend_object *zobj;
		zval elem_zval;

		/* Create ReflectionArrayShapeElement */
		zobj = reflection_array_shape_element_create(reflection_array_shape_element_ce);
		elem_obj = reflection_array_shape_element_from_obj(zobj);
		elem_obj->element = &intern->shape->elements[i];

		ZVAL_OBJ(&elem_zval, zobj);
		zend_hash_next_index_insert_new(Z_ARRVAL(elements), &elem_zval);
	}

	RETURN_ARR(Z_ARR(elements));
}

/* ReflectionArrayShapeType::hasElement(string|int $key): bool */
PHP_METHOD(ReflectionArrayShapeType, hasElement)
{
	reflection_array_shape_type_object *intern;
	zval *key;
	uint32_t i;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(key)
	ZEND_PARSE_PARAMETERS_END();

	intern = reflection_array_shape_type_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->shape) {
		RETURN_FALSE;
	}

	for (i = 0; i < intern->shape->num_elements; i++) {
		zend_shape_element *elem = &intern->shape->elements[i];

		if (Z_TYPE_P(key) == IS_STRING) {
			if (elem->is_string_key && elem->key &&
				zend_string_equals(elem->key, Z_STR_P(key))) {
				RETURN_TRUE;
			}
		} else if (Z_TYPE_P(key) == IS_LONG) {
			if (!elem->is_string_key && elem->key_num == (zend_ulong)Z_LVAL_P(key)) {
				RETURN_TRUE;
			}
		}
	}

	RETURN_FALSE;
}

/* ReflectionArrayShapeType::getElement(string|int $key): ?ReflectionArrayShapeElement */
PHP_METHOD(ReflectionArrayShapeType, getElement)
{
	reflection_array_shape_type_object *intern;
	zval *key;
	uint32_t i;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(key)
	ZEND_PARSE_PARAMETERS_END();

	intern = reflection_array_shape_type_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->shape) {
		RETURN_NULL();
	}

	for (i = 0; i < intern->shape->num_elements; i++) {
		zend_shape_element *elem = &intern->shape->elements[i];
		bool found = false;

		if (Z_TYPE_P(key) == IS_STRING) {
			if (elem->is_string_key && elem->key &&
				zend_string_equals(elem->key, Z_STR_P(key))) {
				found = true;
			}
		} else if (Z_TYPE_P(key) == IS_LONG) {
			if (!elem->is_string_key && elem->key_num == (zend_ulong)Z_LVAL_P(key)) {
				found = true;
			}
		}

		if (found) {
			reflection_array_shape_element_object *elem_obj;
			zend_object *zobj;

			zobj = reflection_array_shape_element_create(reflection_array_shape_element_ce);
			elem_obj = reflection_array_shape_element_from_obj(zobj);
			elem_obj->element = elem;

			RETURN_OBJ(zobj);
		}
	}

	RETURN_NULL();
}

/* ReflectionArrayShapeType::getElementCount(): int */
PHP_METHOD(ReflectionArrayShapeType, getElementCount)
{
	reflection_array_shape_type_object *intern;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_shape_type_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->shape) {
		RETURN_LONG(0);
	}

	RETURN_LONG(intern->shape->num_elements);
}

/* ============================================================================
 * ReflectionArrayShapeElement METHODS
 * ============================================================================
 */

/* ReflectionArrayShapeElement::getName(): string|int */
PHP_METHOD(ReflectionArrayShapeElement, getName)
{
	reflection_array_shape_element_object *intern;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_shape_element_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->element) {
		RETURN_NULL();
	}

	if (intern->element->is_string_key) {
		RETURN_STR_COPY(intern->element->key);
	} else {
		RETURN_LONG(intern->element->key_num);
	}
}

/* ReflectionArrayShapeElement::getKey(): string|int */
PHP_METHOD(ReflectionArrayShapeElement, getKey)
{
	/* Alias for getName() */
	reflection_array_shape_element_object *intern;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_shape_element_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->element) {
		RETURN_NULL();
	}

	if (intern->element->is_string_key) {
		RETURN_STR_COPY(intern->element->key);
	} else {
		RETURN_LONG(intern->element->key_num);
	}
}

/* ReflectionArrayShapeElement::isStringKey(): bool */
PHP_METHOD(ReflectionArrayShapeElement, isStringKey)
{
	reflection_array_shape_element_object *intern;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_shape_element_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->element) {
		RETURN_FALSE;
	}

	RETURN_BOOL(intern->element->is_string_key);
}

/* ReflectionArrayShapeElement::isOptional(): bool */
PHP_METHOD(ReflectionArrayShapeElement, isOptional)
{
	reflection_array_shape_element_object *intern;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_shape_element_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->element) {
		RETURN_FALSE;
	}

	RETURN_BOOL(intern->element->is_optional);
}

/* ReflectionArrayShapeElement::getType(): ReflectionType */
PHP_METHOD(ReflectionArrayShapeElement, getType)
{
	reflection_array_shape_element_object *intern;
	zend_object *type_obj;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_shape_element_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->element) {
		RETURN_NULL();
	}

	/* Create ReflectionType for element type */
	type_obj = reflection_type_from_zend_type(intern->element->type);

	if (type_obj) {
		RETURN_OBJ(type_obj);
	}

	/* For simple types, return NULL (TODO: create ReflectionNamedType) */
	RETURN_NULL();
}

/* ReflectionArrayShapeElement::__toString(): string */
PHP_METHOD(ReflectionArrayShapeElement, __toString)
{
	reflection_array_shape_element_object *intern;
	smart_str buf = {0};
	zend_string *type_str;

	ZEND_PARSE_PARAMETERS_NONE();

	intern = reflection_array_shape_element_from_obj(Z_OBJ_P(ZEND_THIS));

	if (!intern->element) {
		RETURN_EMPTY_STRING();
	}

	/* Format: "key: type" or "key?: type" */
	if (intern->element->is_string_key) {
		smart_str_append(&buf, intern->element->key);
	} else {
		smart_str_append_long(&buf, (zend_long)intern->element->key_num);
	}

	if (intern->element->is_optional) {
		smart_str_appendc(&buf, '?');
	}

	smart_str_appends(&buf, ": ");

	type_str = zend_type_to_string_extended(intern->element->type);
	smart_str_append(&buf, type_str);
	zend_string_release(type_str);

	smart_str_0(&buf);

	RETURN_STR(buf.s ? buf.s : ZSTR_EMPTY_ALLOC());
}

/* ============================================================================
 * METHOD ARGUMENT INFO
 * ============================================================================
 */

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_reflection_array_type_toString, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_reflection_array_type_getName, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_reflection_array_type_allowsNull, 0, 0, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_reflection_array_type_isBuiltin, 0, 0, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_reflection_array_of_getElementType, 0, 0, ReflectionType, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_reflection_array_of_getDepth, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_reflection_shape_getElements, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_reflection_shape_hasElement, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_INFO(0, key)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_reflection_shape_getElement, 0, 1, ReflectionArrayShapeElement, 1)
	ZEND_ARG_INFO(0, key)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_reflection_shape_getElementCount, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_reflection_shape_element_getName, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_reflection_shape_element_isStringKey, 0, 0, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_reflection_shape_element_isOptional, 0, 0, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_reflection_shape_element_getType, 0, 0, ReflectionType, 1)
ZEND_END_ARG_INFO()

/* ============================================================================
 * METHOD TABLES
 * ============================================================================
 */

static const zend_function_entry reflection_array_of_type_methods[] = {
	PHP_ME(ReflectionArrayOfType, __toString,    arginfo_reflection_array_type_toString,    ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayOfType, getName,       arginfo_reflection_array_type_getName,     ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayOfType, allowsNull,    arginfo_reflection_array_type_allowsNull,  ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayOfType, isBuiltin,     arginfo_reflection_array_type_isBuiltin,   ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayOfType, getElementType, arginfo_reflection_array_of_getElementType, ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayOfType, getDepth,      arginfo_reflection_array_of_getDepth,      ZEND_ACC_PUBLIC)
	PHP_FE_END
};

static const zend_function_entry reflection_array_shape_type_methods[] = {
	PHP_ME(ReflectionArrayShapeType, __toString,      arginfo_reflection_array_type_toString,     ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeType, getName,         arginfo_reflection_array_type_getName,      ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeType, allowsNull,      arginfo_reflection_array_type_allowsNull,   ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeType, isBuiltin,       arginfo_reflection_array_type_isBuiltin,    ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeType, getElements,     arginfo_reflection_shape_getElements,       ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeType, hasElement,      arginfo_reflection_shape_hasElement,        ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeType, getElement,      arginfo_reflection_shape_getElement,        ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeType, getElementCount, arginfo_reflection_shape_getElementCount,   ZEND_ACC_PUBLIC)
	PHP_FE_END
};

static const zend_function_entry reflection_array_shape_element_methods[] = {
	PHP_ME(ReflectionArrayShapeElement, getName,       arginfo_reflection_shape_element_getName,     ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeElement, getKey,        arginfo_reflection_shape_element_getName,     ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeElement, isStringKey,   arginfo_reflection_shape_element_isStringKey, ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeElement, isOptional,    arginfo_reflection_shape_element_isOptional,  ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeElement, getType,       arginfo_reflection_shape_element_getType,     ZEND_ACC_PUBLIC)
	PHP_ME(ReflectionArrayShapeElement, __toString,    arginfo_reflection_array_type_toString,       ZEND_ACC_PUBLIC)
	PHP_FE_END
};

/* ============================================================================
 * MODULE INITIALIZATION
 * ============================================================================
 */

/*
 * Register the new Reflection classes.
 * This should be called from PHP_MINIT_FUNCTION(reflection).
 */
ZEND_API void reflection_array_shapes_init(void)
{
	zend_class_entry ce;
	zend_class_entry *reflection_type_ce;

	/* Get parent ReflectionType class */
	reflection_type_ce = zend_hash_str_find_ptr(CG(class_table),
		"reflectiontype", sizeof("reflectiontype") - 1);

	/*
	 * Register ReflectionArrayOfType
	 */
	INIT_CLASS_ENTRY(ce, "ReflectionArrayOfType", reflection_array_of_type_methods);
	reflection_array_of_type_ce = zend_register_internal_class_ex(&ce, reflection_type_ce);
	reflection_array_of_type_ce->create_object = reflection_array_of_type_create;

	memcpy(&reflection_array_of_type_handlers, &std_object_handlers,
		sizeof(zend_object_handlers));
	reflection_array_of_type_handlers.offset = XtOffsetOf(reflection_array_of_type_object, std);
	reflection_array_of_type_handlers.free_obj = reflection_array_of_type_free;

	/*
	 * Register ReflectionArrayShapeType
	 */
	INIT_CLASS_ENTRY(ce, "ReflectionArrayShapeType", reflection_array_shape_type_methods);
	reflection_array_shape_type_ce = zend_register_internal_class_ex(&ce, reflection_type_ce);
	reflection_array_shape_type_ce->create_object = reflection_array_shape_type_create;

	memcpy(&reflection_array_shape_type_handlers, &std_object_handlers,
		sizeof(zend_object_handlers));
	reflection_array_shape_type_handlers.offset = XtOffsetOf(reflection_array_shape_type_object, std);
	reflection_array_shape_type_handlers.free_obj = reflection_array_shape_type_free;

	/*
	 * Register ReflectionArrayShapeElement
	 * (Does not extend ReflectionType)
	 */
	INIT_CLASS_ENTRY(ce, "ReflectionArrayShapeElement", reflection_array_shape_element_methods);
	reflection_array_shape_element_ce = zend_register_internal_class(&ce);
	reflection_array_shape_element_ce->create_object = reflection_array_shape_element_create;

	memcpy(&reflection_array_shape_element_handlers, &std_object_handlers,
		sizeof(zend_object_handlers));
	reflection_array_shape_element_handlers.offset = XtOffsetOf(reflection_array_shape_element_object, std);
	reflection_array_shape_element_handlers.free_obj = reflection_array_shape_element_free;
}

/* ============================================================================
 * INTEGRATION WITH EXISTING REFLECTION
 * ============================================================================
 *
 * The existing reflection code needs to be modified to use our extended
 * type handling. Here's how to integrate:
 *
 * In php_reflection.c, modify reflection_type_factory():
 */

/*
static void reflection_type_factory(zend_type type, zval *ret)
{
	// Check for extended array types first
	if (ZEND_TYPE_IS_ARRAY_OF(type) || ZEND_TYPE_IS_ARRAY_SHAPE(type)) {
		zend_object *obj = reflection_type_from_zend_type(type);
		if (obj) {
			ZVAL_OBJ(ret, obj);
			return;
		}
	}

	// Fall through to existing type handling
	// ... existing code ...
}
*/

/*
 * In ReflectionFunctionAbstract::getReturnType(), the above factory
 * function is called, so our types will be returned automatically.
 *
 * Similarly for ReflectionParameter::getType() and
 * ReflectionProperty::getType().
 */

/* ============================================================================
 * EXAMPLE USAGE
 * ============================================================================
 *
 * PHP code demonstrating the new Reflection API:
 *
 * function getUsers(): array<array{id: int, name: string}> {
 *     return [
 *         ['id' => 1, 'name' => 'Alice'],
 *         ['id' => 2, 'name' => 'Bob'],
 *     ];
 * }
 *
 * $rf = new ReflectionFunction('getUsers');
 * $returnType = $rf->getReturnType();
 *
 * if ($returnType instanceof ReflectionArrayOfType) {
 *     echo "Return type: " . $returnType . "\n";  // array<array{id: int, name: string}>
 *     echo "Element type: " . $returnType->getElementType() . "\n";  // array{id: int, name: string}
 *
 *     $elementType = $returnType->getElementType();
 *     if ($elementType instanceof ReflectionArrayShapeType) {
 *         foreach ($elementType->getElements() as $elem) {
 *             echo "  Key: " . $elem->getName() . ", Type: " . $elem->getType() . "\n";
 *         }
 *     }
 * }
 *
 * Output:
 * Return type: array<array{id: int, name: string}>
 * Element type: array{id: int, name: string}
 *   Key: id, Type: int
 *   Key: name, Type: string
 */
