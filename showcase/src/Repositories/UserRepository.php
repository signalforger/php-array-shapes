<?php

namespace App\Repositories;

require_once __DIR__ . '/../Shapes/UserShapes.php';

/**
 * Repository for user data access.
 *
 * Demonstrates typed arrays for collections and array shapes for records.
 */
class UserRepository
{
    /** @var array<UserRecord> Simulated database storage */
    private array<UserRecord> $users = [];

    public function __construct()
    {
        // Seed with sample data
        $this->users = [
            [
                'id' => 1,
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'created_at' => '2024-01-15 10:30:00',
                'is_active' => true
            ],
            [
                'id' => 2,
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'created_at' => '2024-02-20 14:45:00',
                'is_active' => true
            ],
            [
                'id' => 3,
                'name' => 'Charlie Brown',
                'email' => 'charlie@example.com',
                'created_at' => '2024-03-10 09:15:00',
                'is_active' => false
            ],
        ];
    }

    /**
     * Find a user by ID.
     *
     * Returns an array shape with guaranteed structure.
     */
    public function find(int $id): ?UserRecord
    {
        foreach ($this->users as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Get all users.
     *
     * Returns a typed array of user records.
     */
    public function all(): array<UserRecord>
    {
        return $this->users;
    }

    /**
     * Get active users only.
     */
    public function getActive(): array<UserRecord>
    {
        return array_values(array_filter(
            $this->users,
            fn($user) => ($user['is_active'] ?? false) === true
        ));
    }

    /**
     * Get user IDs.
     *
     * Returns a typed array of integers.
     */
    public function getIds(): array<int>
    {
        return array_map(fn($user) => $user['id'], $this->users);
    }

    /**
     * Get user emails indexed by ID.
     *
     * Returns a map type: array<int, string>
     */
    public function getEmailsById(): array<int, string>
    {
        $result = [];
        foreach ($this->users as $user) {
            $result[$user['id']] = $user['email'];
        }
        return $result;
    }

    /**
     * Get paginated users.
     *
     * Returns a complex nested shape with pagination metadata.
     */
    public function paginate(int $page = 1, int $perPage = 10): PaginatedUsers
    {
        $total = count($this->users);
        $offset = ($page - 1) * $perPage;
        $data = array_slice($this->users, $offset, $perPage);

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage)
            ]
        ];
    }

    /**
     * Get user with profile (demonstrates nested shapes).
     */
    public function findWithProfile(int $id): ?UserWithProfile
    {
        $user = $this->find($id);
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'profile' => [
                'avatar_url' => "https://avatars.example.com/{$user['id']}.jpg",
                'bio' => "Hello, I'm {$user['name']}!",
                'location' => null
            ]
        ];
    }
}
