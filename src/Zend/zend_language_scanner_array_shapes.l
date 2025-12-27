/*
   +----------------------------------------------------------------------+
   | Zend Engine - Array Shapes Lexer Additions                          |
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
 * LEXER ADDITIONS FOR ARRAY SHAPE RETURN TYPES
 * ============================================================================
 *
 * This file contains lexer rules to be added to zend_language_scanner.l
 * to properly tokenize the new array shape syntax.
 *
 * KEY CHALLENGE:
 * In PHP, '<' and '>' are comparison operators:
 *   if ($a < $b) { ... }
 *   if ($a > $b) { ... }
 *
 * But in our new type syntax, they delimit type parameters:
 *   function foo(): array<int> { ... }
 *
 * We need to disambiguate based on context.
 *
 * SOLUTION:
 * Use lexer states (start conditions) to track when we're in a type context.
 * When we see 'array' followed by '<' in a type context, we enter a special
 * state that interprets '<' and '>' as type delimiters.
 *
 * ============================================================================
 */

/* ============================================================================
 * START CONDITION DECLARATIONS
 * ============================================================================
 * Add these to the %x or %s declarations in zend_language_scanner.l
 *
 * We use exclusive states (%x) for our type contexts.
 */

%{
/* Include our array shapes header */
#include "zend_compile_array_shapes.h"
%}

/* Exclusive start condition for type context */
%x ST_IN_TYPE_CONTEXT

/* Exclusive start condition for inside array<...> */
%x ST_IN_ARRAY_OF

/* Exclusive start condition for inside array{...} */
%x ST_IN_ARRAY_SHAPE

/* Nesting depth counter for balanced brackets */
%{
static int array_type_nesting_depth = 0;
static int array_shape_brace_depth = 0;
%}

/* ============================================================================
 * CONTEXT ENTRY RULES
 * ============================================================================
 * These rules detect when we enter a type context.
 *
 * Type contexts in PHP include:
 * - After ':' in function return type: function foo(): TYPE
 * - After ':' in parameter type hint with promotion: __construct(public TYPE $x)
 * - After variable name in typed property: public TYPE $prop
 * - After 'instanceof': $x instanceof TYPE
 * - After 'as' in catch: catch (TYPE $e)
 *
 * For simplicity, we focus on return types and parameter types.
 */

/* ============================================================================
 * MODIFIED RULES FOR TYPE CONTEXT
 * ============================================================================
 *
 * The existing lexer needs to set a state flag when entering type context.
 * We can do this by modifying existing rules or adding lookahead.
 *
 * Approach: Track when we see ':' in function definition context, then
 * subsequent 'array' tokens are in type context.
 */

<ST_IN_SCRIPTING>{
	/*
	 * When we see 'array' and we're in a type context (tracked by parser),
	 * check what follows to determine if it's array<...> or array{...}
	 */

	"array"/"<" {
		/*
		 * 'array' followed by '<' - this is array<T> syntax
		 * The '/' operator in flex is lookahead (doesn't consume)
		 *
		 * Note: The parser sets SCNG(in_type_context) when processing
		 * return types, parameter types, etc.
		 */
		if (SCNG(in_type_context)) {
			BEGIN(ST_IN_ARRAY_OF);
			array_type_nesting_depth = 0;
			RETURN_TOKEN(T_ARRAY);
		} else {
			/* Not in type context - regular array keyword */
			RETURN_TOKEN(T_ARRAY);
		}
	}

	"array"/"{" {
		/*
		 * 'array' followed by '{' - this is array{...} syntax
		 */
		if (SCNG(in_type_context)) {
			BEGIN(ST_IN_ARRAY_SHAPE);
			array_shape_brace_depth = 0;
			RETURN_TOKEN(T_ARRAY);
		} else {
			/* Not in type context - regular array keyword */
			RETURN_TOKEN(T_ARRAY);
		}
	}
}

/* ============================================================================
 * ARRAY-OF STATE RULES (ST_IN_ARRAY_OF)
 * ============================================================================
 * Handle tokens inside array<...> type declarations.
 */

