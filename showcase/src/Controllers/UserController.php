<?php

namespace App\Controllers;

require_once __DIR__ . '/../Services/UserService.php';

use App\Services\UserService;

/**
 * API Controller for user endpoints.
 *
 * Demonstrates typed arrays and shapes in API responses.
 */
class UserController
{
    private UserService $service;

    public function __construct()
    {
        $this->service = new UserService();
    }

    /**
     * GET /api/users
     *
     * Returns all users as a typed array wrapped in API response.
     */
    public function index(): ApiResponse
    {
        $users = $this->service->getAllUsers();

        return [
            'success' => true,
            'data' => $users,
            'message' => 'Users retrieved successfully'
        ];
    }

    /**
     * GET /api/users/{id}
     */
    public function show(int $id): ApiResponse
    {
        return $this->service->getUser($id);
    }

    /**
     * GET /api/users/stats
     *
     * Returns closed shape with exact keys.
     */
    public function stats(): array{total: int, active: int, inactive: int}!
    {
        return $this->service->getStats();
    }

    /**
     * GET /api/users/names
     *
     * Returns simple typed array of strings.
     */
    public function names(): array<string>
    {
        return $this->service->getUserNames();
    }

    /**
     * GET /api/users/search?q={query}
     */
    public function search(string $query): ApiResponse
    {
        $results = $this->service->searchByName($query);

        return [
            'success' => true,
            'data' => $results,
            'message' => count($results) . ' users found'
        ];
    }

    /**
     * POST /api/users/batch
     *
     * Batch retrieve users by IDs.
     * Demonstrates typed array as parameter.
     */
    public function batch(array<int> $ids): ApiResponse
    {
        $users = $this->service->getUsersByIds($ids);

        return [
            'success' => true,
            'data' => $users,
            'message' => count($users) . ' users retrieved'
        ];
    }

    /**
     * GET /api/users/emails
     *
     * Returns a map type.
     */
    public function emails(): array<int, string>
    {
        return $this->service->getEmailLookup();
    }
}
