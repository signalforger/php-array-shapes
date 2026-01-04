<?php

/**
 * GetPokemonAction - Fetch a Pokemon from PokeAPI
 *
 * Demonstrates the boundary pattern with external APIs:
 * - Input: Shape-validated request (GetPokemonRequest shape)
 * - External API: Response validated against PokeApiPokemon shape
 * - Output: Pokemon DTO with game mechanics methods
 */

namespace App\Action;

use App\DTO\Pokemon;
use App\Shapes\GetPokemonRequest;
use App\Shapes\PokeApiPokemon;
use Illuminate\Support\Facades\Http;

class GetPokemonAction
{
    /**
     * Execute the action
     *
     * @param GetPokemonRequest $request Shape-validated request
     * @return Pokemon|null DTO or null if not found
     */
    public function execute(GetPokemonRequest $request): ?Pokemon
    {
        $nameOrId = strtolower((string) $request['name_or_id']);

        $response = Http::get("https://pokeapi.co/api/v2/pokemon/{$nameOrId}");

        if ($response->status() === 404) {
            return null;
        }

        // The API response is validated against PokeApiPokemon shape
        /** @var PokeApiPokemon $data */
        $data = $response->json();

        // Convert shape-validated data to DTO
        return Pokemon::fromShape($data);
    }
}
