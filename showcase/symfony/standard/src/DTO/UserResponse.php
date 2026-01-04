<?php

namespace App\DTO;

/**
 * User Response DTO with userland type validation.
 */
readonly class UserResponse
{
    public function __construct(
        public string $id,
        public string $displayName,
        public string $email,
        public ?string $avatar,
        public UserProfile $profile,
        public UserContact $contact,
        public UserAddress $address,
    ) {}

    public static function fromArray(array $user): self
    {
        $name = $user['name'] ?? [];
        $location = $user['location'] ?? [];

        return new self(
            id: (string) ($user['login']['uuid'] ?? ''),
            displayName: trim(($name['title'] ?? '') . ' ' . ($name['first'] ?? '') . ' ' . ($name['last'] ?? '')),
            email: (string) ($user['email'] ?? ''),
            avatar: $user['picture']['large'] ?? null,
            profile: UserProfile::fromArray($user),
            contact: UserContact::fromArray($user),
            address: UserAddress::fromArray($location),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->displayName,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'profile' => $this->profile->toArray(),
            'contact' => $this->contact->toArray(),
            'address' => $this->address->toArray(),
        ];
    }
}
