<?php

namespace App\Action;

/**
 * Action interface for request handling.
 *
 * Actions encapsulate business logic and provide typed results.
 * The result() method returns typed DTOs that can be inspected
 * via reflection for API documentation generation.
 */
interface ActionInterface
{
    /**
     * Execute the action with given parameters.
     */
    public function execute(): void;

    /**
     * Get the result of the action.
     *
     * Returns a DTO object that can be serialized to JSON.
     */
    public function result(): object|array;
}
