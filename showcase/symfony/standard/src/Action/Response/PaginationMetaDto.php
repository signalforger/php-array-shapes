<?php

namespace App\Action\Response;

/**
 * Pagination metadata.
 */
readonly class PaginationMetaDto implements \JsonSerializable
{
    public function __construct(
        public int $currentPage,
        public int $perPage,
        public int $total,
        public int $lastPage,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'last_page' => $this->lastPage,
        ];
    }
}
