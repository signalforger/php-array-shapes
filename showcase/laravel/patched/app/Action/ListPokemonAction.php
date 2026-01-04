<?php

/**
 * ListPokemonAction - List Pokemon from PokeAPI
 *
 * Demonstrates the boundary pattern:
 * - Input: Shape-validated request (ListPokemonRequest shape)
 * - Output: PokemonList DTO
 */

namespace App\Action;

use App\DTO\PokemonList;
use App\Shapes\ListPokemonRequest;
use App\Shapes\PokeApiList;
use Illuminate\Support\Facades\Http;

class ListPokemonAction
{
    /**
     * Execute the action
     *
     * @param ListPokemonRequest $request Shape-validated request
     * @return PokemonList DTO with pagination helpers
     */
    public function execute(ListPokemonRequest $request): PokemonList
    {
        $limit = $request['limit'] ?? 20;
        $offset = $request['offset'] ?? 0;

        $response = Http::get('https://pokeapi.co/api/v2/pokemon', [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        // The API response is validated against PokeApiList shape
        /** @var PokeApiList $data */
        $data = $response->json();

        // Convert shape-validated data to DTO
        return PokemonList::fromShape($data);
    }
}
