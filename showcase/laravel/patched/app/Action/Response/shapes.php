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