<ST_IN_ARRAY_OF>{
	"<" {
		/*
		 * Opening angle bracket - could be start of nested array<T>
		 * or first '<' of array<...>
		 */
		array_type_nesting_depth++;
		RETURN_TOKEN(T_ARRAY_TYPE_START);
	}

	">" {
		/*
		 * Closing angle bracket
		 */
		array_type_nesting_depth--;
		if (array_type_nesting_depth <= 0) {
			/* We've closed all nested array<> types, return to normal */
			BEGIN(ST_IN_SCRIPTING);
		}
		RETURN_TOKEN(T_ARRAY_TYPE_END);
	}

	"array"/"<" {
		/*
		 * Nested array<T> inside array<...>
		 */
		RETURN_TOKEN(T_ARRAY);
	}

	"array"/"{" {
		/*
		 * array{...} shape inside array<...>
		 * Switch to shape context, but remember we need to return to array_of
		 */
		BEGIN(ST_IN_ARRAY_SHAPE);
		array_shape_brace_depth = 0;
		RETURN_TOKEN(T_ARRAY);
	}

	"int" {
		RETURN_TOKEN(T_INT);
	}

	"string" {
		RETURN_TOKEN(T_STRING_TYPE);
	}

	"float" {
		RETURN_TOKEN(T_FLOAT);
	}

	"bool" {
		RETURN_TOKEN(T_BOOL);
	}

	"null" {
		RETURN_TOKEN(T_NULL);
	}

	"mixed" {
		RETURN_TOKEN(T_MIXED);
	}

	"object" {
		RETURN_TOKEN(T_OBJECT);
	}

	"?" {
		/* Nullable type prefix */
		RETURN_TOKEN('?');
	}

	"|" {
		/* Union type separator */
		RETURN_TOKEN('|');
	}

	"&" {
		/* Intersection type separator */
		RETURN_TOKEN('&');
	}

	"(" {
		/* Parentheses for grouping in DNF types */
		RETURN_TOKEN('(');
	}

	")" {
		RETURN_TOKEN(')');
	}

	[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]* {
		/* Class name or other type identifier */
		zend_copy_value(zendlval, yytext, yyleng);
		RETURN_TOKEN(T_STRING);
	}

	"\\" {
		/* Namespace separator */
		RETURN_TOKEN(T_NS_SEPARATOR);
	}

	[ \t\n\r]+ {
		/* Skip whitespace */
	}

	. {
		/* Any other character - likely an error */
		zend_error(E_COMPILE_WARNING,
			"Unexpected character '%c' in array<T> type", yytext[0]);
		BEGIN(ST_IN_SCRIPTING);
		yyless(0); /* Push back the character */
	}
}

/* ============================================================================
 * ARRAY-SHAPE STATE RULES (ST_IN_ARRAY_SHAPE)
 * ============================================================================
 * Handle tokens inside array{...} shape declarations.
 */

