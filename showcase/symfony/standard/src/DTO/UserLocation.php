<?php

namespace App\DTO;

/**
 * Location DTO with userland type validation.
 */
readonly class UserLocation
{
    public function __construct(
        public string $street,
        public string $city,
        public string $state,
        public string $country,
        public string $postcode,
        public UserCoordinates $coordinates,
        public string $timezone,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            street: trim(($data['street']['number'] ?? '') . ' ' . ($data['street']['name'] ?? '')),
            city: (string) ($data['city'] ?? ''),
            state: (string) ($data['state'] ?? ''),
            country: (string) ($data['country'] ?? ''),
            postcode: (string) ($data['postcode'] ?? ''),
            coordinates: UserCoordinates::fromArray($data['coordinates'] ?? []),
            timezone: (string) ($data['timezone']['description'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postcode' => $this->postcode,
            'coordinates' => $this->coordinates->toArray(),
            'timezone' => $this->timezone,
        ];
    }
}
