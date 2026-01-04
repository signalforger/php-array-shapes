<?php

/**
 * GetJobAction - Retrieve a single job by ID
 *
 * Demonstrates the boundary pattern:
 * - Input: Shape-validated request data (GetJobRequest shape)
 * - Output: DTO with business logic (JobDetail)
 *
 * This action is a pure class - no interface, no parent.
 * Parameters come from execute() method, not constructor.
 */

namespace App\Action;

use App\DTO\JobDetail;
use App\Models\JobListing;
use App\Shapes\GetJobRequest;

class GetJobAction
{
    /**
     * Execute the action
     *
     * @param GetJobRequest $request Shape-validated request data
     * @return JobDetail|null Returns DTO or null if not found
     */
    public function execute(GetJobRequest $request): ?JobDetail
    {
        $job = JobListing::find($request['id']);

        if ($job === null) {
            return null;
        }

        return JobDetail::fromModel($job);
    }
}
