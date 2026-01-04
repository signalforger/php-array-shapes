<?php

/**
 * API Response Shape Definitions
 *
 * These shapes define the structure of API responses.
 * They are used for:
 * - Type checking at runtime
 * - API documentation generation via reflection
 *
 * Shape Inheritance:
 * - JobDetailResponse extends JobResponse (adds description, fetched_at)
 * - Uses ::shape syntax for referencing shape names
 */

namespace App\Action\Response;

// ============================================
// Base Shapes
// ============================================

// Salary information
shape SalaryRange = array{
    min: ?int,
    max: ?int,
    currency: ?string,
    formatted: string
};

// Pagination metadata (reusable across different list responses)
shape PaginationMeta = array{
    current_page: int,
    per_page: int,
    total: int,
    last_page: int
};

// ============================================
// Job Response Shapes (with inheritance)
// ============================================

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

// Full job details - extends JobResponse with additional fields
// At compile time, this flattens to include all JobResponse fields plus description and fetched_at
shape JobDetailResponse extends JobResponse = array{
    description: string,
    fetched_at: ?string
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

// ============================================
// Error/Status Responses
// ============================================

// Base error response
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

// Base Pokemon info (for list items)
shape PokemonListItem = array{
    name: string,
    url: string
};

// Single Pokemon response - full details
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

// Paginated Pokemon list response
shape PokemonListResponse = array{
    count: int,
    next: ?string,
    previous: ?string,
    results: array<PokemonListItem>
};
