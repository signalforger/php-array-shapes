<?php
declare(strict_arrays=1);

/**
 * PHP 8.5 with Array Shapes - Demo
 */

// Define shapes
shape User = array{id: int, name: string, email: string};
shape ApiResponse = array{success: bool, data: array, message?: string};

// Classes for typed arrays
class Product {
    public function __construct(
        public int $id,
        public string $name,
        public float $price
    ) {}
}

// Functions using array shapes
function getUsers(): array<User> {
    return [
        ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com'],
    ];
}

function getProducts(): array<Product> {
    return [
        new Product(1, 'Widget', 29.99),
        new Product(2, 'Gadget', 49.99),
        new Product(3, 'Gizmo', 19.99),
    ];
}

function createResponse(bool $success, array $data, ?string $message = null): ApiResponse {
    $response = ['success' => $success, 'data' => $data];
    if ($message !== null) {
        $response['message'] = $message;
    }
    return $response;
}

// HTML Output
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Array Shapes Demo</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #4F5B93; }
        h2 { color: #333; border-bottom: 2px solid #4F5B93; padding-bottom: 10px; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; overflow-x: auto; }
        .success { color: #22c55e; }
        .type { color: #569cd6; }
        .keyword { color: #c586c0; }
        .string { color: #ce9178; }
        .card { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #4F5B93; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐘 PHP 8.5 with Array Shapes</h1>

        <div class="card">
            <h2>PHP Version</h2>
            <pre><?= phpversion() ?></pre>
        </div>

        <div class="card">
            <h2>Feature: Typed Arrays <code>array&lt;User&gt;</code></h2>
            <p>Functions can declare they return an array of specific objects:</p>
            <pre><span class="keyword">function</span> <span class="type">getProducts</span>(): <span class="type">array&lt;Product&gt;</span> { ... }</pre>

            <h3>Products:</h3>
            <table>
                <tr><th>ID</th><th>Name</th><th>Price</th></tr>
                <?php foreach (getProducts() as $product): ?>
                <tr>
                    <td><?= $product->id ?></td>
                    <td><?= $product->name ?></td>
                    <td>$<?= number_format($product->price, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card">
            <h2>Feature: Shape Aliases</h2>
            <p>Define reusable shapes with the <code>shape</code> keyword:</p>
            <pre><span class="keyword">shape</span> <span class="type">User</span> = <span class="type">array</span>{id: <span class="type">int</span>, name: <span class="type">string</span>, email: <span class="type">string</span>};</pre>

            <h3>Users (using shape alias):</h3>
            <table>
                <tr><th>ID</th><th>Name</th><th>Email</th></tr>
                <?php foreach (getUsers() as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= $user['name'] ?></td>
                    <td><?= $user['email'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card">
            <h2>Feature: API Response Shapes</h2>
            <pre><span class="keyword">shape</span> <span class="type">ApiResponse</span> = <span class="type">array</span>{success: <span class="type">bool</span>, data: <span class="type">array</span>, message?: <span class="type">string</span>};</pre>

            <h3>Response:</h3>
            <pre><?= json_encode(createResponse(true, getUsers(), 'Users fetched successfully'), JSON_PRETTY_PRINT) ?></pre>
        </div>

        <div class="card">
            <h2>Reflection API</h2>
            <?php
            $rf = new ReflectionFunction('getProducts');
            $returnType = $rf->getReturnType();
            ?>
            <pre>
ReflectionFunction('getProducts')->getReturnType()
  → <?= get_class($returnType) ?>

  __toString(): "<?= $returnType ?>"
            </pre>
        </div>

        <div class="card">
            <h2 class="success">✓ Array Shapes Working!</h2>
            <p>This page demonstrates PHP 8.5 with native array shapes support.</p>
        </div>
    </div>
</body>
</html>
