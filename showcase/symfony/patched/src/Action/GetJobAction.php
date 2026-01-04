<?php

/**
 * Get Job Action
 *
 * Pure action class that accepts shape-validated input
 * and returns a DTO. No interface or parent class.
 *
 * Pattern:
 * - Shape validates data at boundary (controller)
 * - Action receives validated shape
 * - Action returns DTO with business logic methods
 *
 * @api GET /api/jobs/{id}
 */

namespace App\Action;

use App\DTO\JobDetail;
use App\Repository\JobListingRepository;
use App\Shapes\GetJobRequest;

class GetJobAction
{
    public function __construct(
        private readonly JobListingRepository $repository,
    ) {}

    /**
     * Execute the action with shape-validated request data
     *
     * @param GetJobRequest $request Shape-validated request data
     * @return JobDetail|null DTO with business logic methods, null if not found
     */
    public function execute(GetJobRequest $request): ?JobDetail
    {
        $job = $this->repository->find($request['id']);

        if ($job === null) {
            return null;
        }

        return JobDetail::fromEntity($job);
    }
}
