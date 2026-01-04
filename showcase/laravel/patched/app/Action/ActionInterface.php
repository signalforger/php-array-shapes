<?php

namespace App\Action;

/**
 * Action interface for request handling.
 *
 * Actions encapsulate business logic and provide typed results.
 * The result() method returns typed arrays or array shapes.
 * The return type is used for API documentation generation.
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
     * In patched PHP, this returns typed arrays or array shapes.
     * The return type is used for API documentation generation.
     */
    public function result(): mixed;
}
