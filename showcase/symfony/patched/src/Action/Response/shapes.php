<?php

/**
 * API Response Shape Definitions
 *
 * These shapes define the structure of API responses.
 * They are used for:
 * - Type checking at runtime
 * - API documentation generation via reflection
 */

namespace App\Action\Response;

// Salary information
shape SalaryRange = array{
    min: ?int,
    max: ?int,
    currency: ?string,
    formatted: string
};

// Basic job information for list views
shape JobResponse = array{
    id: int,
    title: string,
    company_name: string,
    company_logo: ?string,
    location: string,
    remote: bool,
    job_type: string,
    salary: SalaryRange,
    url: string,
    tags: array<string>,
    source: string,
    posted_at: ?string
};

// Full job details including description
shape JobDetailResponse = array{
    id: int,
    title: string,
    company_name: string,
    company_logo: ?string,
    location: string,
    remote: bool,
    job_type: string,
    salary: SalaryRange,
    url: string,
    tags: array<string>,
    source: string,
    posted_at: ?string,
    description: string,
    fetched_at: ?string
};

// Pagination metadata
shape PaginationMeta = array{
    current_page: int,
    per_page: int,
    total: int,
    last_page: int
};

// Paginated list of jobs
shape JobListResponse = array{
    data: array<JobResponse>,
    meta: PaginationMeta
};

// Job statistics
shape JobStatsResponse = array{
    total_jobs: int,
    by_source: array<string, int>,
    by_type: array<string, int>,
    remote_jobs: int,
    with_salary: int,
    last_fetched_at: ?string
};

// Error response
shape ErrorResponse = array{
    error: string,
    code: int
};

// ============================================
// PokeAPI Response Shapes
// ============================================

// Pokemon stat (hp, attack, defense, etc.)
shape PokemonStat = array{
    name: string,
    base_stat: int,
    effort: int
};

// Pokemon type (fire, water, grass, etc.)
shape PokemonType = array{
    slot: int,
    name: string
};

// Pokemon ability
shape PokemonAbility = array{
    name: string,
    is_hidden: bool,
    slot: int
};

// Pokemon sprites/images
shape PokemonSprites = array{
    front_default: ?string,
    front_shiny: ?string,
    back_default: ?string,
    back_shiny: ?string,
    official_artwork: ?string
};

// Single Pokemon response
shape PokemonResponse = array{
    id: int,
    name: string,
    height: int,
    weight: int,
    base_experience: ?int,
    types: array<PokemonType>,
    stats: array<PokemonStat>,
    abilities: array<PokemonAbility>,
    sprites: PokemonSprites
};

// Pokemon list item (from paginated list)
shape PokemonListItem = array{
    name: string,
    url: string
};

// Paginated Pokemon list response
shape PokemonListResponse = array{
    count: int,
    next: ?string,
    previous: ?string,
    results: array<PokemonListItem>
};
