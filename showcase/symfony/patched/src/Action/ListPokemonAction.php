<?php

namespace App\Action;

use App\Action\Request\ListPokemonRequest;
use App\Action\Response\PokemonListResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Action to list Pokemon from PokeAPI.
 *
 * @api GET /api/pokemon
 */
class ListPokemonAction implements ActionInterface
{
    private ?PokemonListResponse $result = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ListPokemonRequest $request,
    ) {}

    public function execute(): void
    {
        $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon', [
            'query' => [
                'limit' => $this->request->limit,
                'offset' => $this->request->offset,
            ],
        ]);

        $data = $response->toArray();

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
