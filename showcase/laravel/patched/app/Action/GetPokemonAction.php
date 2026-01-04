<?php

namespace App\Action;

use App\Action\Request\GetPokemonRequest;
use App\Action\Response\PokemonResponse;
use App\Action\Response\PokemonStat;
use App\Action\Response\PokemonType;
use App\Action\Response\PokemonAbility;
use App\Action\Response\PokemonSprites;
use Illuminate\Support\Facades\Http;

/**
 * Action to get a single Pokemon from PokeAPI.
 *
 * @api GET /api/pokemon/{nameOrId}
 */
class GetPokemonAction implements ActionInterface
{
    private ?PokemonResponse $result = null;
    private bool $notFound = false;

    public function __construct(
        private readonly GetPokemonRequest $request,
    ) {}

    public function execute(): void
    {
        $response = Http::get('https://pokeapi.co/api/v2/pokemon/' . strtolower($this->request->nameOrId));

        if ($response->status() === 404) {
            $this->notFound = true;
            return;
        }

        $data = $response->json();

        $this->result = [
            'id' => $data['id'],
            'name' => $data['name'],
            'height' => $data['height'],
            'weight' => $data['weight'],
            'base_experience' => $data['base_experience'],
            'types' => $this->formatTypes($data['types']),
            'stats' => $this->formatStats($data['stats']),
            'abilities' => $this->formatAbilities($data['abilities']),
            'sprites' => $this->formatSprites($data['sprites']),
        ];
    }

    public function result(): PokemonResponse
    {
        if ($this->result === null) {
            throw new \RuntimeException('Pokemon not found or action not executed');
        }
        return $this->result;
    }

    public function isNotFound(): bool
    {
        return $this->notFound;
    }

    /**
     * @return array<PokemonType>
     */
    private function formatTypes(array $types): array
    {
        return array_map(fn($t): PokemonType => [
            'slot' => $t['slot'],
            'name' => $t['type']['name'],
        ], $types);
    }

    /**
     * @return array<PokemonStat>
     */
    private function formatStats(array $stats): array
    {
        return array_map(fn($s): PokemonStat => [
            'name' => $s['stat']['name'],
            'base_stat' => $s['base_stat'],
            'effort' => $s['effort'],
        ], $stats);
    }

    /**
     * @return array<PokemonAbility>
     */
    private function formatAbilities(array $abilities): array
    {
        return array_map(fn($a): PokemonAbility => [
            'name' => $a['ability']['name'],
            'is_hidden' => $a['is_hidden'],
            'slot' => $a['slot'],
        ], $abilities);
    }

    private function formatSprites(array $sprites): PokemonSprites
    {
        return [
            'front_default' => $sprites['front_default'],
            'front_shiny' => $sprites['front_shiny'],
            'back_default' => $sprites['back_default'],
            'back_shiny' => $sprites['back_shiny'],
            'official_artwork' => $sprites['other']['official-artwork']['front_default'] ?? null,
        ];
    }
}
