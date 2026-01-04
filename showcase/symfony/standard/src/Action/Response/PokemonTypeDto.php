<?php

namespace App\Action\Response;

/**
 * Pokemon type (fire, water, grass, etc.)
 */
readonly class PokemonTypeDto
{
    public function __construct(
        public int $slot,
        public string $name,
    ) {}

    public function toArray(): array
    {
        return [
            'slot' => $this->slot,
            'name' => $this->name,
        ];
    }
}
