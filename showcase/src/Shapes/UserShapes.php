<?php
/**
 * Shape definitions for User-related data structures.
 *
 * These shapes define the exact structure of data returned from
 * database queries and API responses.
 */

// Basic user record from database
shape UserRecord = array{
    id: int,
    name: string,
    email: string,
    created_at: string,
    is_active?: bool
};

// User with profile information
shape UserWithProfile = array{
    id: int,
    name: string,
    email: string,
    profile: array{
        avatar_url: ?string,
        bio: ?string,
        location: ?string
    }
};

// API response wrapper
shape ApiResponse = array{
    success: bool,
    data: mixed,
    message?: string,
    errors?: array<string>
};

// Paginated response
shape PaginatedUsers = array{
    data: array<UserRecord>,
    meta: array{
        current_page: int,
        per_page: int,
        total: int,
        last_page: int
    }
};
