<?php

namespace App\Action;

use App\Action\Request\ListPokemonRequest;
use App\Action\Response\PokemonListItemDto;
use App\Action\Response\PokemonListResponseDto;
use Illuminate\Support\Facades\Http;

/**
 * Action to list Pokemon from PokeAPI.
 *
 * @api GET /api/pokemon
 */
class ListPokemonAction implements ActionInterface
{
    private ?PokemonListResponseDto $result = null;

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

        $this->result = new PokemonListResponseDto(
            count: $data['count'],
            next: $data['next'],
            previous: $data['previous'],
            results: array_map(
                fn($item) => new PokemonListItemDto($item['name'], $item['url']),
                $data['results']
            ),
        );
    }

    public function result(): PokemonListResponseDto
    {
        if ($this->result === null) {
            throw new \RuntimeException('Action not executed');
        }
        return $this->result;
    }
}
