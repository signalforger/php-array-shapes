<?php

namespace App\DTO;

/**
 * Paginated Users DTO with userland type validation.
 */
readonly class PaginatedUsers
{
    /**
     * @param array<UserResponse> $data
     */
    public function __construct(
        public array $data,
        public int $currentPage,
        public int $perPage,
        public int $total,
        public int $lastPage,
        public int $from,
        public int $to,
    ) {}

    public static function create(
        array $pageUsers,
        int $currentPage,
        int $perPage,
        int $total,
        int $lastPage,
        int $offset
    ): self {
        $data = [];
        foreach ($pageUsers as $user) {
            $data[] = UserResponse::fromArray($user);
        }

        return new self(
            data: $data,
            currentPage: $currentPage,
            perPage: $perPage,
            total: $total,
            lastPage: $lastPage,
            from: $offset + 1,
            to: min($offset + $perPage, $total),
        );
    }

    public function toArray(): array
    {
        return [
            'data' => array_map(fn(UserResponse $u) => $u->toArray(), $this->data),
            'meta' => [
                'current_page' => $this->currentPage,
                'per_page' => $this->perPage,
                'total' => $this->total,
                'last_page' => $this->lastPage,
                'from' => $this->from,
                'to' => $this->to,
            ],
        ];
    }
}
