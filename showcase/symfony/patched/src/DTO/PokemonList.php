<?php

/**
 * PokemonList DTO
 *
 * Represents a paginated list of Pokemon.
 * DTOs contain behavior (methods) unlike shapes which are pure data structures.
 */

namespace App\DTO;

readonly class PokemonList
{
    /**
     * @param array<PokemonListItem> $pokemon
     */
    public function __construct(
        public int $count,
        public ?string $next,
        public ?string $previous,
        public array $pokemon,
    ) {}

    /**
     * Create from shape-validated array
     */
    public static function fromShape(array $data): self
    {
        return new self(
            count: $data['count'],
            next: $data['next'],
            previous: $data['previous'],
            pokemon: array_map(
                fn(array $item) => PokemonListItem::fromShape($item),
                $data['results']
            ),
        );
    }

    /**
     * Check if there are more results
     */
    public function hasMore(): bool
    {
        return $this->next !== null;
    }

    /**
     * Check if there are previous results
     */
    public function hasPrevious(): bool
    {
        return $this->previous !== null;
    }

    /**
     * Get count of items in this page
     */
    public function pageCount(): int
    {
        return count($this->pokemon);
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'next' => $this->next,
            'previous' => $this->previous,
            'has_more' => $this->hasMore(),
            'results' => array_map(fn(PokemonListItem $item) => $item->toArray(), $this->pokemon),
        ];
    }
}
