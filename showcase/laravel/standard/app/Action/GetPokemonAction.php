<?php

namespace App\Action;

use App\Action\Request\GetPokemonRequest;
use App\Action\Response\PokemonAbilityDto;
use App\Action\Response\PokemonResponseDto;
use App\Action\Response\PokemonSpritesDto;
use App\Action\Response\PokemonStatDto;
use App\Action\Response\PokemonTypeDto;
use Illuminate\Support\Facades\Http;

/**
 * Action to get a single Pokemon from PokeAPI.
 *
 * @api GET /api/pokemon/{nameOrId}
 */
class GetPokemonAction implements ActionInterface
{
    private ?PokemonResponseDto $result = null;
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

        $this->result = new PokemonResponseDto(
            id: $data['id'],
            name: $data['name'],
            height: $data['height'],
            weight: $data['weight'],
            base_experience: $data['base_experience'],
            types: $this->formatTypes($data['types']),
            stats: $this->formatStats($data['stats']),
            abilities: $this->formatAbilities($data['abilities']),
            sprites: $this->formatSprites($data['sprites']),
        );
    }

    public function result(): PokemonResponseDto
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
     * @return array<PokemonTypeDto>
     */
    private function formatTypes(array $types): array
    {
        return array_map(
            fn($t) => new PokemonTypeDto($t['slot'], $t['type']['name']),
            $types
        );
    }

    /**
     * @return array<PokemonStatDto>
     */
    private function formatStats(array $stats): array
    {
        return array_map(
            fn($s) => new PokemonStatDto($s['stat']['name'], $s['base_stat'], $s['effort']),
            $stats
        );
    }

    /**
     * @return array<PokemonAbilityDto>
     */
    private function formatAbilities(array $abilities): array
    {
        return array_map(
            fn($a) => new PokemonAbilityDto($a['ability']['name'], $a['is_hidden'], $a['slot']),
            $abilities
        );
    }

    private function formatSprites(array $sprites): PokemonSpritesDto
    {
        return new PokemonSpritesDto(
            front_default: $sprites['front_default'],
            front_shiny: $sprites['front_shiny'],
            back_default: $sprites['back_default'],
            back_shiny: $sprites['back_shiny'],
            official_artwork: $sprites['other']['official-artwork']['front_default'] ?? null,
        );
    }
}
