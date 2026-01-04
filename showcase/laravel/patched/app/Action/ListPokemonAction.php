<?php

namespace App\Action;

use App\Action\Request\ListPokemonRequest;
use App\Action\Response\PokemonListResponse;
use Illuminate\Support\Facades\Http;

/**
 * Action to list Pokemon from PokeAPI.
 *
 * @api GET /api/pokemon
 */
class ListPokemonAction implements ActionInterface
{
    private ?PokemonListResponse $result = null;

    public function __construct(
        private readonly ListPokemonRequest $request,
    ) {}

    public function execute(): void
    {
        $response = Http::get('https://pokeapi.co/api/v2/pokemon', [
            'limit' => $this->request->limit,
            'offset' => $this->request->offset,
        ]);

        $data = $response->json();

        $this->result = [
            'count' => $data['count'],
            'next' => $data['next'],
            'previous' => $data['previous'],
            'results' => array_map(fn($item) => [
                'name' => $item['name'],
                'url' => $item['url'],
            ], $data['results']),
        ];
    }

    public function result(): PokemonListResponse
    {
        if ($this->result === null) {
            throw new \RuntimeException('Action not executed');
        }
        return $this->result;
    }
}
