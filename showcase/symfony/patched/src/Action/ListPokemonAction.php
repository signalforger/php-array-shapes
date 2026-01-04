<?php

/**
 * List Pokemon Action
 *
 * Pure action class that accepts shape-validated input
 * and returns a DTO. No interface or parent class.
 *
 * Pattern:
 * - Shape validates data at boundary (controller)
 * - Action receives validated shape
 * - Action validates external API response against shape
 * - Action returns DTO with collection methods
 *
 * @api GET /api/pokemon
 */

namespace App\Action;

use App\DTO\PokemonList;
use App\Shapes\ListPokemonRequest;
use App\Shapes\PokeApiList;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ListPokemonAction
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * Execute the action with shape-validated request data
     *
     * @param ListPokemonRequest $request Shape-validated request data
     * @return PokemonList DTO with pagination methods
     */
    public function execute(ListPokemonRequest $request): PokemonList
    {
        $limit = $request['limit'] ?? 20;
        $offset = $request['offset'] ?? 0;

        $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon', [
            'query' => [
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);

        // Validate external API response against shape
        $data = $response->toArray();

        // In production, this would validate: is_shape($data, PokeApiList::shape)
        // For now we trust the response structure

        return PokemonList::fromShape($data);
    }
}
