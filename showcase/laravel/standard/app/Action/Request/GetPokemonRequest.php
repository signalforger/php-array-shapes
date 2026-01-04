<?php

namespace App\Action\Request;

/**
 * Request parameters for getting a single Pokemon.
 */
readonly class GetPokemonRequest
{
    public function __construct(
        public string $nameOrId,
    ) {}
}
