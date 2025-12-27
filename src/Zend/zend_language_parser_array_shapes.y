/*
   +----------------------------------------------------------------------+
   | Zend Engine - Array Shapes Parser Grammar                           |
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

/*
 * ============================================================================
 * GRAMMAR ADDITIONS FOR ARRAY SHAPE RETURN TYPES
 * ============================================================================
 *
 * This file contains grammar rules to be added to zend_language_parser.y
 * to support the following syntaxes:
 *
 *   1. array<T>           - Homogeneous array (all elements of type T)
 *   2. array{key: T, ...} - Shaped array (specific keys with specific types)
 *
 * INTEGRATION INSTRUCTIONS:
 *
 * 1. Add the new token declarations in the %token section
 * 2. Add the new non-terminal declarations in the %type section
 * 3. Insert the grammar rules into the appropriate location
 * 4. Ensure proper AST node creation
 *
 * ============================================================================
 */

/* ============================================================================
 * TOKEN DECLARATIONS
 * ============================================================================
 * Add these to the %token section of zend_language_parser.y
 */

/*
 * Note: We reuse existing tokens where possible:
 *   - '<' and '>' are already tokens (T_IS_SMALLER_OR_EQUAL context)
 *   - '{' and '}' are already tokens
 *   - ':' is already a token
 *   - '?' is already a token (for nullable types)
 *
 * We add a new token for the angle bracket in type context to avoid
 * ambiguity with comparison operators.
 */

%token T_ARRAY_TYPE_START   /* '<' in type context after 'array' */
%token T_ARRAY_TYPE_END     /* '>' in type context */

/* ============================================================================
 * NON-TERMINAL DECLARATIONS
 * ============================================================================
 * Add these to the %type section of zend_language_parser.y
 */

%type <ast> type_expr_extended
%type <ast> array_of_type
%type <ast> array_shape_type
%type <ast> shape_element_list
%type <ast> shape_element
%type <ast> shape_key

/* ============================================================================
 * GRAMMAR RULES
 * ============================================================================
 * These rules extend the existing type_expr rules to support array shapes.
 *
 * The existing type grammar in PHP is roughly:
 *
 *   type_expr:
 *       type_without_static
 *     | T_STATIC
 *     ;
 *
 *   type_without_static:
 *       T_ARRAY
 *     | T_CALLABLE
 *     | name
 *     | ... (other built-in types)
 *     | '?' type_expr
 *     | type_expr '|' type_expr
 *     | type_expr '&' type_expr
 *     ;
 *
 * We extend this to add:
 *   - T_ARRAY '<' type_expr '>'           -> array_of_type
 *   - T_ARRAY '{' shape_element_list '}'  -> array_shape_type
 */

/* ----------------------------------------------------------------------------
 * Extended Type Expression
 * ----------------------------------------------------------------------------
 * Modifies the existing type grammar to include array shapes.
 * This should replace or extend the T_ARRAY production in type_without_static.
 */

type_expr_extended:
		type_without_static_base
			{ $$ = $1; }
	|	array_of_type
			{ $$ = $1; }
	|	array_shape_type
			{ $$ = $1; }
;

/* ----------------------------------------------------------------------------
 * Array-Of Type: array<T>
 * ----------------------------------------------------------------------------
 * Syntax: array<element_type>
 *
 * Examples:
 *   array<int>
 *   array<string>
 *   array<array<int>>        -- Nested
 *   array<array{id: int}>    -- Array of shapes
 *   array<?string>           -- Nullable element type
 *
 * AST Structure:
 *   ZEND_AST_TYPE_ARRAY_OF
 *     └── child[0]: element type (type_expr_extended)
 */

array_of_type:
		T_ARRAY T_ARRAY_TYPE_START type_expr_extended T_ARRAY_TYPE_END
			{
				$$ = zend_ast_create(ZEND_AST_TYPE_ARRAY_OF, $3);
				$$->attr = 0; /* No special attributes */
			}
;

