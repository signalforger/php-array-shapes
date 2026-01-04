<?php

/**
 * Request Shape Definitions
 *
 * These shapes define the structure of incoming API requests.
 * They validate user input at the application boundary before
 * processing by action classes.
 *
 * Usage pattern:
 * 1. Controller receives raw request data (array from JSON body)
 * 2. Data is validated against request shape
 * 3. Validated data passed to Action::execute()
 * 4. Action returns a DTO
 */

namespace App\Shapes;

// ============================================
// Job Request Shapes
// ============================================

// Request to get a single job
shape GetJobRequest = array{
    id: int
};

// Request to list jobs with filters
shape ListJobsRequest = array{
    q?: string,
    remote?: bool,
    job_type?: string,
    location?: string,
    source?: string,
    min_salary?: int,
    max_salary?: int,
    page?: int,
    per_page?: int
};

// Request to search jobs (simpler than list)
shape SearchJobsRequest = array{
    q: string,
    page?: int,
    per_page?: int
};

// ============================================
// Pokemon Request Shapes (demo API)
// ============================================

shape GetPokemonRequest = array{
    id: int|string
};

shape ListPokemonRequest = array{
    limit?: int,
    offset?: int
};

// ============================================
// Webhook Request Shapes
// ============================================

// Generic webhook payload structure
shape WebhookPayload = array{
    event: string,
    timestamp: string,
    data: mixed,
    signature?: string
};

// Job posted webhook
shape JobPostedWebhook extends WebhookPayload = array{
    data: array{
        job_id: string,
        source: string,
        title: string,
        company: string,
        url: string
    }
};

// Job expired webhook
shape JobExpiredWebhook extends WebhookPayload = array{
    data: array{
        job_id: string,
        source: string,
        reason: string
    }
};
