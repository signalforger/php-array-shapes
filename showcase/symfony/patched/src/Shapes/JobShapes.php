<?php

/**
 * Job-Related Shape Definitions
 *
 * These shapes define the structure of job data at application boundaries.
 * They are used to validate:
 * - External API responses (job boards, aggregators)
 * - Webhook payloads
 * - User input for job searches
 *
 * After validation, data is converted to DTOs for internal processing.
 *
 * Shape Inheritance:
 * - JobData: Base job information
 * - JobDetailData extends JobData: Adds description, timestamps
 */

namespace App\Shapes;

// Import base shapes
require_once __DIR__ . '/BaseShapes.php';

// ============================================
// Job Data Shapes (from external sources)
// ============================================

// Base job data - common fields for all job representations
shape JobData = array{
    id: int|string,
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

// Detailed job data - extends base with full description
shape JobDetailData extends JobData = array{
    description: string,
    fetched_at: ?string,
    application_url?: string,
    company_url?: string
};

// ============================================
// Job List Response Shape
// ============================================

shape JobListData = array{
    data: array<JobData>,
    meta: PaginationMeta
};

// ============================================
// External Provider Response Shapes
// ============================================

// Arbeitnow API response format
shape ArbeitnowJobData = array{
    slug: string,
    title: string,
    company_name: string,
    location: string,
    remote: bool,
    job_types: array<string>,
    description: string,
    url: string,
    tags: array<string>,
    created_at: string
};

shape ArbeitnowResponse = array{
    data: array<ArbeitnowJobData>,
    links: array{
        first: ?string,
        last: ?string,
        prev: ?string,
        next: ?string
    },
    meta: array{
        current_page: int,
        last_page: int,
        per_page: int,
        total: int
    }
};

// Remotive API response format
shape RemotiveJobData = array{
    id: int,
    title: string,
    company_name: string,
    company_logo: ?string,
    category: string,
    job_type: string,
    publication_date: string,
    candidate_required_location: string,
    salary: string,
    description: string,
    url: string,
    tags: array<string>
};

// Note: Remotive API uses "job-count" as key, but shapes require valid identifiers
// When consuming this API, map "job-count" to job_count before validation
shape RemotiveResponse = array{
    job_count: int,
    jobs: array<RemotiveJobData>
};

// JSearch API response format
shape JSearchJobData = array{
    job_id: string,
    job_title: string,
    employer_name: string,
    employer_logo: ?string,
    job_city: ?string,
    job_country: string,
    job_employment_type: string,
    job_min_salary: ?int,
    job_max_salary: ?int,
    job_salary_currency: ?string,
    job_description: string,
    job_apply_link: string,
    job_posted_at_datetime_utc: string,
    job_is_remote: bool
};

shape JSearchResponse = array{
    status: string,
    data: array<JSearchJobData>
};