<ST_IN_ARRAY_SHAPE>{
	"{" {
		/*
		 * Opening brace - track nesting for nested shapes
		 */
		array_shape_brace_depth++;
		RETURN_TOKEN('{');
	}

	"}" {
		/*
		 * Closing brace
		 */
		if (array_shape_brace_depth > 0) {
			array_shape_brace_depth--;
			RETURN_TOKEN('}');
		} else {
			/*
			 * We've closed the main shape, return to scripting
			 * or to array_of if we were nested
			 */
			if (array_type_nesting_depth > 0) {
				BEGIN(ST_IN_ARRAY_OF);
			} else {
				BEGIN(ST_IN_SCRIPTING);
			}
			RETURN_TOKEN('}');
		}
	}

	":" {
		/* Key-type separator */
		RETURN_TOKEN(':');
	}

	"," {
		/* Element separator */
		RETURN_TOKEN(',');
	}

	"?" {
		/* Optional key marker or nullable type */
		RETURN_TOKEN('?');
	}

	"array"/"<" {
		/*
		 * Nested array<T> inside shape
		 */
		BEGIN(ST_IN_ARRAY_OF);
		array_type_nesting_depth = 0;
		RETURN_TOKEN(T_ARRAY);
	}

	"array"/"{" {
		/*
		 * Nested shape inside shape
		 */
		array_shape_brace_depth++;
		RETURN_TOKEN(T_ARRAY);
	}

	"int" {
		RETURN_TOKEN(T_INT);
	}

	"string" {
		RETURN_TOKEN(T_STRING_TYPE);
	}

	"float" {
		RETURN_TOKEN(T_FLOAT);
	}

	"bool" {
		RETURN_TOKEN(T_BOOL);
	}

	"null" {
		RETURN_TOKEN(T_NULL);
	}

	"mixed" {
		RETURN_TOKEN(T_MIXED);
	}

	"object" {
		RETURN_TOKEN(T_OBJECT);
	}

	"|" {
		/* Union type separator */
		RETURN_TOKEN('|');
	}

	"&" {
		/* Intersection type separator */
		RETURN_TOKEN('&');
	}

	"(" {
		RETURN_TOKEN('(');
	}

	")" {
		RETURN_TOKEN(')');
	}

	[0-9]+ {
		/* Integer key */
		ZVAL_LONG(zendlval, ZEND_STRTOL(yytext, NULL, 10));
		RETURN_TOKEN(T_LNUMBER);
	}

	[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]* {
		/* Identifier (string key or type name) */
		zend_copy_value(zendlval, yytext, yyleng);
		RETURN_TOKEN(T_STRING);
	}

	"\\" {
		/* Namespace separator */
		RETURN_TOKEN(T_NS_SEPARATOR);
	}

	"'" {
		/*
		 * String literal key (single quoted)
		 * Enter string scanning mode
		 */
		BEGIN(ST_CONSTANT_STRING);
		RETURN_TOKEN('\'');
	}

	"\"" {
		/*
		 * String literal key (double quoted)
		 * Enter string scanning mode
		 */
		BEGIN(ST_DOUBLE_QUOTES);
		RETURN_TOKEN('"');
	}

	[ \t\n\r]+ {
		/* Skip whitespace */
	}

	. {
		/* Any other character - likely an error */
		zend_error(E_COMPILE_WARNING,
			"Unexpected character '%c' in array{...} shape", yytext[0]);
		BEGIN(ST_IN_SCRIPTING);
		yyless(0);
	}
}

/* ============================================================================
 * TYPE CONTEXT TRACKING
 * ============================================================================
 *
 * The parser needs to tell the lexer when we're in a type context.
 * We add a flag to the scanner globals and provide functions to set it.
 */

%{
/*
 * Add to SCNG (scanner globals) structure in zend_globals.h:
 *
 * zend_bool in_type_context;
 *
 * This flag is set by the parser when processing:
 * - Function return type (after ':' in function declaration)
 * - Parameter type (before parameter name)
 * - Property type (before property name)
 * - Catch type (after 'catch (')
 */

/* Helper function to enter type context - called by parser */
static void zend_enter_type_context(void)
{
	SCNG(in_type_context) = 1;
}

/* Helper function to leave type context - called by parser */
static void zend_leave_type_context(void)
{
	SCNG(in_type_context) = 0;
}
%}

/* ============================================================================
 * ALTERNATIVE APPROACH: PARSER-DRIVEN LEXER MODE
 * ============================================================================
 *
 * Instead of the lexer trying to detect type context, we can have the
 * parser explicitly tell the lexer to switch modes. This is cleaner
 * but requires parser modifications.
 *
 * The parser would call lex_mode_push(ARRAY_TYPE) when entering type
 * position and lex_mode_pop() when leaving.
 *
 * Example modifications to parser:
 *
 * return_type:
 *       ':' { zend_enter_type_context(); } type_expr { zend_leave_type_context(); }
 *           { $$ = $3; }
 *     ;
 *
 * This ensures the lexer is in the right mode when scanning type tokens.
 */

/* ============================================================================
 * TOKEN DEFINITIONS
 * ============================================================================
 *
 * Add these token definitions to zend_language_parser.y:
 *
 * %token T_ARRAY_TYPE_START  "'<' in type context"
 * %token T_ARRAY_TYPE_END    "'>'" in type context"
 *
 * These tokens are only produced by the lexer when in type context.
 * In expression context, '<' and '>' produce the regular comparison tokens.
 */

/* ============================================================================
 * SCANNER GLOBALS ADDITIONS
 * ============================================================================
 *
 * Add to struct _zend_scanner_globals in zend_globals.h:
 */

