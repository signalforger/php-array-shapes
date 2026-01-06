# Implementation Details

This document describes the C implementation of typed arrays and array shapes in the Zend engine.

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Type System Extensions](#type-system-extensions)
  - [zend_type Modifications](#zend_type-modifications)
  - [Array Shape Structure](#array-shape-structure)
  - [Typed Array Structure](#typed-array-structure)
- [Lexer and Parser](#lexer-and-parser)
  - [New Tokens](#new-tokens)
  - [Grammar Rules](#grammar-rules)
- [Compilation](#compilation)
  - [Shape Declarations](#shape-declarations)
  - [Type Resolution](#type-resolution)
  - [Shape Inheritance](#shape-inheritance)
- [Runtime Validation](#runtime-validation)
  - [Validation Entry Points](#validation-entry-points)
  - [Typed Array Validation](#typed-array-validation)
  - [Array Shape Validation](#array-shape-validation)
  - [Error Message Generation](#error-message-generation)
- [Variance Checking](#variance-checking)
  - [Covariance for Return Types](#covariance-for-return-types)
  - [Contravariance for Parameters](#contravariance-for-parameters)
- [Performance Optimizations](#performance-optimizations)
  - [Type Caching](#type-caching)
  - [Class Entry Caching](#class-entry-caching)
  - [SIMD Validation](#simd-validation)
  - [String Interning](#string-interning)
- [Reflection API](#reflection-api)
- [Key Files](#key-files)

---

## Architecture Overview

The implementation touches several core Zend engine components:

```
┌─────────────────────────────────────────────────────────────┐
│                        PHP Source                            │
│              array<int>  /  array{id: int}                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Lexer (zend_language_scanner.l)          │
│          Tokenizes: T_ARRAY, T_SHAPE, <, >, {, }, etc.      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Parser (zend_language_parser.y)          │
│          Builds AST nodes for typed arrays and shapes        │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Compiler (zend_compile.c)                │
│    Resolves types, flattens shape inheritance, stores in    │
│    zend_type structures on functions/properties             │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Executor (zend_execute.c)                │
│         Runtime validation of typed arrays and shapes        │
└─────────────────────────────────────────────────────────────┘
```

---

## Type System Extensions

### zend_type Modifications

The `zend_type` structure in `Zend/zend_types.h` is extended to support typed arrays and shapes:

```c
/* Type flags in zend_type.type_mask */
#define ZEND_TYPE_HAS_TYPED_ARRAY    (1 << 24)  /* array<T> or array<K,V> */
#define ZEND_TYPE_HAS_ARRAY_SHAPE    (1 << 25)  /* array{key: type} */

/* Macros for type detection */
#define ZEND_TYPE_HAS_TYPED_ARRAY(t)  ((t).type_mask & ZEND_TYPE_HAS_TYPED_ARRAY)
#define ZEND_TYPE_HAS_ARRAY_SHAPE(t)  ((t).type_mask & ZEND_TYPE_HAS_ARRAY_SHAPE)

/* Access the typed array or shape data */
#define ZEND_TYPED_ARRAY(t)    ((zend_typed_array*)((t).ptr))
#define ZEND_ARRAY_SHAPE(t)    ((zend_array_shape*)((t).ptr))
```

The `zend_type` union uses the `ptr` field to store a pointer to either `zend_typed_array` or `zend_array_shape` when the corresponding flag is set.

### Array Shape Structure

```c
/* Single element in an array shape */
typedef struct _zend_array_shape_element {
    zend_string *key;           /* The key name (interned) */
    zend_type type;             /* The element's type */
    bool is_optional;           /* Key may be absent (key?) */
} zend_array_shape_element;

/* Complete array shape definition */
typedef struct _zend_array_shape {
    uint32_t num_elements;      /* Total number of elements */
    uint32_t num_required;      /* Number of required (non-optional) elements */
    bool is_closed;             /* Closed shape (!)? Rejects extra keys */
    HashTable *expected_keys;   /* Pre-built hash for O(1) key lookup (closed shapes) */
    zend_array_shape_element elements[]; /* Flexible array member */
} zend_array_shape;
```

Memory layout for `array{id: int, name: string, email?: string}`:

```
┌──────────────────────────────────────────────────┐
│ zend_array_shape                                 │
├──────────────────────────────────────────────────┤
│ num_elements: 3                                  │
│ num_required: 2                                  │
│ is_closed: false                                 │
│ expected_keys: NULL (only used for closed)       │
├──────────────────────────────────────────────────┤
│ elements[0]:                                     │
│   key: "id" (interned)                          │
│   type: IS_LONG                                 │
│   is_optional: false                            │
├──────────────────────────────────────────────────┤
│ elements[1]:                                     │
│   key: "name" (interned)                        │
│   type: IS_STRING                               │
│   is_optional: false                            │
├──────────────────────────────────────────────────┤
│ elements[2]:                                     │
│   key: "email" (interned)                       │
│   type: IS_STRING                               │
│   is_optional: true                             │
└──────────────────────────────────────────────────┘
```

### Typed Array Structure

```c
/* Typed array definition */
typedef struct _zend_typed_array {
    zend_type element_type;     /* Type of array elements */
    zend_type key_type;         /* Type of keys (for array<K,V>) */
    bool has_key_type;          /* Whether key type is specified */
} zend_typed_array;
```

For `array<string, int>`:

```
┌──────────────────────────────────────────────────┐
│ zend_typed_array                                 │
├──────────────────────────────────────────────────┤
│ element_type: IS_LONG                           │
│ key_type: IS_STRING                             │
│ has_key_type: true                              │
└──────────────────────────────────────────────────┘
```

---

## Lexer and Parser

### New Tokens

Added to `Zend/zend_language_scanner.l`:

```c
/* Keywords */
"shape"      { RETURN_TOKEN(T_SHAPE); }

/* Operators (context-sensitive) */
/* < and > are reused from comparison operators */
/* { and } are reused from block delimiters */
/* ! is reused for closed shape suffix */
```

The lexer uses context to distinguish between:
- `array<int>` (typed array) vs `$a < $b` (comparison)
- `array{id: int}` (shape) vs `{ $code; }` (block)

### Grammar Rules

Added to `Zend/zend_language_parser.y`:

```yacc
/* Typed array: array<T> or array<K, V> */
typed_array_type:
    T_ARRAY '<' type_expr '>'
        { $$ = zend_compile_typed_array($3, NULL); }
  | T_ARRAY '<' type_expr ',' type_expr '>'
        { $$ = zend_compile_typed_array($5, $3); }
;

/* Array shape: array{key: type, ...} */
array_shape_type:
    T_ARRAY '{' shape_element_list '}'
        { $$ = zend_compile_array_shape($3, false); }
  | T_ARRAY '{' shape_element_list '}' '!'
        { $$ = zend_compile_array_shape($3, true); }
;

/* Shape elements */
shape_element_list:
    shape_element
        { $$ = zend_ast_create_list(1, ZEND_AST_SHAPE_ELEM_LIST, $1); }
  | shape_element_list ',' shape_element
        { $$ = zend_ast_list_add($1, $3); }
;

shape_element:
    T_STRING ':' type_expr
        { $$ = zend_ast_create(ZEND_AST_SHAPE_ELEM, $1, $3, false); }
  | T_STRING '?' ':' type_expr
        { $$ = zend_ast_create(ZEND_AST_SHAPE_ELEM, $1, $4, true); }
;

/* Shape type alias declaration */
shape_declaration:
    T_SHAPE T_STRING '=' array_shape_type ';'
        { $$ = zend_compile_shape_declaration($2, $4, NULL); }
  | T_SHAPE T_STRING T_EXTENDS class_name '=' array_shape_type ';'
        { $$ = zend_compile_shape_declaration($2, $6, $4); }
;
```

---

## Compilation

### Shape Declarations

When compiling `shape User = array{id: int, name: string}`:

```c
void zend_compile_shape_declaration(
    zend_string *name,
    zend_ast *shape_ast,
    zend_string *parent_name
) {
    /* 1. Check if shape already exists */
    if (zend_hash_exists(CG(shape_table), name)) {
        zend_error(E_COMPILE_ERROR, "Cannot redeclare shape %s", ZSTR_VAL(name));
    }

    /* 2. Resolve parent shape if extending */
    zend_array_shape *parent = NULL;
    if (parent_name) {
        parent = zend_lookup_shape(parent_name);
        if (!parent) {
            zend_error(E_COMPILE_ERROR, "Shape %s not found", ZSTR_VAL(parent_name));
        }
    }

    /* 3. Compile the shape definition */
    zend_array_shape *shape = zend_compile_array_shape_from_ast(shape_ast);

    /* 4. Flatten inheritance if parent exists */
    if (parent) {
        shape = zend_flatten_shape_inheritance(shape, parent);
    }

    /* 5. Register in global shape table */
    zend_hash_add_ptr(CG(shape_table), name, shape);
}
```

### Type Resolution

During compilation, types are resolved from AST to `zend_type`:

```c
zend_type zend_compile_type(zend_ast *ast) {
    zend_type type = ZEND_TYPE_INIT_NONE(0);

    switch (ast->kind) {
        case ZEND_AST_TYPE:
            /* Simple types: int, string, etc. */
            type.type_mask = zend_type_from_ast(ast);
            break;

        case ZEND_AST_TYPED_ARRAY:
            /* array<T> or array<K, V> */
            type = zend_compile_typed_array_type(ast);
            type.type_mask |= MAY_BE_ARRAY | ZEND_TYPE_HAS_TYPED_ARRAY;
            break;

        case ZEND_AST_ARRAY_SHAPE:
            /* array{key: type, ...} */
            type = zend_compile_array_shape_type(ast);
            type.type_mask |= MAY_BE_ARRAY | ZEND_TYPE_HAS_ARRAY_SHAPE;
            break;

        case ZEND_AST_SHAPE_NAME:
            /* Named shape: UserShape */
            type = zend_resolve_shape_alias(ast);
            break;
    }

    return type;
}
```

### Shape Inheritance

Inheritance is resolved at compile time by flattening parent fields:

```c
zend_array_shape *zend_flatten_shape_inheritance(
    zend_array_shape *child,
    zend_array_shape *parent
) {
    /* Calculate total elements */
    uint32_t total = parent->num_elements + child->num_elements;

    /* Allocate new shape */
    zend_array_shape *result = zend_arena_alloc(
        &CG(arena),
        sizeof(zend_array_shape) + total * sizeof(zend_array_shape_element)
    );

    /* Copy parent elements first */
    memcpy(result->elements, parent->elements,
           parent->num_elements * sizeof(zend_array_shape_element));

    /* Check child overrides and add new fields */
    uint32_t out_idx = parent->num_elements;
    for (uint32_t i = 0; i < child->num_elements; i++) {
        zend_array_shape_element *child_elem = &child->elements[i];

        /* Look for override in parent */
        int parent_idx = zend_shape_find_element(parent, child_elem->key);
        if (parent_idx >= 0) {
            /* Override: validate covariance */
            zend_array_shape_element *parent_elem = &parent->elements[parent_idx];
            if (!zend_type_is_subtype(child_elem->type, parent_elem->type)) {
                zend_error(E_COMPILE_ERROR,
                    "Shape element %s type must be subtype of parent",
                    ZSTR_VAL(child_elem->key));
            }
            /* Replace parent element */
            result->elements[parent_idx] = *child_elem;
        } else {
            /* New field */
            result->elements[out_idx++] = *child_elem;
        }
    }

    result->num_elements = out_idx;
    result->num_required = zend_count_required_elements(result);
    result->is_closed = child->is_closed;

    return result;
}
```

---

## Runtime Validation

### Validation Entry Points

Validation occurs at several points in `Zend/zend_execute.c`:

```c
/* Function argument validation */
static zend_always_inline void zend_verify_arg_type(
    zend_function *zf,
    uint32_t arg_num,
    zval *arg
) {
    zend_arg_info *arg_info = &zf->common.arg_info[arg_num - 1];
    zend_type type = arg_info->type;

    if (ZEND_TYPE_HAS_TYPED_ARRAY(type)) {
        zend_verify_typed_array(arg, type, zf, arg_num, /* is_return */ false);
    } else if (ZEND_TYPE_HAS_ARRAY_SHAPE(type)) {
        zend_verify_array_shape(arg, type, zf, arg_num, /* is_return */ false);
    }
}

/* Return value validation */
static zend_always_inline void zend_verify_return_type(
    zend_function *zf,
    zval *retval
) {
    zend_type type = zf->common.return_type;

    if (ZEND_TYPE_HAS_TYPED_ARRAY(type)) {
        zend_verify_typed_array(retval, type, zf, 0, /* is_return */ true);
    } else if (ZEND_TYPE_HAS_ARRAY_SHAPE(type)) {
        zend_verify_array_shape(retval, type, zf, 0, /* is_return */ true);
    }
}

/* Property assignment validation */
static void zend_verify_property_type(
    zend_property_info *prop,
    zval *value
) {
    zend_type type = prop->type;

    if (ZEND_TYPE_HAS_TYPED_ARRAY(type)) {
        zend_verify_typed_array_property(value, type, prop);
    } else if (ZEND_TYPE_HAS_ARRAY_SHAPE(type)) {
        zend_verify_array_shape_property(value, type, prop);
    }
}
```

### Typed Array Validation

```c
static bool zend_verify_typed_array(
    zval *arr,
    zend_type expected_type,
    zend_function *zf,
    uint32_t arg_num,
    bool is_return
) {
    if (Z_TYPE_P(arr) != IS_ARRAY) {
        return false;  /* Not an array */
    }

    HashTable *ht = Z_ARRVAL_P(arr);
    zend_typed_array *typed = ZEND_TYPED_ARRAY(expected_type);

    /* Check cached validation status */
    if (HT_ELEM_TYPE_IS_VALID(ht, typed->element_type)) {
        return true;  /* Cache hit - already validated */
    }

    /* Validate key type if specified */
    if (typed->has_key_type) {
        if (!zend_verify_array_keys(ht, typed->key_type)) {
            zend_throw_typed_array_key_error(zf, arg_num, is_return, typed);
            return false;
        }
    }

    /* Validate each element */
    zval *val;
    zend_ulong idx;
    zend_string *key;

    ZEND_HASH_FOREACH_KEY_VAL(ht, idx, key, val) {
        if (!zend_check_type(typed->element_type, val)) {
            zend_throw_typed_array_error(
                zf, arg_num, is_return, typed,
                key ? key : NULL, idx, val
            );
            return false;
        }
    } ZEND_HASH_FOREACH_END();

    /* Cache validation result */
    HT_SET_ELEM_TYPE(ht, typed->element_type);

    return true;
}
```

### Array Shape Validation

```c
static bool zend_verify_array_shape(
    zval *arr,
    zend_type expected_type,
    zend_function *zf,
    uint32_t arg_num,
    bool is_return
) {
    if (Z_TYPE_P(arr) != IS_ARRAY) {
        return false;
    }

    HashTable *ht = Z_ARRVAL_P(arr);
    zend_array_shape *shape = ZEND_ARRAY_SHAPE(expected_type);

    /* Track which required keys are present */
    uint32_t found_required = 0;

    /* Validate each expected element */
    for (uint32_t i = 0; i < shape->num_elements; i++) {
        zend_array_shape_element *elem = &shape->elements[i];
        zval *val = zend_hash_find(ht, elem->key);

        if (val == NULL) {
            if (!elem->is_optional) {
                /* Missing required key */
                zend_throw_shape_missing_key_error(
                    zf, arg_num, is_return, shape, elem->key
                );
                return false;
            }
            continue;  /* Optional key absent - OK */
        }

        if (!elem->is_optional) {
            found_required++;
        }

        /* Validate element type */
        if (!zend_check_type(elem->type, val)) {
            zend_throw_shape_type_error(
                zf, arg_num, is_return, shape, elem->key, val
            );
            return false;
        }
    }

    /* Closed shape: check for extra keys */
    if (shape->is_closed) {
        if (zend_hash_num_elements(ht) > shape->num_elements) {
            /* Find the unexpected key */
            zend_string *key;
            ZEND_HASH_FOREACH_STR_KEY(ht, key) {
                if (!zend_hash_exists(shape->expected_keys, key)) {
                    zend_throw_shape_unexpected_key_error(
                        zf, arg_num, is_return, shape, key
                    );
                    return false;
                }
            } ZEND_HASH_FOREACH_END();
        }
    }

    return true;
}
```

### Error Message Generation

```c
static void zend_throw_typed_array_error(
    zend_function *zf,
    uint32_t arg_num,
    bool is_return,
    zend_typed_array *typed,
    zend_string *key,
    zend_ulong idx,
    zval *actual
) {
    zend_string *type_str = zend_typed_array_to_string(typed);
    const char *actual_type = zend_zval_type_name(actual);

    if (is_return) {
        zend_type_error(
            "%s(): Return value must be of type %s, "
            "array element at %s%s is %s",
            ZSTR_VAL(zf->common.function_name),
            ZSTR_VAL(type_str),
            key ? "key \"" : "index ",
            key ? ZSTR_VAL(key) : /* format idx */ ...,
            actual_type
        );
    } else {
        zend_type_error(
            "%s(): Argument #%d must be of type %s, "
            "array element at %s%s is %s",
            ZSTR_VAL(zf->common.function_name),
            arg_num,
            ZSTR_VAL(type_str),
            key ? "key \"" : "index ",
            key ? ZSTR_VAL(key) : /* format idx */ ...,
            actual_type
        );
    }

    zend_string_release(type_str);
}
```

---

## Variance Checking

Variance is enforced during class compilation in `Zend/zend_inheritance.c`.

### Covariance for Return Types

Child return types must be subtypes of parent (narrower):

```c
static inheritance_status zend_array_shape_covariant_check(
    zend_class_entry *fe_scope, const zend_type fe_type,
    zend_class_entry *proto_scope, const zend_type proto_type
) {
    /* Both must be array shapes */
    if (!ZEND_TYPE_HAS_ARRAY_SHAPE(fe_type) || !ZEND_TYPE_HAS_ARRAY_SHAPE(proto_type)) {
        /* Child has shape, parent doesn't: OK (more specific) */
        if (ZEND_TYPE_HAS_ARRAY_SHAPE(fe_type)) {
            return INHERITANCE_SUCCESS;
        }
        /* Parent has shape, child doesn't: ERROR (less specific) */
        if (ZEND_TYPE_HAS_ARRAY_SHAPE(proto_type)) {
            return INHERITANCE_ERROR;
        }
        return INHERITANCE_SUCCESS;
    }

    zend_array_shape *child = ZEND_ARRAY_SHAPE(fe_type);
    zend_array_shape *parent = ZEND_ARRAY_SHAPE(proto_type);

    /* Build hash table for O(1) child key lookup */
    HashTable child_keys;
    zend_hash_init(&child_keys, child->num_elements, NULL, NULL, 0);
    for (uint32_t i = 0; i < child->num_elements; i++) {
        zend_hash_add_ptr(&child_keys, child->elements[i].key, &child->elements[i]);
    }

    /* For covariance: child must have ALL parent's required fields */
    for (uint32_t i = 0; i < parent->num_elements; i++) {
        zend_array_shape_element *parent_elem = &parent->elements[i];

        /* Find matching child element */
        zend_array_shape_element *child_elem =
            zend_hash_find_ptr(&child_keys, parent_elem->key);

        if (!child_elem) {
            if (!parent_elem->is_optional) {
                /* Missing required parent field */
                zend_hash_destroy(&child_keys);
                return INHERITANCE_ERROR;
            }
            continue;
        }

        /* Child element type must be subtype of parent element type */
        inheritance_status elem_status = zend_perform_covariant_type_check(
            fe_scope, child_elem->type,
            proto_scope, parent_elem->type
        );

        if (elem_status == INHERITANCE_ERROR) {
            zend_hash_destroy(&child_keys);
            return INHERITANCE_ERROR;
        }
    }

    zend_hash_destroy(&child_keys);
    return INHERITANCE_SUCCESS;
}
```

### Contravariance for Parameters

Child parameters must accept supertypes of parent (wider):

```c
/* For parameters, the check is inverted:
 * Parent shape fields are checked against child.
 * Child can have FEWER required fields (accepts more).
 * Child cannot have MORE required fields (accepts less).
 */
static inheritance_status zend_array_shape_contravariant_check(
    zend_class_entry *fe_scope, const zend_type fe_type,
    zend_class_entry *proto_scope, const zend_type proto_type
) {
    zend_array_shape *child = ZEND_ARRAY_SHAPE(fe_type);
    zend_array_shape *parent = ZEND_ARRAY_SHAPE(proto_type);

    /* Child requires fewer fields than parent: OK (wider) */
    /* Child requires more fields than parent: ERROR (narrower) */

    for (uint32_t i = 0; i < child->num_elements; i++) {
        zend_array_shape_element *child_elem = &child->elements[i];

        if (child_elem->is_optional) {
            continue;  /* Optional in child - no constraint */
        }

        /* Required in child - must also be required in parent */
        zend_array_shape_element *parent_elem =
            zend_shape_find_element(parent, child_elem->key);

        if (!parent_elem || parent_elem->is_optional) {
            /* Child requires field that parent doesn't - narrows type */
            return INHERITANCE_ERROR;
        }
    }

    return INHERITANCE_SUCCESS;
}
```

---

## Performance Optimizations

### Type Caching

The `HashTable` structure has fields for caching validated types:

```c
/* In Zend/zend_hash.h */
typedef struct _HashTable {
    /* ... existing fields ... */
    union {
        struct {
            /* ... */
            uint8_t nValidatedElemType;  /* Cached element type */
            uint8_t nValidatedKeyType;   /* Cached key type bitmask */
        } v;
    } u;
} HashTable;

/* Macros for cache access */
#define HT_ELEM_TYPE_IS_VALID(ht, expected_type) \
    ((ht)->u.v.nValidatedElemType == (expected_type))

#define HT_SET_ELEM_TYPE(ht, type) \
    ((ht)->u.v.nValidatedElemType = (type))

#define HT_INVALIDATE_ELEM_TYPE(ht) \
    ((ht)->u.v.nValidatedElemType = IS_UNDEF)
```

Cache invalidation in `Zend/zend_hash.c`:

```c
ZEND_API zval *zend_hash_add(HashTable *ht, zend_string *key, zval *val) {
    HT_INVALIDATE_ELEM_TYPE(ht);  /* Invalidate on mutation */
    HT_INVALIDATE_KEY_TYPE(ht);
    /* ... rest of implementation ... */
}
```

### Class Entry Caching

Thread-local caching for class lookups:

```c
/* In Zend/zend_execute.c */
ZEND_TLS zend_string *cached_class_name = NULL;
ZEND_TLS zend_class_entry *cached_class_entry = NULL;

static zend_always_inline zend_class_entry *zend_lookup_class_cached(
    zend_string *name
) {
    if (cached_class_name && zend_string_equals(name, cached_class_name)) {
        return cached_class_entry;  /* Cache hit */
    }

    zend_class_entry *ce = zend_lookup_class(name);

    /* Update cache */
    if (cached_class_name) {
        zend_string_release(cached_class_name);
    }
    cached_class_name = zend_string_copy(name);
    cached_class_entry = ce;

    return ce;
}
```

### SIMD Validation

For large arrays of primitive types, AVX2 instructions validate 8 elements at once:

```c
#ifdef __AVX2__
static bool zend_verify_int_array_simd(HashTable *ht) {
    if (!HT_IS_PACKED(ht) || zend_hash_num_elements(ht) < 16) {
        return zend_verify_int_array_scalar(ht);  /* Fall back */
    }

    zval *data = ht->arPacked;
    uint32_t count = zend_hash_num_elements(ht);

    /* Process 8 elements at a time */
    __m256i expected_type = _mm256_set1_epi32(IS_LONG);

    for (uint32_t i = 0; i + 7 < count; i += 8) {
        /* Gather type bytes from 8 consecutive zvals */
        __m256i types = _mm256_i32gather_epi32(
            (const int*)&data[i].u1.type_info,
            _mm256_setr_epi32(0, 4, 8, 12, 16, 20, 24, 28),
            4
        );

        /* Compare all 8 types at once */
        __m256i cmp = _mm256_cmpeq_epi32(types, expected_type);
        if (_mm256_movemask_epi8(cmp) != 0xFFFFFFFF) {
            /* At least one mismatch - fall back to scalar for error */
            return zend_verify_int_array_scalar(ht);
        }
    }

    /* Handle remainder with scalar loop */
    for (uint32_t i = (count / 8) * 8; i < count; i++) {
        if (Z_TYPE(data[i]) != IS_LONG) {
            return false;
        }
    }

    return true;
}
#endif
```

### String Interning

Shape keys use PHP's string interning for memory efficiency:

```c
static void zend_persist_array_shape(zend_array_shape *shape) {
    for (uint32_t i = 0; i < shape->num_elements; i++) {
        zend_string *key = shape->elements[i].key;

        /* Try to use existing interned string */
        zend_string *interned = zend_new_interned_string(key);
        if (interned != key) {
            zend_string_release(key);
            shape->elements[i].key = interned;
        }
    }
}
```

---

## Reflection API

New reflection classes in `Zend/zend_builtin_functions.c`:

```c
/* ReflectionTypedArrayType */
ZEND_METHOD(ReflectionTypedArrayType, getElementType) {
    zend_typed_array *typed = /* get from internal pointer */;
    zend_string *type_str = zend_type_to_string(typed->element_type);
    RETURN_STR(type_str);
}

ZEND_METHOD(ReflectionTypedArrayType, getKeyType) {
    zend_typed_array *typed = /* get from internal pointer */;
    if (!typed->has_key_type) {
        RETURN_NULL();
    }
    zend_string *type_str = zend_type_to_string(typed->key_type);
    RETURN_STR(type_str);
}

/* ReflectionArrayShapeType */
ZEND_METHOD(ReflectionArrayShapeType, getElements) {
    zend_array_shape *shape = /* get from internal pointer */;
    array_init(return_value);

    for (uint32_t i = 0; i < shape->num_elements; i++) {
        zval elem_info;
        object_init_ex(&elem_info, reflection_shape_element_ce);
        /* Populate element info */
        add_next_index_zval(return_value, &elem_info);
    }
}

ZEND_METHOD(ReflectionArrayShapeType, isClosed) {
    zend_array_shape *shape = /* get from internal pointer */;
    RETURN_BOOL(shape->is_closed);
}

ZEND_METHOD(ReflectionArrayShapeType, getElementCount) {
    zend_array_shape *shape = /* get from internal pointer */;
    RETURN_LONG(shape->num_elements);
}

ZEND_METHOD(ReflectionArrayShapeType, getRequiredElementCount) {
    zend_array_shape *shape = /* get from internal pointer */;
    RETURN_LONG(shape->num_required);
}
```

---

## Key Files

| File | Purpose |
|------|---------|
| `Zend/zend_types.h` | Type definitions: `zend_array_shape`, `zend_typed_array` |
| `Zend/zend_compile.h` | Compilation structures and macros |
| `Zend/zend_compile.c` | Shape/type compilation, inheritance flattening |
| `Zend/zend_language_scanner.l` | Lexer: tokenizes new syntax |
| `Zend/zend_language_parser.y` | Parser: grammar rules for shapes/typed arrays |
| `Zend/zend_execute.c` | Runtime validation logic |
| `Zend/zend_inheritance.c` | Variance checking for class methods |
| `Zend/zend_hash.h` | HashTable with type caching fields |
| `Zend/zend_hash.c` | Cache invalidation on mutation |
| `Zend/zend_builtin_functions.c` | Reflection API implementation |
| `Zend/zend_vm_def.h` | VM opcode handlers |
| `ext/reflection/php_reflection.c` | Reflection class registration |

---

## Build and Test

```bash
# Clone with submodules
git clone --recursive https://github.com/signalforger/php-array-shapes.git
cd php-array-shapes/php-src

# Configure and build
./buildconf
./configure --enable-debug
make -j$(nproc)

# Run tests
make test TESTS=Zend/tests/type_declarations/array_shapes/
```

All 47 tests should pass.
