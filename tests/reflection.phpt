--TEST--
Reflection API for array shapes
--FILE--
<?php

// Test function with array<int> return type
function getIds(): array<int> {
    return [1, 2, 3];
}

// Test function with shape return type
function getUser(): array{id: int, name: string, active: bool} {
    return ['id' => 1, 'name' => 'Alice', 'active' => true];
}

// Test function with nested types
function getUsers(): array<array{id: int, name: string}> {
    return [['id' => 1, 'name' => 'Alice']];
}

// Test 1: ReflectionArrayOfType
$rf = new ReflectionFunction('getIds');
$returnType = $rf->getReturnType();

echo "Return type of getIds(): " . $returnType . "\n";
echo "Is builtin: " . ($returnType->isBuiltin() ? 'true' : 'false') . "\n";
echo "Allows null: " . ($returnType->allowsNull() ? 'true' : 'false') . "\n";

if ($returnType instanceof ReflectionArrayOfType) {
    echo "Element type: " . $returnType->getElementType() . "\n";
    echo "Depth: " . $returnType->getDepth() . "\n";
}

echo "\n";

// Test 2: ReflectionArrayShapeType
$rf = new ReflectionFunction('getUser');
$returnType = $rf->getReturnType();

echo "Return type of getUser(): " . $returnType . "\n";
echo "Is builtin: " . ($returnType->isBuiltin() ? 'true' : 'false') . "\n";

if ($returnType instanceof ReflectionArrayShapeType) {
    echo "Element count: " . $returnType->getElementCount() . "\n";
    echo "Has 'id': " . ($returnType->hasElement('id') ? 'true' : 'false') . "\n";
    echo "Has 'email': " . ($returnType->hasElement('email') ? 'true' : 'false') . "\n";

    echo "Elements:\n";
    foreach ($returnType->getElements() as $elem) {
        echo "  " . $elem . "\n";
    }
}

echo "\n";

// Test 3: Nested types
$rf = new ReflectionFunction('getUsers');
$returnType = $rf->getReturnType();

echo "Return type of getUsers(): " . $returnType . "\n";

if ($returnType instanceof ReflectionArrayOfType) {
    $elementType = $returnType->getElementType();
    echo "Element type: " . $elementType . "\n";

    if ($elementType instanceof ReflectionArrayShapeType) {
        echo "Inner shape elements:\n";
        foreach ($elementType->getElements() as $elem) {
            echo "  Key: " . $elem->getKey();
            echo ", Type: " . $elem->getType();
            echo ", Optional: " . ($elem->isOptional() ? 'true' : 'false');
            echo "\n";
        }
    }
}

echo "\nAll reflection tests passed!\n";

--EXPECT--
Return type of getIds(): array<int>
Is builtin: true
Allows null: false
Element type: int
Depth: 1

Return type of getUser(): array{id: int, name: string, active: bool}
Is builtin: true
Element count: 3
Has 'id': true
Has 'email': false
Elements:
  id: int
  name: string
  active: bool

Return type of getUsers(): array<array{id: int, name: string}>
Element type: array{id: int, name: string}
Inner shape elements:
  Key: id, Type: int, Optional: false
  Key: name, Type: string, Optional: false

All reflection tests passed!
