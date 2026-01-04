<?php

namespace App\Action\Response;

/**
 * Pokemon sprites/images
 */
readonly class PokemonSpritesDto
{
    public function __construct(
        public ?string $front_default,
        public ?string $front_shiny,
        public ?string $back_default,
        public ?string $back_shiny,
        public ?string $official_artwork,
    ) {}

    public function toArray(): array
    {
        return [
            'front_default' => $this->front_default,
            'front_shiny' => $this->front_shiny,
            'back_default' => $this->back_default,
            'back_shiny' => $this->back_shiny,
            'official_artwork' => $this->official_artwork,
        ];
    }
}
