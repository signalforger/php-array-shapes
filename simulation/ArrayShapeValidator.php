<?php

declare(strict_types=1);

/**
 * PHP Simulation of Array Shape Return Types RFC
 *
 * This simulates the runtime behavior that would be implemented in C.
 * Use this to understand and test the expected validation logic.
 */

namespace ArrayShapes;

class TypeValidationError extends \TypeError {}

/**
 * Validates that all elements of an array match the specified type.
 * Simulates: array<T> validation
 */
class ArrayOfValidator
{
    private string $elementType;
    private ?self $nestedValidator = null;
    private ?ArrayShapeValidator $shapeValidator = null;

    public function __construct(string $elementType)
    {
        $this->elementType = $elementType;

        // Handle nested array<T>
        if (preg_match('/^array<(.+)>$/', $elementType, $matches)) {
            $this->nestedValidator = new self($matches[1]);
        }
        // Handle nested array{...}
        elseif (preg_match('/^array\{(.+)\}$/', $elementType, $matches)) {
            $this->shapeValidator = ArrayShapeValidator::parse($elementType);
        }
    }

    public function validate(array $array, string $context = 'value'): void
    {
        foreach ($array as $index => $element) {
            if ($this->nestedValidator !== null) {
                // Nested array<T> - element must be array
                if (!is_array($element)) {
                    throw new TypeValidationError(
                        "$context must be of type array<{$this->elementType}>, " .
                        "array containing " . gettype($element) . " given"
                    );
                }
                $this->nestedValidator->validate($element, $context);
            }
            elseif ($this->shapeValidator !== null) {
                // Nested shape - element must be array
                if (!is_array($element)) {
                    throw new TypeValidationError(
                        "$context must be of type array<{$this->elementType}>, " .
                        "array containing " . gettype($element) . " given"
                    );
                }
                $this->shapeValidator->validate($element, $context);
            }
            else {
                // Simple type check
                if (!$this->checkType($element, $this->elementType)) {
                    throw new TypeValidationError(
                        "$context must be of type array<{$this->elementType}>, " .
                        "array containing " . gettype($element) . " given"
                    );
                }
            }
        }
    }

    private function checkType(mixed $value, string $type): bool
    {
        // Handle nullable
        if (str_starts_with($type, '?')) {
            if ($value === null) {
                return true;
            }
            $type = substr($type, 1);
        }

        return match ($type) {
            'int' => is_int($value),
            'string' => is_string($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'null' => $value === null,
            'array' => is_array($value),
            'object' => is_object($value),
            'mixed' => true,
            default => $value instanceof $type,
        };
    }
}

/**
 * Validates that an array matches a shape definition.
 * Simulates: array{key: T, ...} validation
 */
class ArrayShapeValidator
{
    /** @var array<string|int, ShapeElement> */
    private array $elements = [];

    public function addElement(string|int $key, string $type, bool $optional = false): void
    {
        $this->elements[$key] = new ShapeElement($key, $type, $optional);
    }

    public function validate(array $array, string $context = 'value'): void
    {
        foreach ($this->elements as $key => $element) {
            // Check if key exists
            if (!array_key_exists($key, $array)) {
                if (!$element->optional) {
                    throw new TypeValidationError(
                        "$context missing required key '$key'"
                    );
                }
                continue;
            }

            $value = $array[$key];

            // Handle nested array<T>
            if (preg_match('/^array<(.+)>$/', $element->type, $matches)) {
                if (!is_array($value)) {
                    throw new TypeValidationError(
                        "$context key '$key' must be of type {$element->type}, " .
                        gettype($value) . " given"
                    );
                }
                $validator = new ArrayOfValidator($matches[1]);
                $validator->validate($value, "$context key '$key'");
            }
            // Handle nested shape
            elseif (preg_match('/^array\{.+\}$/', $element->type)) {
                if (!is_array($value)) {
                    throw new TypeValidationError(
                        "$context key '$key' must be of type {$element->type}, " .
                        gettype($value) . " given"
                    );
                }
                $validator = self::parse($element->type);
                $validator->validate($value, "$context key '$key'");
            }
            // Simple type check
            else {
                if (!$this->checkType($value, $element->type)) {
                    throw new TypeValidationError(
                        "$context key '$key' must be of type {$element->type}, " .
                        gettype($value) . " given"
                    );
                }
            }
        }
    }