/*
typedef struct _zend_scanner_globals {
	// ... existing fields ...

	// Type context tracking for array shapes
	zend_bool in_type_context;

	// Nesting depth for array<...> types
	int array_type_depth;

	// Nesting depth for array{...} shapes
	int array_shape_depth;

	// Stack for tracking nested type contexts
	// (needed when array<array{...}> nesting occurs)
	int type_context_stack[16];
	int type_context_stack_ptr;

} zend_scanner_globals;
*/

/* ============================================================================
 * INITIALIZATION
 * ============================================================================
 *
 * Add to scanner initialization in zend_language_scanner.l:
 */

%{
static void zend_scanner_array_shapes_init(void)
{
	SCNG(in_type_context) = 0;
	array_type_nesting_depth = 0;
	array_shape_brace_depth = 0;
}
%}

/* ============================================================================
 * COMPLETE EXAMPLE: LEXER RULES FOR TYPE CONTEXT
 * ============================================================================
 *
 * Below is a complete example of how the lexer rules should be organized.
 * This integrates with the existing zend_language_scanner.l structure.
 */

/*

// In the definitions section, add the start conditions:
%x ST_IN_TYPE_CONTEXT
%x ST_IN_ARRAY_OF
%x ST_IN_ARRAY_SHAPE

// In the rules section:

<ST_IN_SCRIPTING>{

// Regular 'array' keyword (not followed by < or { in type context)
"array" {
	RETURN_TOKEN(T_ARRAY);
}

// ... existing rules ...

}

// Rules for returning from string literals back to shape context
<ST_CONSTANT_STRING>{
	"'" {
		// Check if we were in array shape context
		if (array_shape_brace_depth > 0 || SCNG(in_type_context)) {
			BEGIN(ST_IN_ARRAY_SHAPE);
		} else {
			BEGIN(ST_IN_SCRIPTING);
		}
		RETURN_TOKEN(T_CONSTANT_ENCAPSED_STRING);
	}
}

*/

/* ============================================================================
 * SIMPLER ALTERNATIVE: PARSER-ONLY DISAMBIGUATION
 * ============================================================================
 *
 * If modifying the lexer proves too complex, an alternative is to:
 *
 * 1. Always produce T_ARRAY for 'array' keyword
 * 2. Produce regular '<' and '>' for angle brackets
 * 3. Let the parser disambiguate based on context
 *
 * This requires the parser to accept '<' and '>' in type position:
 *
 * array_of_type:
 *       T_ARRAY '<' type_expr '>'
 *           { $$ = zend_ast_create(ZEND_AST_TYPE_ARRAY_OF, $3); }
 *     ;
 *
 * The parser's lookahead will disambiguate:
 * - In "function foo(): array<int>" the parser expects a type, so '<' is type delimiter
 * - In "$a < $b" the parser expects an operator, so '<' is comparison
 *
 * This works because PHP's grammar is LALR(1) and the contexts are
 * syntactically distinct.
 *
 * CAUTION: This approach may have edge cases. The lexer-based approach
 * is more robust but more complex.
 */

/* ============================================================================
 * RECOMMENDED IMPLEMENTATION
 * ============================================================================
 *
 * For PHP's actual implementation, we recommend:
 *
 * 1. Add a simple flag in scanner globals: in_type_context
 *
 * 2. Have the parser set this flag via mid-rule actions:
 *      return_type: ':' { SCNG(in_type_context) = 1; } type_expr
 *                       { SCNG(in_type_context) = 0; $$ = $3; }
 *
 * 3. In the lexer, check this flag when seeing '<' after 'array':
 *      "array"/"<" {
 *          if (SCNG(in_type_context)) {
 *              // Prepare for array<T> mode
 *          }
 *          RETURN_TOKEN(T_ARRAY);
 *      }
 *
 *      "<" {
 *          if (after_array_keyword && SCNG(in_type_context)) {
 *              RETURN_TOKEN(T_ARRAY_TYPE_START);
 *          }
 *          RETURN_TOKEN('<'); // Regular less-than
 *      }
 *
 * 4. Track nesting depth for balanced brackets
 *
 * This provides a clean separation between lexer and parser while
 * correctly handling the contextual nature of the new syntax.
 */
