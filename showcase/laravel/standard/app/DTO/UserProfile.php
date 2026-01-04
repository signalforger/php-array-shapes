<?php

namespace App\DTO;

/**
 * Profile DTO with userland type validation.
 */
readonly class UserProfile
{
    public function __construct(
        public string $gender,
        public int $age,
        public string $nationality,
    ) {}

    public static function fromArray(array $user): self
    {
        return new self(
            gender: (string) ($user['gender'] ?? ''),
            age: (int) ($user['dob']['age'] ?? 0),
            nationality: (string) ($user['nat'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'gender' => $this->gender,
            'age' => $this->age,
            'nationality' => $this->nationality,
        ];
    }
}
