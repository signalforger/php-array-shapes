<?php

/**
 * PokemonList DTO
 *
 * Paginated list of Pokemon from PokeAPI.
 */

namespace App\DTO;

readonly class PokemonList
{
    /**
     * @param array<PokemonListItem> $pokemon
     */
    public function __construct(
        public int $count,
        public ?string $nextUrl,
        public ?string $previousUrl,
        public array $pokemon,
    ) {}

    /**
     * Create from shape-validated PokeAPI list response
     */
    public static function fromShape(array $data): self
    {
        return new self(
            count: $data['count'],
            nextUrl: $data['next'],
            previousUrl: $data['previous'],
            pokemon: array_map(
                fn($item) => PokemonListItem::fromShape($item),
                $data['results']
            ),
        );
    }

    /**
     * Check if there are more Pokemon
     */
    public function hasNext(): bool
    {
        return $this->nextUrl !== null;
    }

    /**
     * Check if there are previous Pokemon
     */
    public function hasPrevious(): bool
    {
        return $this->previousUrl !== null;
    }

    /**
     * Get count of Pokemon in this page
     */
    public function pageCount(): int
    {
        return count($this->pokemon);
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'next' => $this->nextUrl,
            'previous' => $this->previousUrl,
            'results' => array_map(fn($p) => $p->toArray(), $this->pokemon),
        ];
    }
}
