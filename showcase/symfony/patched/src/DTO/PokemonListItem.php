<?php

/**
 * PokemonListItem DTO
 *
 * Represents a Pokemon in a list (minimal info).
 * DTOs contain behavior (methods) unlike shapes which are pure data structures.
 */

namespace App\DTO;

readonly class PokemonListItem
{
    public function __construct(
        public string $name,
        public string $url,
    ) {}

    /**
     * Create from shape-validated array
     */
    public static function fromShape(array $data): self
    {
        return new self(
            name: $data['name'],
            url: $data['url'],
        );
    }

    /**
     * Extract Pokemon ID from URL
     */
    public function id(): int
    {
        preg_match('/\/pokemon\/(\d+)\/$/', $this->url, $matches);
        return (int) ($matches[1] ?? 0);
    }

    /**
     * Get formatted name (capitalized)
     */
    public function formattedName(): string
    {
        return ucfirst($this->name);
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name,
            'formatted_name' => $this->formattedName(),
            'url' => $this->url,
        ];
    }
}
