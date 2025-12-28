<?php
/**
 * Reflection API for Array Shapes
 *
 * The ReflectionArrayShapeType and ReflectionArrayShapeElement classes
 * allow runtime inspection of array shape types.
 */

declare(strict_arrays=1);

// =============================================================================
// BASIC REFLECTION OF ARRAY SHAPES
// =============================================================================

function getUserProfile(): array{id: int, name: string, email: string, active: bool} {
    return ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'active' => true];
}

// Get reflection of the function
$reflection = new ReflectionFunction('getUserProfile');
$returnType = $reflection->getReturnType();

echo "=== Basic Reflection ===\n";
echo "Return type class: " . get_class($returnType) . "\n";
echo "Is array shape: " . ($returnType instanceof ReflectionArrayShapeType ? 'yes' : 'no') . "\n";
echo "Type as string: " . (string)$returnType . "\n";
echo "\n";


// =============================================================================
// INSPECTING SHAPE ELEMENTS
// =============================================================================

echo "=== Shape Elements ===\n";

$elements = $returnType->getElements();
echo "Number of elements: " . count($elements) . "\n";
echo "Element count method: " . $returnType->getElementCount() . "\n";
echo "Required elements: " . $returnType->getRequiredElementCount() . "\n";
echo "\n";

echo "Elements:\n";
foreach ($elements as $element) {
    echo "  - Name: " . $element->getName() . "\n";
    echo "    Type: " . (string)$element->getType() . "\n";
    echo "    Optional: " . ($element->isOptional() ? 'yes' : 'no') . "\n";
    echo "\n";
}


// =============================================================================
// REFLECTION WITH OPTIONAL KEYS
// =============================================================================

function getConfig(): array{host: string, port?: int, ssl?: bool, timeout?: float} {
    return ['host' => 'localhost'];
}

echo "=== Optional Keys Reflection ===\n";

$configReflection = new ReflectionFunction('getConfig');
$configType = $configReflection->getReturnType();

echo "Total elements: " . $configType->getElementCount() . "\n";
echo "Required elements: " . $configType->getRequiredElementCount() . "\n";
echo "\n";

foreach ($configType->getElements() as $elem) {
    $status = $elem->isOptional() ? 'optional' : 'required';
    echo "  - {$elem->getName()}: {$elem->getType()} ({$status})\n";
}
echo "\n";


// =============================================================================
// REFLECTION OF NESTED SHAPES
// =============================================================================

function getNestedData(): array{
    user: array{id: int, name: string},
    settings: array{theme: string, language: string}
} {
    return [
        'user' => ['id' => 1, 'name' => 'Alice'],
        'settings' => ['theme' => 'dark', 'language' => 'en']
    ];
}

echo "=== Nested Shapes Reflection ===\n";

$nestedReflection = new ReflectionFunction('getNestedData');
$nestedType = $nestedReflection->getReturnType();

foreach ($nestedType->getElements() as $elem) {
    echo "Element: {$elem->getName()}\n";
    $elemType = $elem->getType();
    echo "  Type class: " . get_class($elemType) . "\n";

    if ($elemType instanceof ReflectionArrayShapeType) {
        echo "  Nested elements:\n";
        foreach ($elemType->getElements() as $nested) {
            echo "    - {$nested->getName()}: {$nested->getType()}\n";
        }
    }
    echo "\n";
}


// =============================================================================
// REFLECTION OF UNION TYPES IN SHAPES
// =============================================================================

function getFlexibleData(): array{id: int|string, value: float|int|null} {
    return ['id' => 'ABC', 'value' => null];
}

echo "=== Union Types in Shapes ===\n";

$flexReflection = new ReflectionFunction('getFlexibleData');
$flexType = $flexReflection->getReturnType();

foreach ($flexType->getElements() as $elem) {
    $elemType = $elem->getType();
    echo "Element: {$elem->getName()}\n";
    echo "  Type: {$elemType}\n";
    echo "  Type class: " . get_class($elemType) . "\n";

    if ($elemType instanceof ReflectionUnionType) {
        echo "  Union members:\n";
        foreach ($elemType->getTypes() as $unionMember) {
            echo "    - {$unionMember}\n";
        }
    }
    echo "\n";
}


// =============================================================================
// REFLECTION OF PARAMETER TYPES
// =============================================================================

function processOrder(array{product: string, quantity: int, price: float} $order): float {
    return $order['quantity'] * $order['price'];
}

echo "=== Parameter Type Reflection ===\n";

$orderReflection = new ReflectionFunction('processOrder');
$params = $orderReflection->getParameters();

foreach ($params as $param) {
    echo "Parameter: \${$param->getName()}\n";
    $paramType = $param->getType();
    echo "  Type class: " . get_class($paramType) . "\n";

    if ($paramType instanceof ReflectionArrayShapeType) {
        echo "  Shape elements:\n";
        foreach ($paramType->getElements() as $elem) {
            echo "    - {$elem->getName()}: {$elem->getType()}\n";
        }
    }
    echo "\n";
}


