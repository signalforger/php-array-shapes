<?php

namespace App\DTO;

/**
 * Normalized User DTO with userland type validation.
 */
readonly class NormalizedUser
{
    public function __construct(
        public string $id,
        public string $username,
        public string $email,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public string $gender,
        public int $age,
        public ?string $dateOfBirth,
        public string $phone,
        public string $cell,
        public string $nationality,
        public UserPicture $picture,
        public UserLocation $location,
        public ?string $registeredAt,
        public int $registeredYears,
    ) {}

    public static function fromArray(array $user): self
    {
        return new self(
            id: (string) ($user['login']['uuid'] ?? ''),
            username: (string) ($user['login']['username'] ?? ''),
            email: (string) ($user['email'] ?? ''),
            firstName: (string) ($user['name']['first'] ?? ''),
            lastName: (string) ($user['name']['last'] ?? ''),
            fullName: trim(($user['name']['first'] ?? '') . ' ' . ($user['name']['last'] ?? '')),
            gender: (string) ($user['gender'] ?? ''),
            age: (int) ($user['dob']['age'] ?? 0),
            dateOfBirth: $user['dob']['date'] ?? null,
            phone: (string) ($user['phone'] ?? ''),
            cell: (string) ($user['cell'] ?? ''),
            nationality: (string) ($user['nat'] ?? ''),
            picture: UserPicture::fromArray($user['picture'] ?? []),
            location: UserLocation::fromArray($user['location'] ?? []),
            registeredAt: $user['registered']['date'] ?? null,
            registeredYears: (int) ($user['registered']['age'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'gender' => $this->gender,
            'age' => $this->age,
            'date_of_birth' => $this->dateOfBirth,
            'phone' => $this->phone,
            'cell' => $this->cell,
            'nationality' => $this->nationality,
            'picture' => $this->picture->toArray(),
            'location' => $this->location->toArray(),
            'registered_at' => $this->registeredAt,
            'registered_years' => $this->registeredYears,
        ];
    }
}
