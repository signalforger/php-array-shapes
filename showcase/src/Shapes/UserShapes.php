<?php
/**
 * Shape definitions for User-related data structures.
 *
 * These shapes define the exact structure of data returned from
 * database queries and API responses.
 *
 * Shape Inheritance:
 * - BaseUser: Common user fields (id, name, email)
 * - UserRecord extends BaseUser: Database record with timestamps
 * - UserWithProfile extends BaseUser: User with profile information
 * - SuccessResponse extends ApiResponse: Successful API response
 * - ErrorApiResponse extends ApiResponse: Error API response
 */

// ============================================
// Base Shapes
// ============================================

// Base user fields common to all user shapes
shape BaseUser = array{
    id: int,
    name: string,
    email: string
};

// Profile information (nested shape)
shape UserProfile = array{
    avatar_url: ?string,
    bio: ?string,
    location: ?string
};

// Pagination metadata
shape PaginationMeta = array{
    current_page: int,
    per_page: int,
    total: int,
    last_page: int
};

// ============================================
// User Shapes (with inheritance)
// ============================================

// User record from database - extends BaseUser with timestamps
shape UserRecord extends BaseUser = array{
    created_at: string,
    is_active?: bool
};

// User with profile information - extends BaseUser with profile data
shape UserWithProfile extends BaseUser = array{
    profile: UserProfile
};

// ============================================
// API Response Shapes (with inheritance)
// ============================================

// Base API response wrapper
shape ApiResponse = array{
    success: bool,
    data: mixed,
    message?: string
};

// Successful response with optional errors field
shape SuccessResponse extends ApiResponse = array{
    errors?: array<string>
};

// Error response - always has errors
shape ErrorApiResponse extends ApiResponse = array{
    errors: array<string>
};

// ============================================
// Paginated Responses
// ============================================

// Paginated response for user records
shape PaginatedUsers = array{
    data: array<UserRecord>,
    meta: PaginationMeta
};
