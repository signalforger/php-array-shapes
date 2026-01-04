<?php

namespace App\DTO;

/**
 * Picture DTO with userland type validation.
 */
readonly class UserPicture
{
    public function __construct(
        public ?string $large,
        public ?string $medium,
        public ?string $thumbnail,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            large: $data['large'] ?? null,
            medium: $data['medium'] ?? null,
            thumbnail: $data['thumbnail'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'large' => $this->large,
            'medium' => $this->medium,
            'thumbnail' => $this->thumbnail,
        ];
    }
}