/* ----------------------------------------------------------------------------
 * Array Shape Type: array{key: T, ...}
 * ----------------------------------------------------------------------------
 * Syntax: array{key1: type1, key2: type2, ...}
 *
 * Examples:
 *   array{id: int}
 *   array{id: int, name: string}
 *   array{id: int, name: ?string}        -- Nullable value
 *   array{id?: int}                       -- Optional key (future extension)
 *   array{0: string, 1: int}             -- Integer keys
 *   array{data: array<int>}              -- Nested array<T>
 *   array{user: array{id: int}}          -- Nested shape
 *
 * AST Structure:
 *   ZEND_AST_TYPE_ARRAY_SHAPE
 *     └── child[0]: ZEND_AST_SHAPE_ELEMENT_LIST
 *           ├── child[0]: ZEND_AST_SHAPE_ELEMENT (key1: type1)
 *           ├── child[1]: ZEND_AST_SHAPE_ELEMENT (key2: type2)
 *           └── ...
 */

array_shape_type:
		T_ARRAY '{' shape_element_list '}'
			{
				$$ = zend_ast_create(ZEND_AST_TYPE_ARRAY_SHAPE, $3);
				$$->attr = 0;
			}
	|	T_ARRAY '{' '}'
			{
				/* Empty shape: array{} - valid but unusual */
				zend_ast *empty_list = zend_ast_create_list(0, ZEND_AST_SHAPE_ELEMENT_LIST);
				$$ = zend_ast_create(ZEND_AST_TYPE_ARRAY_SHAPE, empty_list);
				$$->attr = 0;
			}
;

/* ----------------------------------------------------------------------------
 * Shape Element List
 * ----------------------------------------------------------------------------
 * Comma-separated list of key: type pairs.
 * Trailing comma is allowed for convenience.
 */

shape_element_list:
		shape_element
			{
				$$ = zend_ast_create_list(1, ZEND_AST_SHAPE_ELEMENT_LIST, $1);
			}
	|	shape_element_list ',' shape_element
			{
				$$ = zend_ast_list_add($1, $3);
			}
	|	shape_element_list ','
			{
				/* Allow trailing comma */
				$$ = $1;
			}
;

/* ----------------------------------------------------------------------------
 * Shape Element: key: type
 * ----------------------------------------------------------------------------
 * A single key-type pair in a shape definition.
 *
 * Key can be:
 *   - An identifier (treated as string key): id, name, user_id
 *   - A string literal: 'my-key', "another_key"
 *   - An integer literal: 0, 1, 42
 *
 * Type is any valid type expression (including nested arrays).
 *
 * Future extension: key?: type for optional keys
 *
 * AST Structure:
 *   ZEND_AST_SHAPE_ELEMENT
 *     ├── child[0]: key (string or number AST)
 *     └── child[1]: type (type_expr_extended)
 *     └── attr: flags (e.g., optional bit)
 */

shape_element:
		shape_key ':' type_expr_extended
			{
				$$ = zend_ast_create(ZEND_AST_SHAPE_ELEMENT, $1, $3);
				$$->attr = 0; /* Required key */
			}
	|	shape_key '?' ':' type_expr_extended
			{
				/* Optional key syntax: key?: type */
				$$ = zend_ast_create(ZEND_AST_SHAPE_ELEMENT, $1, $4);
				$$->attr = 1; /* Optional key flag */
			}
;

/* ----------------------------------------------------------------------------
 * Shape Key
 * ----------------------------------------------------------------------------
 * The key part of a shape element.
 *
 * Allowed:
 *   - Identifier: id, name, userId (becomes string key)
 *   - String literal: 'key', "key" (explicit string key)
 *   - Integer literal: 0, 1, 42 (integer key)
 *
 * Note: We use T_STRING for identifiers, which covers most cases.
 * Reserved words need special handling (similar to class member names).
 */

