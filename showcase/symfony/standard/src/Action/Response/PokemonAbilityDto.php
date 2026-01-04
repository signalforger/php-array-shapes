<?php

namespace App\Action\Response;

/**
 * Pokemon ability
 */
readonly class PokemonAbilityDto
{
    public function __construct(
        public string $name,
        public bool $is_hidden,
        public int $slot,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'is_hidden' => $this->is_hidden,
            'slot' => $this->slot,
        ];
    }
}
