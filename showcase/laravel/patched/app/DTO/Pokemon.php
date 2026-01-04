<?php

/**
 * Pokemon DTO
 *
 * Represents a Pokemon with helper methods for game mechanics.
 * Demonstrates converting external API shapes to internal DTOs.
 */

namespace App\DTO;

readonly class Pokemon
{
    /**
     * @param array<array{name: string, baseStat: int, effort: int}> $stats
     * @param array<array{slot: int, name: string}> $types
     * @param array<array{name: string, isHidden: bool, slot: int}> $abilities
     */
    public function __construct(
        public int $id,
        public string $name,
        public int $height,
        public int $weight,
        public ?int $baseExperience,
        public array $types,
        public array $stats,
        public array $abilities,
        public ?string $spriteDefault,
        public ?string $spriteShiny,
        public ?string $officialArtwork,
    ) {}

    /**
     * Create from shape-validated PokeAPI response
     */
    public static function fromShape(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            height: $data['height'],
            weight: $data['weight'],
            baseExperience: $data['base_experience'],
            types: array_map(fn($t) => [
                'slot' => $t['slot'],
                'name' => $t['type']['name'],
            ], $data['types']),
            stats: array_map(fn($s) => [
                'name' => $s['stat']['name'],
                'baseStat' => $s['base_stat'],
                'effort' => $s['effort'],
            ], $data['stats']),
            abilities: array_map(fn($a) => [
                'name' => $a['ability']['name'],
                'isHidden' => $a['is_hidden'],
                'slot' => $a['slot'],
            ], $data['abilities']),
            spriteDefault: $data['sprites']['front_default'],
            spriteShiny: $data['sprites']['front_shiny'],
            // Note: The raw PokeAPI uses "official-artwork", but it's normalized to
            // official_artwork before shape validation
            officialArtwork: $data['sprites']['other']['official_artwork']['front_default'] ?? null,
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
     * Get primary type name
     */
    public function primaryType(): string
    {
        foreach ($this->types as $type) {
            if ($type['slot'] === 1) {
                return $type['name'];
            }
        }

        return $this->types[0]['name'] ?? 'unknown';
    }

    /**
     * Get all type names
     */
    public function typeNames(): array
    {
        return array_map(fn($t) => $t['name'], $this->types);
    }

    /**
     * Get base stat by name
     */
    public function getStat(string $name): ?int
    {
        foreach ($this->stats as $stat) {
            if ($stat['name'] === $name) {
                return $stat['baseStat'];
            }
        }

        return null;
    }

    /**
     * Get total base stats
     */
    public function baseStatTotal(): int
    {
        return array_sum(array_column($this->stats, 'baseStat'));
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
     * Get non-hidden abilities
     */
    public function regularAbilities(): array
    {
        return array_filter($this->abilities, fn($a) => !$a['isHidden']);
    }

    /**
     * Get hidden ability if any
     */
    public function hiddenAbility(): ?array
    {
        foreach ($this->abilities as $ability) {
            if ($ability['isHidden']) {
                return $ability;
            }
        }

        return null;
    }

    /**
     * Check if Pokemon has a specific type
     */
    public function hasType(string $type): bool
    {
        return in_array(strtolower($type), array_map('strtolower', $this->typeNames()));
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'height' => $this->height,
            'height_meters' => $this->heightInMeters(),
            'weight' => $this->weight,
            'weight_kg' => $this->weightInKg(),
            'base_experience' => $this->baseExperience,
            'types' => array_map(fn($t) => [
                'slot' => $t['slot'],
                'name' => $t['name'],
            ], $this->types),
            'stats' => array_map(fn($s) => [
                'name' => $s['name'],
                'base_stat' => $s['baseStat'],
                'effort' => $s['effort'],
            ], $this->stats),
            'base_stat_total' => $this->baseStatTotal(),
            'abilities' => array_map(fn($a) => [
                'name' => $a['name'],
                'is_hidden' => $a['isHidden'],
                'slot' => $a['slot'],
            ], $this->abilities),
            'sprites' => [
                'front_default' => $this->spriteDefault,
                'front_shiny' => $this->spriteShiny,
                'official_artwork' => $this->officialArtwork,
            ],
        ];
    }
}