shape_key:
		T_STRING
			{
				/* Identifier key: treat as string */
				$$ = zend_ast_create_zval_from_str(
					zend_string_copy(Z_STR_P(zend_ast_get_zval($1)))
				);
			}
	|	T_CONSTANT_ENCAPSED_STRING
			{
				/* String literal key */
				$$ = $1;
			}
	|	T_LNUMBER
			{
				/* Integer literal key */
				$$ = $1;
			}
	|	identifier
			{
				/* Allows reserved words as keys (like 'class', 'function') */
				$$ = zend_ast_create_zval_from_str(
					zend_string_copy(Z_STR_P(zend_ast_get_zval($1)))
				);
			}
;

/* ============================================================================
 * INTEGRATION WITH EXISTING TYPE GRAMMAR
 * ============================================================================
 *
 * The existing type_without_static rule needs to be modified to include
 * our extended array types. There are two approaches:
 *
 * APPROACH 1: Modify existing T_ARRAY production
 * ------------------------------------------------
 * Change the existing T_ARRAY rule to handle our extensions:
 *
 * type_without_static:
 *       T_ARRAY
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_ARRAY); }
 *     | T_ARRAY T_ARRAY_TYPE_START type_expr T_ARRAY_TYPE_END
 *           { $$ = zend_ast_create(ZEND_AST_TYPE_ARRAY_OF, $3); }
 *     | T_ARRAY '{' shape_element_list '}'
 *           { $$ = zend_ast_create(ZEND_AST_TYPE_ARRAY_SHAPE, $3); }
 *     | ... (rest of existing rules)
 *     ;
 *
 * APPROACH 2: Factor out type_without_static_base
 * ------------------------------------------------
 * Create a base rule without array, then combine:
 *
 * type_without_static_base:
 *       T_CALLABLE
 *     | name
 *     | T_STRING_CAST  // string
 *     | ... (all non-array types)
 *     ;
 *
 * type_without_static:
 *       type_without_static_base
 *     | T_ARRAY
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_ARRAY); }
 *     | array_of_type
 *     | array_shape_type
 *     ;
 *
 * We recommend Approach 1 for cleaner integration.
 */

/* ============================================================================
 * PRECEDENCE AND ASSOCIATIVITY
 * ============================================================================
 *
 * The new type syntax should not introduce ambiguity because:
 *
 * 1. array<T> is only valid in type context, not expression context
 * 2. array{k: T} is only valid in type context
 * 3. The lexer enters a special state after seeing 'array' in type context
 *
 * However, to be safe, we can add precedence rules:
 */

%nonassoc T_ARRAY_TYPE_START T_ARRAY_TYPE_END

/* ============================================================================
 * NULLABLE TYPE SUPPORT
 * ============================================================================
 *
 * Nullable types work naturally with our extensions:
 *
 *   ?array<int>           -- Nullable array of ints
 *   array<?int>           -- Array of nullable ints
 *   ?array{id: int}       -- Nullable shaped array
 *   array{id: ?int}       -- Shape with nullable int
 *
 * The existing nullable_type rule handles the outer ?:
 *
 * nullable_type:
 *       '?' type_expr  { $$ = ...; }
 *     ;
 *
 * Inner nullable types are handled by type_expr allowing '?' prefix.
 */

/* ============================================================================
 * UNION TYPE SUPPORT
 * ============================================================================
 *
 * Union types should work with array shapes:
 *
 *   array<int>|null          -- Union of array<int> and null
 *   array{id: int}|false     -- Union of shape and false
 *   array<int|string>        -- Array of (int or string)
 *   array{data: int|string}  -- Shape with union value type
 *
 * The existing union_type rule handles outer unions:
 *
 * union_type:
 *       type_expr '|' type_expr  { $$ = ...; }
 *     ;
 *
 * Inner unions are handled by type_expr allowing '|' separator.
 */

/* ============================================================================
 * ERROR RECOVERY
 * ============================================================================
 *
 * Add error productions for better error messages:
 */

array_of_type_error:
		T_ARRAY T_ARRAY_TYPE_START error T_ARRAY_TYPE_END
			{
				zend_throw_exception(NULL,
					"Syntax error in array<T> type declaration", 0);
				YYERROR;
			}
	|	T_ARRAY T_ARRAY_TYPE_START type_expr_extended error
			{
				zend_throw_exception(NULL,
					"Expected '>' to close array<T> type", 0);
				YYERROR;
			}