// =============================================================================
// REFLECTION OF CLASS METHODS
// =============================================================================

class UserService
{
    public function create(
        array{name: string, email: string, password?: string} $data
    ): array{id: int, name: string, email: string, created_at: string} {
        return [
            'id' => 1,
            'name' => $data['name'],
            'email' => $data['email'],
            'created_at' => date('c')
        ];
    }
}

echo "=== Class Method Reflection ===\n";

$methodReflection = new ReflectionMethod(UserService::class, 'create');

// Reflect parameter
$paramType = $methodReflection->getParameters()[0]->getType();
echo "Parameter type (create):\n";
if ($paramType instanceof ReflectionArrayShapeType) {
    foreach ($paramType->getElements() as $elem) {
        $opt = $elem->isOptional() ? '?' : '';
        echo "  - {$elem->getName()}{$opt}: {$elem->getType()}\n";
    }
}

// Reflect return type
$returnType = $methodReflection->getReturnType();
echo "\nReturn type:\n";
if ($returnType instanceof ReflectionArrayShapeType) {
    foreach ($returnType->getElements() as $elem) {
        echo "  - {$elem->getName()}: {$elem->getType()}\n";
    }
}
echo "\n";


// =============================================================================
// DYNAMIC TYPE CHECKING USING REFLECTION
// =============================================================================

echo "=== Dynamic Type Checking ===\n";

function validateAgainstShape(array $data, ReflectionArrayShapeType $shape): array {
    $errors = [];

    foreach ($shape->getElements() as $elem) {
        $key = $elem->getName();

        // Check if required key is missing
        if (!array_key_exists($key, $data)) {
            if (!$elem->isOptional()) {
                $errors[] = "Missing required key: {$key}";
            }
            continue;
        }

        // Get the expected type as string
        $expectedType = (string)$elem->getType();
        $actualType = gettype($data[$key]);

        echo "  Checking '{$key}': expected={$expectedType}, actual={$actualType}\n";
    }

    return $errors;
}

// Test validation
$testData = ['id' => 1, 'name' => 'Test'];
$shapeType = (new ReflectionFunction('getUserProfile'))->getReturnType();

echo "Validating test data against getUserProfile shape:\n";
$errors = validateAgainstShape($testData, $shapeType);
if ($errors) {
    echo "Errors found:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}
echo "\n";


// =============================================================================
// BUILDING DOCUMENTATION FROM REFLECTION
// =============================================================================

echo "=== Auto-Generated Documentation ===\n";

function generateShapeDoc(ReflectionArrayShapeType $shape): string {
    $doc = "Shape structure:\n";
    $doc .= "```\n";
    $doc .= "array{\n";

    foreach ($shape->getElements() as $elem) {
        $optional = $elem->isOptional() ? '?' : '';
        $doc .= "    {$elem->getName()}{$optional}: {$elem->getType()},\n";
    }

    $doc .= "}\n";
    $doc .= "```\n";
    $doc .= "\nTotal elements: " . $shape->getElementCount() . "\n";
    $doc .= "Required elements: " . $shape->getRequiredElementCount() . "\n";

    return $doc;
}

$docType = (new ReflectionFunction('getConfig'))->getReturnType();
echo generateShapeDoc($docType);


// =============================================================================
// COMPARING SHAPE TYPES
// =============================================================================

function getUserA(): array{id: int, name: string} {
    return ['id' => 1, 'name' => 'A'];
}

function getUserB(): array{id: int, name: string} {
    return ['id' => 2, 'name' => 'B'];
}

function getUserC(): array{id: int, name: string, email: string} {
    return ['id' => 3, 'name' => 'C', 'email' => 'c@example.com'];
}

echo "=== Comparing Shape Types ===\n";

$typeA = (new ReflectionFunction('getUserA'))->getReturnType();
$typeB = (new ReflectionFunction('getUserB'))->getReturnType();
$typeC = (new ReflectionFunction('getUserC'))->getReturnType();

function shapesMatch(ReflectionArrayShapeType $a, ReflectionArrayShapeType $b): bool {
    if ($a->getElementCount() !== $b->getElementCount()) {
        return false;
    }

    $aElements = $a->getElements();
    $bElements = $b->getElements();

    for ($i = 0; $i < count($aElements); $i++) {
        if ($aElements[$i]->getName() !== $bElements[$i]->getName()) {
            return false;
        }
        if ((string)$aElements[$i]->getType() !== (string)$bElements[$i]->getType()) {
            return false;
        }
        if ($aElements[$i]->isOptional() !== $bElements[$i]->isOptional()) {
            return false;
        }
    }

    return true;
}

echo "getUserA matches getUserB: " . (shapesMatch($typeA, $typeB) ? 'yes' : 'no') . "\n";
echo "getUserA matches getUserC: " . (shapesMatch($typeA, $typeC) ? 'yes' : 'no') . "\n";


echo "\n--- All reflection examples completed successfully! ---\n";
