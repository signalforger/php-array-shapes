<?php

namespace App\Http\Controllers\Api;

use App\Action\GetPokemonAction;
use App\Action\ListPokemonAction;
use App\Action\Request\GetPokemonRequest;
use App\Action\Request\ListPokemonRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pokemon API Controller - fetches data from PokeAPI.
 *
 * Demonstrates typed array shapes with external API data.
 */
class PokemonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actionRequest = new ListPokemonRequest(
            limit: $request->integer('limit', 20),
            offset: $request->integer('offset', 0),
        );

        $action = new ListPokemonAction($actionRequest);
        $action->execute();

        return response()->json($action->result());
    }

    public function show(string $nameOrId): JsonResponse
    {
        $actionRequest = new GetPokemonRequest(nameOrId: $nameOrId);

        $action = new GetPokemonAction($actionRequest);
        $action->execute();

        if ($action->isNotFound()) {
            return response()->json(['error' => 'Pokemon not found', 'code' => 404], 404);
        }

        return response()->json($action->result());
    }
}
