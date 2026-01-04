<?php

namespace App\Action\Response;

/**
 * Single Pokemon response
 */
readonly class PokemonResponseDto
{
    /**
     * @param array<PokemonTypeDto> $types
     * @param array<PokemonStatDto> $stats
     * @param array<PokemonAbilityDto> $abilities
     */
    public function __construct(
        public int $id,
        public string $name,
        public int $height,
        public int $weight,
        public ?int $base_experience,
        public array $types,
        public array $stats,
        public array $abilities,
        public PokemonSpritesDto $sprites,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'height' => $this->height,
            'weight' => $this->weight,
            'base_experience' => $this->base_experience,
            'types' => array_map(fn($t) => $t->toArray(), $this->types),
            'stats' => array_map(fn($s) => $s->toArray(), $this->stats),
            'abilities' => array_map(fn($a) => $a->toArray(), $this->abilities),
            'sprites' => $this->sprites->toArray(),
        ];
    }
}
