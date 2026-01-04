# PhpStorm Support for Typed Arrays & Array Shapes

This directory contains a PhpStorm plugin that adds syntax highlighting support for the typed arrays and array shapes RFC.

## Supported Syntax

```php
// Typed arrays
function getIds(): array<int> { ... }
function getUsers(): array<User> { ... }
function getScores(): array<string, int> { ... }

// Array shapes
function getUser(): array{id: int, name: string} { ... }
function getConfig(): array{debug: bool, cache?: int} { ... }

// Closed shapes
function getStrict(): array{id: int, name: string}! { ... }

// Shape declarations
shape User = array{id: int, name: string, email: string};
shape Config = array{debug: bool, cache_ttl?: int};
```

## Build from Source

### Prerequisites

- Java 17+
- Gradle 8.5+ (or let the wrapper download it)

### Build Steps

```bash
cd phpstorm-support

# Option 1: If you have Gradle installed
gradle wrapper --gradle-version=8.5
./gradlew buildPlugin

# Option 2: Manual Gradle wrapper setup
# Download gradle-wrapper.jar and place in gradle/wrapper/
./gradlew buildPlugin
```

The plugin ZIP will be created at:
```
build/distributions/php-array-shapes-1.0.0.zip
```

## Install in PhpStorm

1. Build the plugin (see above)
2. Open PhpStorm
3. Go to **Settings** → **Plugins**
4. Click **⚙️** (gear icon) → **Install Plugin from Disk...**
5. Select `build/distributions/php-array-shapes-1.0.0.zip`
6. Restart PhpStorm

## Configure Colors

After installation, you can customize the highlighting colors:

1. **Settings** → **Editor** → **Color Scheme** → **PHP Array Shapes**
2. Customize colors for:
   - Array type syntax (`array<T>`, `array{...}`)
   - Shape key names
   - `shape` keyword

## What It Does

- Adds syntax highlighting for `array<Type>` and `array{key: type}` in PHP files
- Highlights the `shape` keyword in shape declarations
- Provides a color settings page for customization

## Limitations

This is a syntax highlighting plugin only. It does not provide:

- Full syntax error suppression (PhpStorm may still show some errors)
- Code completion for shape keys
- Type inference
- Refactoring support
- Go to definition for shapes

For full IDE support, wait for official PhpStorm support after PHP adopts the RFC.

## Project Structure

```
phpstorm-support/
├── build.gradle.kts              # Gradle build configuration
├── settings.gradle.kts           # Gradle settings
├── gradle.properties             # Gradle properties
├── gradlew                       # Gradle wrapper script
└── src/main/
    ├── kotlin/com/signalforger/phparrayshapes/
    │   ├── ArrayShapesAnnotator.kt        # Syntax highlighting
    │   ├── ArrayShapesSyntaxInspection.kt # Error suppression
    │   └── ArrayShapesColorSettingsPage.kt # Color customization
    └── resources/META-INF/
        └── plugin.xml            # Plugin descriptor
```

## Troubleshooting

### Plugin doesn't load

Make sure you're using PhpStorm 2024.3 or newer. The plugin requires PhpStorm 243+.

### Syntax not highlighted

The plugin only highlights syntax in type positions (return types, parameter types, property types). It won't highlight array shapes in regular code.

### Still seeing errors

The plugin adds highlighting but cannot fully suppress PHP parser errors for unknown syntax. This is a limitation of extending PhpStorm without modifying its core PHP parser.
