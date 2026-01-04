<?php

namespace App\Action\Response;

/**
 * Paginated Pokemon list response
 */
readonly class PokemonListResponseDto
{
    /**
     * @param array<PokemonListItemDto> $results
     */
    public function __construct(
        public int $count,
        public ?string $next,
        public ?string $previous,
        public array $results,
    ) {}

    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'next' => $this->next,
            'previous' => $this->previous,
            'results' => array_map(fn($r) => $r->toArray(), $this->results),
        ];
    }
}