;

array_shape_type_error:
		T_ARRAY '{' error '}'
			{
				zend_throw_exception(NULL,
					"Syntax error in array{...} shape declaration", 0);
				YYERROR;
			}
	|	T_ARRAY '{' shape_element_list error
			{
				zend_throw_exception(NULL,
					"Expected '}' to close array shape", 0);
				YYERROR;
			}
;

shape_element_error:
		shape_key error
			{
				zend_throw_exception(NULL,
					"Expected ':' after shape key", 0);
				YYERROR;
			}
	|	shape_key ':' error
			{
				zend_throw_exception(NULL,
					"Invalid type in shape element", 0);
				YYERROR;
			}
;

/* ============================================================================
 * COMPLETE MODIFIED RULES
 * ============================================================================
 *
 * Below is the complete set of modified/added rules for reference.
 * These should be integrated into the main zend_language_parser.y file.
 */

/*
 * Modified type_without_static to include array shape extensions:
 *
 * type_without_static:
 *       T_ARRAY
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_ARRAY); }
 *     | T_ARRAY T_ARRAY_TYPE_START type_expr T_ARRAY_TYPE_END
 *           { $$ = zend_ast_create(ZEND_AST_TYPE_ARRAY_OF, $3); }
 *     | T_ARRAY '{' shape_element_list_opt '}'
 *           { $$ = zend_ast_create(ZEND_AST_TYPE_ARRAY_SHAPE, $3); }
 *     | T_CALLABLE
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_CALLABLE); }
 *     | T_ITERABLE
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_ITERABLE); }
 *     | T_BOOL
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, _IS_BOOL); }
 *     | T_INT
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_LONG); }
 *     | T_FLOAT
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_DOUBLE); }
 *     | T_STRING_CAST  // Note: This might be T_STRING_TYPE
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_STRING); }
 *     | T_VOID
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_VOID); }
 *     | T_NEVER
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_NEVER); }
 *     | T_NULL
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_NULL); }
 *     | T_TRUE
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_TRUE); }
 *     | T_FALSE
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_FALSE); }
 *     | T_MIXED
 *           { $$ = zend_ast_create_ex(ZEND_AST_TYPE, IS_MIXED); }
 *     | name
 *           { $$ = $1; }
 *     ;
 *
 * shape_element_list_opt:
 *       // empty
 *           { $$ = zend_ast_create_list(0, ZEND_AST_SHAPE_ELEMENT_LIST); }
 *     | shape_element_list
 *           { $$ = $1; }
 *     ;
 */

%%

/* ============================================================================
 * HELPER FUNCTIONS FOR AST CREATION
 * ============================================================================
 * These functions are called from the grammar actions and should be
 * implemented in zend_language_parser.c or a separate file.
 */

/*
 * Create an AST node for array<T> type.
 * Called from array_of_type production.
 */
static zend_ast* zend_ast_create_array_of_type(zend_ast *element_type)
{
	zend_ast *node = zend_ast_create(ZEND_AST_TYPE_ARRAY_OF, element_type);
	node->attr = 0;
	return node;
}

/*
 * Create an AST node for array{...} shape type.
 * Called from array_shape_type production.
 */
static zend_ast* zend_ast_create_array_shape_type(zend_ast *element_list)
{
	zend_ast *node = zend_ast_create(ZEND_AST_TYPE_ARRAY_SHAPE, element_list);
	node->attr = 0;
	return node;
}

/*
 * Create an AST node for a single shape element.
 * Called from shape_element production.
 */
static zend_ast* zend_ast_create_shape_element(zend_ast *key, zend_ast *type, bool optional)
{
	zend_ast *node = zend_ast_create(ZEND_AST_SHAPE_ELEMENT, key, type);
	node->attr = optional ? 1 : 0;
	return node;
}
