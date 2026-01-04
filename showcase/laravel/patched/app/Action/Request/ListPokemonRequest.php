<?php

namespace App\Action\Request;

/**
 * Request parameters for listing Pokemon.
 */
readonly class ListPokemonRequest
{
    public function __construct(
        public int $limit = 20,
        public int $offset = 0,
    ) {}
}
