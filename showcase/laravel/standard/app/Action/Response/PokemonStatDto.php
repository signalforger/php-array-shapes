<?php

namespace App\Action\Response;

/**
 * Pokemon stat (hp, attack, defense, etc.)
 */
readonly class PokemonStatDto
{
    public function __construct(
        public string $name,
        public int $base_stat,
        public int $effort,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'base_stat' => $this->base_stat,
            'effort' => $this->effort,
        ];
    }
}
