<?php

namespace App\Http\Controllers\Api;

use App\Action\GetJobAction;
use App\Action\GetJobStatsAction;
use App\Action\ListJobsAction;
use App\Action\Request\GetJobRequest;
use App\Action\Request\ListJobsRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API Controller for job listings.
 *
 * This controller is thin - it delegates to Action classes.
 * Actions return DTO objects that can be inspected via reflection
 * for API documentation generation.
 */
class JobController extends Controller
{
    /**
     * List jobs with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $action = new ListJobsAction(
            ListJobsRequest::fromArray($request->all())
        );

        $action->execute();

        return response()->json($action->result());
    }

    /**
     * Get a single job listing.
     */
    public function show(int $id): JsonResponse
    {
        $action = new GetJobAction(
            new GetJobRequest($id)
        );

        $action->execute();

        if ($action->isNotFound()) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        return response()->json(['data' => $action->result()]);
    }

    /**
     * Get job statistics.
     */
    public function stats(): JsonResponse
    {
        $action = new GetJobStatsAction();
        $action->execute();

        return response()->json(['data' => $action->result()]);
    }
}
