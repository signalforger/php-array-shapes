<?php

/**
 * PokemonListItem DTO
 *
 * A lightweight Pokemon reference from the list endpoint.
 */

namespace App\DTO;

readonly class PokemonListItem
{
    public function __construct(
        public string $name,
        public string $url,
    ) {}

    /**
     * Create from shape-validated list item
     */
    public static function fromShape(array $data): self
    {
        return new self(
            name: $data['name'],
            url: $data['url'],
        );
    }

    /**
     * Get display name (capitalized)
     */
    public function displayName(): string
    {
        return ucfirst($this->name);
    }

    /**
     * Extract ID from URL
     */
    public function id(): int
    {
        preg_match('/\/pokemon\/(\d+)\/?$/', $this->url, $matches);
        return (int) ($matches[1] ?? 0);
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'url' => $this->url,
        ];
    }
}
