<?php
/**
 * Optional Keys in Array Shapes
 *
 * Optional keys are marked with a question mark after the key name: key?: type
 * Optional keys don't need to be present in the returned array.
 */

declare(strict_arrays=1);

// =============================================================================
// BASIC OPTIONAL KEYS
// =============================================================================

/**
 * Shape with one required and one optional key
 */
function getUserBasic(): array{name: string, nickname?: string} {
    // We can omit 'nickname' since it's optional
    return ['name' => 'Alice'];
}

$user1 = getUserBasic();
echo "User: {$user1['name']}, Nickname: " . ($user1['nickname'] ?? 'none') . "\n";


/**
 * Same shape, but this time including the optional key
 */
function getUserWithNickname(): array{name: string, nickname?: string} {
    return ['name' => 'Bob', 'nickname' => 'Bobby'];
}

$user2 = getUserWithNickname();
echo "User: {$user2['name']}, Nickname: {$user2['nickname']}\n";


// =============================================================================
// MULTIPLE OPTIONAL KEYS
// =============================================================================

/**
 * Shape with multiple optional keys
 */
function getConfig(): array{
    host: string,
    port?: int,
    timeout?: float,
    ssl?: bool,
    username?: string,
    password?: string
} {
    // Only host is required, everything else is optional
    return ['host' => 'localhost'];
}

/**
 * Same shape with some optional keys filled
 */
function getSecureConfig(): array{
    host: string,
    port?: int,
    timeout?: float,
    ssl?: bool,
    username?: string,
    password?: string
} {
    return [
        'host' => 'secure.example.com',
        'port' => 443,
        'ssl' => true,
        'timeout' => 30.0
    ];
}

$config1 = getConfig();
$config2 = getSecureConfig();
echo "Config 1 host: {$config1['host']}, port: " . ($config1['port'] ?? 'default') . "\n";
echo "Config 2 host: {$config2['host']}, port: {$config2['port']}, ssl: " . ($config2['ssl'] ? 'yes' : 'no') . "\n";


// =============================================================================
// ALL OPTIONAL KEYS
// =============================================================================

/**
 * Shape where all keys are optional (useful for options/settings)
 */
function getOptions(): array{
    debug?: bool,
    verbose?: bool,
    color?: bool,
    format?: string
} {
    // Can return empty array since all keys are optional
    return [];
}

/**
 * Same shape with all options set
 */
function getFullOptions(): array{
    debug?: bool,
    verbose?: bool,
    color?: bool,
    format?: string
} {
    return [
        'debug' => true,
        'verbose' => false,
        'color' => true,
        'format' => 'json'
    ];
}

$opts1 = getOptions();
$opts2 = getFullOptions();
echo "Options 1 debug: " . (isset($opts1['debug']) ? ($opts1['debug'] ? 'yes' : 'no') : 'not set') . "\n";
echo "Options 2 debug: " . ($opts2['debug'] ? 'yes' : 'no') . ", format: {$opts2['format']}\n";


// =============================================================================
// MIXED REQUIRED AND OPTIONAL
// =============================================================================

/**
 * Real-world example: API response shape
 */
function getApiResponse(): array{
    status: int,
    message: string,
    data?: array,
    error?: string,
    timestamp?: string
} {
    return [
        'status' => 200,
        'message' => 'Success',
        'data' => ['items' => [1, 2, 3]],
        'timestamp' => date('c')
        // 'error' is intentionally omitted since this is a success response
    ];
}

/**
 * Error response using the same shape
 */
function getErrorResponse(): array{
    status: int,
    message: string,
    data?: array,
    error?: string,
    timestamp?: string
} {
    return [
        'status' => 500,
        'message' => 'Internal Server Error',
        'error' => 'Database connection failed'
        // 'data' is omitted since this is an error
    ];
}

$success = getApiResponse();
$error = getErrorResponse();
echo "Success response: status={$success['status']}, has data: " . (isset($success['data']) ? 'yes' : 'no') . "\n";
echo "Error response: status={$error['status']}, error: {$error['error']}\n";


// =============================================================================
// OPTIONAL KEYS WITH DEFAULTS PATTERN
// =============================================================================

/**
 * Function that merges defaults with optional shape keys
 */
function createWidget(array{
    name: string,
    width?: int,
    height?: int,
    color?: string,
    visible?: bool
} $config): array{name: string, width: int, height: int, color: string, visible: bool} {
    // Merge with defaults
    return [
        'name' => $config['name'],
        'width' => $config['width'] ?? 100,
        'height' => $config['height'] ?? 50,
        'color' => $config['color'] ?? 'blue',
        'visible' => $config['visible'] ?? true
    ];
}

$widget1 = createWidget(['name' => 'Button']);
$widget2 = createWidget(['name' => 'Panel', 'width' => 200, 'color' => 'red']);
echo "Widget 1: {$widget1['name']} ({$widget1['width']}x{$widget1['height']}) color={$widget1['color']}\n";
echo "Widget 2: {$widget2['name']} ({$widget2['width']}x{$widget2['height']}) color={$widget2['color']}\n";


echo "\n--- All optional key examples completed successfully! ---\n";
