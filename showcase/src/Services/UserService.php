<?php

namespace App\Services;

require_once __DIR__ . '/../Repositories/UserRepository.php';

use App\Repositories\UserRepository;

/**
 * Service layer for user operations.
 *
 * Demonstrates how typed arrays flow through the application layers.
 */
class UserService
{
    private UserRepository $repository;

    public function __construct()
    {
        $this->repository = new UserRepository();
    }

    /**
     * Get all users as a typed array.
     */
    public function getAllUsers(): array<UserRecord>
    {
        return $this->repository->all();
    }

    /**
     * Get user by ID with API response wrapper.
     */
    public function getUser(int $id): ApiResponse
    {
        $user = $this->repository->find($id);

        if ($user === null) {
            return [
                'success' => false,
                'data' => null,
                'message' => "User with ID {$id} not found",
                'errors' => ['User not found']
            ];
        }

        return [
            'success' => true,
            'data' => $user,
            'message' => 'User retrieved successfully'
        ];
    }

    /**
     * Get statistics about users.
     *
     * Returns a closed shape (no extra keys allowed).
     */
    public function getStats(): array{total: int, active: int, inactive: int}!
    {
        $all = $this->repository->all();
        $active = $this->repository->getActive();

        return [
            'total' => count($all),
            'active' => count($active),
            'inactive' => count($all) - count($active)
        ];
    }

    /**
     * Get user names as a simple string array.
     */
    public function getUserNames(): array<string>
    {
        $users = $this->repository->all();
        return array_map(fn($user) => $user['name'], $users);
    }

    /**
     * Search users by name (returns filtered typed array).
     */
    public function searchByName(string $query): array<UserRecord>
    {
        $users = $this->repository->all();
        return array_values(array_filter(
            $users,
            fn($user) => stripos($user['name'], $query) !== false
        ));
    }

    /**
     * Get user email lookup table.
     */
    public function getEmailLookup(): array<int, string>
    {
        return $this->repository->getEmailsById();
    }

    /**
     * Batch get users by IDs.
     */
    public function getUsersByIds(array<int> $ids): array<UserRecord>
    {
        $result = [];
        foreach ($ids as $id) {
            $user = $this->repository->find($id);
            if ($user !== null) {
                $result[] = $user;
            }
        }
        return $result;
    }
}
