<?php

/**
 * Get Pokemon Action
 *
 * Pure action class that accepts shape-validated input
 * and returns a DTO. No interface or parent class.
 *
 * Pattern:
 * - Shape validates data at boundary (controller)
 * - Action receives validated shape
 * - Action validates external API response against shape
 * - Action returns DTO with game mechanics methods
 *
 * @api GET /api/pokemon/{nameOrId}
 */

namespace App\Action;

use App\DTO\Pokemon;
use App\Shapes\GetPokemonRequest;
use App\Shapes\PokeApiPokemon;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GetPokemonAction
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * Execute the action with shape-validated request data
     *
     * @param GetPokemonRequest $request Shape-validated request data
     * @return Pokemon|null DTO with game mechanics methods, null if not found
     */
    public function execute(GetPokemonRequest $request): ?Pokemon
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://pokeapi.co/api/v2/pokemon/' . strtolower((string) $request['id'])
            );

            // Validate external API response against shape
            $data = $response->toArray();

            // In production, this would validate: is_shape($data, PokeApiPokemon::shape)
            // For now we trust the response structure

            return Pokemon::fromShape($data);

        } catch (\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
}