    private function checkType(mixed $value, string $type): bool
    {
        // Handle nullable
        if (str_starts_with($type, '?')) {
            if ($value === null) {
                return true;
            }
            $type = substr($type, 1);
        }

        return match ($type) {
            'int' => is_int($value),
            'string' => is_string($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'null' => $value === null,
            'array' => is_array($value),
            'object' => is_object($value),
            'mixed' => true,
            default => $value instanceof $type,
        };
    }

    /**
     * Parse a shape type string into a validator.
     * E.g., "array{id: int, name: string}" -> ArrayShapeValidator
     */
    public static function parse(string $shapeType): self
    {
        $validator = new self();

        // Extract content between { and }
        if (!preg_match('/^array\{(.+)\}$/', $shapeType, $matches)) {
            throw new \InvalidArgumentException("Invalid shape type: $shapeType");
        }

        $content = $matches[1];
        $elements = self::splitElements($content);

        foreach ($elements as $element) {
            $element = trim($element);
            if (empty($element)) continue;

            // Check for optional marker
            $optional = false;
            if (preg_match('/^([^:]+)\?\s*:\s*(.+)$/', $element, $m)) {
                $key = trim($m[1]);
                $type = trim($m[2]);
                $optional = true;
            } elseif (preg_match('/^([^:]+):\s*(.+)$/', $element, $m)) {
                $key = trim($m[1]);
                $type = trim($m[2]);
            } else {
                throw new \InvalidArgumentException("Invalid element: $element");
            }

            // Convert numeric string keys to integers
            if (is_numeric($key)) {
                $key = (int)$key;
            }

            $validator->addElement($key, $type, $optional);
        }

        return $validator;
    }

    /**
     * Split shape elements, respecting nested braces.
     */
    private static function splitElements(string $content): array
    {
        $elements = [];
        $current = '';
        $depth = 0;
        $inAngle = 0;

        for ($i = 0; $i < strlen($content); $i++) {
            $char = $content[$i];

            if ($char === '{') $depth++;
            elseif ($char === '}') $depth--;
            elseif ($char === '<') $inAngle++;
            elseif ($char === '>') $inAngle--;
            elseif ($char === ',' && $depth === 0 && $inAngle === 0) {
                $elements[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (!empty(trim($current))) {
            $elements[] = $current;
        }

        return $elements;
    }
}

class ShapeElement
{
    public function __construct(
        public readonly string|int $key,
        public readonly string $type,
        public readonly bool $optional = false
    ) {}
}

/**
 * Attribute to declare return type (simulation of native syntax)
 */
#[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD)]
class ReturnType
{
    public function __construct(
        public readonly string $type
    ) {}
}

/**
 * Validates return value against declared type.
 * Call this at the end of functions to simulate runtime validation.
 */
function validateReturn(mixed $value, string $type, string $functionName): mixed
{
    // Check for nullable
    $nullable = false;
    if (str_starts_with($type, '?')) {
        $nullable = true;
        $type = substr($type, 1);
        if ($value === null) {
            return $value;
        }
    }

    // Handle array<T>
    if (preg_match('/^array<(.+)>$/', $type, $matches)) {
        if (!is_array($value)) {
            throw new TypeValidationError(
                "$functionName(): Return value must be of type $type, " .
                gettype($value) . " returned"
            );
        }
        $validator = new ArrayOfValidator($matches[1]);
        $validator->validate($value, "$functionName(): Return value");
        return $value;
    }

    // Handle array{...}
    if (preg_match('/^array\{.+\}$/', $type)) {
        if (!is_array($value)) {
            throw new TypeValidationError(
                "$functionName(): Return value must be of type $type, " .
                gettype($value) . " returned"
            );
        }
        $validator = ArrayShapeValidator::parse($type);
        $validator->validate($value, "$functionName(): Return value");
        return $value;
    }

    return $value;
}
