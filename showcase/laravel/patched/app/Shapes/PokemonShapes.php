<?php

/**
 * Pokemon Shape Definitions
 *
 * These shapes define the structure of PokeAPI responses.
 * They demonstrate using shapes to validate external API data
 * before converting to internal DTOs.
 */

namespace App\Shapes;

// ============================================
// Pokemon API Response Shapes
// ============================================

// Raw stat from PokeAPI
shape PokeApiStat = array{
    base_stat: int,
    effort: int,
    stat: array{
        name: string,
        url: string
    }
};

// Raw type from PokeAPI
shape PokeApiType = array{
    slot: int,
    type: array{
        name: string,
        url: string
    }
};

// Raw ability from PokeAPI
shape PokeApiAbility = array{
    ability: array{
        name: string,
        url: string
    },
    is_hidden: bool,
    slot: int
};

// Raw sprites from PokeAPI
// Note: PokeAPI uses "official-artwork" as key, but shapes require valid identifiers
// When consuming this API, map "official-artwork" to official_artwork before validation
shape PokeApiSprites = array{
    front_default: ?string,
    front_shiny: ?string,
    back_default: ?string,
    back_shiny: ?string,
    other: array{
        official_artwork: array{
            front_default: ?string
        }
    }
};

// Full Pokemon response from PokeAPI
shape PokeApiPokemon = array{
    id: int,
    name: string,
    height: int,
    weight: int,
    base_experience: ?int,
    types: array<PokeApiType>,
    stats: array<PokeApiStat>,
    abilities: array<PokeApiAbility>,
    sprites: PokeApiSprites
};

// Pokemon list item from PokeAPI
shape PokeApiListItem = array{
    name: string,
    url: string
};

// Pokemon list response from PokeAPI
shape PokeApiList = array{
    count: int,
    next: ?string,
    previous: ?string,
    results: array<PokeApiListItem>
};

