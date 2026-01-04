<?php

/**
 * Base Shape Definitions
 *
 * Common shapes used across the application. These serve as building blocks
 * for more specific shapes and demonstrate shape composition.
 *
 * Shapes are used at application boundaries to validate incoming data
 * (API requests, webhooks, external service responses) before converting
 * to internal DTOs.
 */

namespace App\Shapes;

// ============================================
// Pagination
// ============================================

shape PaginationMeta = array{
    current_page: int,
    per_page: int,
    total: int,
    last_page: int
};

// ============================================
// Money/Salary
// ============================================

shape SalaryRange = array{
    min: ?int,
    max: ?int,
    currency: ?string
};

// ============================================
// API Response Wrappers
// ============================================

shape ApiError = array{
    message: string,
    code: ?string,
    field?: string
};

shape ApiMeta = array{
    request_id?: string,
    timestamp?: string
};
