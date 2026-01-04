<?php

namespace App\Controller\Api;

use App\Action\GetPokemonAction;
use App\Action\ListPokemonAction;
use App\Action\Request\GetPokemonRequest;
use App\Action\Request\ListPokemonRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pokemon API Controller - fetches data from PokeAPI.
 *
 * Demonstrates typed array shapes with external API data.
 */
#[Route('/api/pokemon')]
class PokemonController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    #[Route('', name: 'api_pokemon_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $actionRequest = new ListPokemonRequest(
            limit: $request->query->getInt('limit', 20),
            offset: $request->query->getInt('offset', 0),
        );

        $action = new ListPokemonAction($this->httpClient, $actionRequest);
        $action->execute();

        return $this->json($action->result());
    }

    #[Route('/{nameOrId}', name: 'api_pokemon_get', methods: ['GET'])]
    public function get(string $nameOrId): JsonResponse
    {
        $actionRequest = new GetPokemonRequest(nameOrId: $nameOrId);

        $action = new GetPokemonAction($this->httpClient, $actionRequest);
        $action->execute();

        if ($action->isNotFound()) {
            return $this->json(['error' => 'Pokemon not found', 'code' => 404], 404);
        }

        return $this->json($action->result());
    }
}
