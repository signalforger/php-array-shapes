<?php

namespace App\Action\Response;

/**
 * Pokemon list item (from paginated list)
 */
readonly class PokemonListItemDto
{
    public function __construct(
        public string $name,
        public string $url,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
        ];
    }
}
