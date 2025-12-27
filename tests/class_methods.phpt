--TEST--
Array shapes with class methods
--FILE--
<?php

class UserRepository {
    /**
     * @return array<array{id: int, name: string}>
     */
    public function findAll(): array<array{id: int, name: string}> {
        return [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
    }

    public function findById(int $id): ?array{id: int, name: string, email: ?string} {
        if ($id === 1) {
            return [
                'id' => 1,
                'name' => 'Alice',
                'email' => 'alice@example.com'
            ];
        }
        return null;
    }

    public function create(array{name: string, email: ?string} $data): array{id: int, name: string, email: ?string} {
        return [
            'id' => rand(1, 1000),
            'name' => $data['name'],
            'email' => $data['email'] ?? null
        ];
    }
}

$repo = new UserRepository();

// Test findAll
$users = $repo->findAll();
echo "findAll() returned " . count($users) . " users\n";
var_dump($users);

// Test findById
$user = $repo->findById(1);
echo "\nfindById(1):\n";
var_dump($user);

$notFound = $repo->findById(999);
echo "\nfindById(999):\n";
var_dump($notFound);

// Test create
$newUser = $repo->create(['name' => 'Charlie', 'email' => 'charlie@example.com']);
echo "\ncreate() returned:\n";
echo "id type: " . gettype($newUser['id']) . "\n";
echo "name: " . $newUser['name'] . "\n";
echo "email: " . $newUser['email'] . "\n";

echo "\nAll class method tests passed!\n";

--EXPECTF--
findAll() returned 2 users
array(2) {
  [0]=>
  array(2) {
    ["id"]=>
    int(1)
    ["name"]=>
    string(5) "Alice"
  }
  [1]=>
  array(2) {
    ["id"]=>
    int(2)
    ["name"]=>
    string(3) "Bob"
  }
}

findById(1):
array(3) {
  ["id"]=>
  int(1)
  ["name"]=>
  string(5) "Alice"
  ["email"]=>
  string(17) "alice@example.com"
}

findById(999):
NULL

create() returned:
id type: integer
name: Charlie
email: charlie@example.com

All class method tests passed!
