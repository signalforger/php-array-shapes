<?php

namespace App\DTO;

/**
 * Address DTO with userland type validation.
 */
readonly class UserAddress
{
    public function __construct(
        public string $formatted,
        public string $city,
        public string $country,
    ) {}

    public static function fromArray(array $location): self
    {
        $parts = array_filter([
            trim(($location['street']['number'] ?? '') . ' ' . ($location['street']['name'] ?? '')),
            $location['city'] ?? '',
            $location['state'] ?? '',
            (string) ($location['postcode'] ?? ''),
            $location['country'] ?? '',
        ]);

        return new self(
            formatted: implode(', ', $parts),
            city: (string) ($location['city'] ?? ''),
            country: (string) ($location['country'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'formatted' => $this->formatted,
            'city' => $this->city,
            'country' => $this->country,
        ];
    }
}
