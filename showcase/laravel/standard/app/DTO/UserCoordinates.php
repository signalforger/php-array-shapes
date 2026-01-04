<?php

namespace App\DTO;

/**
 * Coordinates DTO with userland type validation.
 */
readonly class UserCoordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            latitude: (float) ($data['latitude'] ?? 0),
            longitude: (float) ($data['longitude'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
