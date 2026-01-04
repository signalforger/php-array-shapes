# PHP Array Shapes - VS Code Extension

Syntax highlighting for the PHP Typed Arrays and Array Shapes RFC.

## Supported Syntax

- `array<Type>` - Typed arrays
- `array<KeyType, ValueType>` - Typed maps
- `array{key: type}` - Array shapes
- `array{key?: type}` - Optional keys
- `array{...}!` - Closed shapes
- `shape Name = array{...}` - Shape type aliases

## Installation

### From VSIX (recommended)

1. Package the extension:
   ```bash
   cd vscode-extension
   npx vsce package
   ```

2. Install in VS Code:
   - Open VS Code
   - Press `Ctrl+Shift+P` → "Extensions: Install from VSIX..."
   - Select the generated `.vsix` file

### Development Mode

1. Open this folder in VS Code
2. Press `F5` to launch Extension Development Host
3. Open a PHP file to test highlighting

## Example

```php
<?php

function getUsers(): array<User> {
    return [];
}

function getConfig(): array{debug: bool, cache?: int} {
    return ['debug' => true];
}

shape UserData = array{
    id: int,
    name: string,
    email?: string
};
```
