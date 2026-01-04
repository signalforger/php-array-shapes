<?php

namespace App\DTO;

/**
 * Contact DTO with userland type validation.
 */
readonly class UserContact
{
    public function __construct(
        public string $phone,
        public string $cell,
    ) {}

    public static function fromArray(array $user): self
    {
        return new self(
            phone: (string) ($user['phone'] ?? ''),
            cell: (string) ($user['cell'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'phone' => $this->phone,
            'cell' => $this->cell,
        ];
    }
}
