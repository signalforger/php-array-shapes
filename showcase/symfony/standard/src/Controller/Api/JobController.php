<?php

namespace App\Controller\Api;

use App\Action\GetJobAction;
use App\Action\GetJobStatsAction;
use App\Action\ListJobsAction;
use App\Action\Request\GetJobRequest;
use App\Action\Request\ListJobsRequest;
use App\Repository\JobListingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API Controller for job listings.
 *
 * This controller is thin - it delegates to Action classes.
 * Actions return DTO objects that can be inspected via reflection
 * for API documentation generation.
 */
#[Route('/api', name: 'api_')]
class JobController extends AbstractController
{
    public function __construct(
        private readonly JobListingRepository $jobRepository
    ) {}

    /**
     * List jobs with optional filters.
     */
    #[Route('/jobs', name: 'jobs_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $action = new ListJobsAction(
            $this->jobRepository,
            ListJobsRequest::fromArray($request->query->all())
        );

        $action->execute();

        return $this->json($action->result());
    }

    /**
     * Get a single job listing.
     */
    #[Route('/jobs/{id}', name: 'jobs_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $action = new GetJobAction(
            $this->jobRepository,
            new GetJobRequest($id)
        );

        $action->execute();

        if ($action->isNotFound()) {
            return $this->json(['error' => 'Job not found'], 404);
        }

        return $this->json(['data' => $action->result()]);
    }

    /**
     * Get job statistics.
     */
    #[Route('/jobs/stats', name: 'jobs_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $action = new GetJobStatsAction($this->jobRepository);
        $action->execute();

        return $this->json(['data' => $action->result()]);
    }
}
