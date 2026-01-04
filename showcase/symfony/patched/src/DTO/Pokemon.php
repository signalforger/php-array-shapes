<?php

/**
 * Pokemon DTO
 *
 * Represents a Pokemon with game mechanics methods.
 * DTOs contain behavior (methods) unlike shapes which are pure data structures.
 */

namespace App\DTO;

readonly class Pokemon
{
    public function __construct(
        public int $id,
        public string $name,
        public int $height,
        public int $weight,
        public ?int $baseExperience,
        public array $types,
        public array $stats,
        public array $abilities,
        public array $sprites,
    ) {}

    /**
     * Create from PokeAPI shape-validated response
     */
    public static function fromShape(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            height: $data['height'],
            weight: $data['weight'],
            baseExperience: $data['base_experience'],
            types: array_map(fn($t) => $t['type']['name'], $data['types']),
            stats: array_combine(
                array_map(fn($s) => $s['stat']['name'], $data['stats']),
                array_map(fn($s) => $s['base_stat'], $data['stats'])
            ),
            abilities: array_map(fn($a) => [
                'name' => $a['ability']['name'],
                'is_hidden' => $a['is_hidden'],
            ], $data['abilities']),
            sprites: [
                'front_default' => $data['sprites']['front_default'],
                'front_shiny' => $data['sprites']['front_shiny'],
                // Note: The raw PokeAPI uses "official-artwork", but it's normalized to
                // official_artwork before shape validation
                'official_artwork' => $data['sprites']['other']['official_artwork']['front_default'] ?? null,
            ],
        );
    }

    /**
     * Get primary type
     */
    public function primaryType(): string
    {
        return $this->types[0] ?? 'unknown';
    }

    /**
     * Check if Pokemon has a specific type
     */
    public function hasType(string $type): bool
    {
        return in_array(strtolower($type), array_map('strtolower', $this->types), true);
    }

    /**
     * Get a specific stat value
     */
    public function getStat(string $name): ?int
    {
        return $this->stats[$name] ?? null;
    }

    /**
     * Get base stat total (BST)
     */
    public function baseStatTotal(): int
    {
        return array_sum($this->stats);
    }

    /**
     * Get height in meters
     */
    public function heightInMeters(): float
    {
        return $this->height / 10;
    }

    /**
     * Get weight in kilograms
     */
    public function weightInKg(): float
    {
        return $this->weight / 10;
    }

    /**
     * Get hidden ability if exists
     */
    public function hiddenAbility(): ?string
    {
        foreach ($this->abilities as $ability) {
            if ($ability['is_hidden']) {
                return $ability['name'];
            }
        }
        return null;
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
            'id' => $this->id,
            'name' => $this->name,
            'formatted_name' => $this->formattedName(),
            'height' => $this->height,
            'height_meters' => $this->heightInMeters(),
            'weight' => $this->weight,
            'weight_kg' => $this->weightInKg(),
            'base_experience' => $this->baseExperience,
            'types' => $this->types,
            'primary_type' => $this->primaryType(),
            'stats' => $this->stats,
            'base_stat_total' => $this->baseStatTotal(),
            'abilities' => $this->abilities,
            'hidden_ability' => $this->hiddenAbility(),
            'sprites' => $this->sprites,
        ];
    }
}
