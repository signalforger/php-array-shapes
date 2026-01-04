<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API Controller for saved searches with webhooks.
 *
 * Allows users to save job searches and receive webhook notifications.
 */
class SavedSearchController extends Controller
{
    public function __construct(
        private WebhookService $webhookService
    ) {}

    /**
     * List saved searches for a user.
     *
     * @param Request $request
     *   Headers:
     *   - X-User-Id: int (required, user ID)
     *
     * @return JsonResponse
     *   Response structure:
     *   {
     *     "data": [
     *       {
     *         "id": int,
     *         "name": string,
     *         "query": string|null,
     *         "filters": object,
     *         "webhook_url": string|null,
     *         "last_notified_at": string|null,
     *         "created_at": string
     *       }
     *     ]
     *   }
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->header('X-User-Id');
        if (!$userId) {
            return response()->json(['error' => 'X-User-Id header required'], 401);
        }

        $searches = SavedSearch::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $searches->map(fn($s) => $this->formatSearch($s)),
        ]);
    }

    /**
     * Create a new saved search.
     *
     * @param Request $request
     *   Headers:
     *   - X-User-Id: int (required)
     *
     *   Body (JSON):
     *   {
     *     "name": string (required),
     *     "query": string (optional),
     *     "filters": {
     *       "remote": bool,
     *       "job_type": string,
     *       "location": string,
     *       "min_salary": int
     *     },
     *     "webhook_url": string (optional, URL for notifications)
     *   }
     *
     * @return JsonResponse
     *   Response structure:
     *   {
     *     "data": {...},
     *     "message": string
     *   }
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $request->header('X-User-Id');
        if (!$userId) {
            return response()->json(['error' => 'X-User-Id header required'], 401);
        }

        // Ensure user exists
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'query' => 'nullable|string|max:500',
            'filters' => 'nullable|array',
            'filters.remote' => 'nullable|boolean',
            'filters.job_type' => 'nullable|string|in:full-time,part-time,contract,internship',
            'filters.location' => 'nullable|string|max:255',
            'filters.min_salary' => 'nullable|integer|min:0',
            'webhook_url' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $search = SavedSearch::create([
            'user_id' => $userId,
            'name' => $request->input('name'),
            'query' => $request->input('query'),
            'filters' => $request->input('filters', []),
            'webhook_url' => $request->input('webhook_url'),
        ]);

        return response()->json([
            'data' => $this->formatSearch($search),
            'message' => 'Saved search created successfully',
        ], 201);
    }

    /**
     * Get a single saved search.
     *
     * @param Request $request
     * @param int $id Search ID
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $request->header('X-User-Id');
        if (!$userId) {
            return response()->json(['error' => 'X-User-Id header required'], 401);
        }

        $search = SavedSearch::where('id', $id)->where('user_id', $userId)->first();
        if (!$search) {
            return response()->json(['error' => 'Saved search not found'], 404);
        }

        // Include recent deliveries
        $deliveries = $search->deliveries()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $data = $this->formatSearch($search);
        $data['deliveries'] = $deliveries->map(fn($d) => [
            'id' => $d->id,
            'status' => $d->status,
            'job_count' => count($d->job_ids),
            'attempts' => $d->attempts,
            'delivered_at' => $d->delivered_at?->toIso8601String(),
            'created_at' => $d->created_at->toIso8601String(),
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Update a saved search.
     *
     * @param Request $request
     * @param int $id Search ID
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $userId = $request->header('X-User-Id');
        if (!$userId) {
            return response()->json(['error' => 'X-User-Id header required'], 401);
        }

        $search = SavedSearch::where('id', $id)->where('user_id', $userId)->first();
        if (!$search) {
            return response()->json(['error' => 'Saved search not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'query' => 'nullable|string|max:500',
            'filters' => 'nullable|array',
            'webhook_url' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $search->update($request->only(['name', 'query', 'filters', 'webhook_url']));

        return response()->json([
            'data' => $this->formatSearch($search->fresh()),
            'message' => 'Saved search updated successfully',
        ]);
    }

    /**
     * Delete a saved search.
     *
     * @param Request $request
     * @param int $id Search ID
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $request->header('X-User-Id');
        if (!$userId) {
            return response()->json(['error' => 'X-User-Id header required'], 401);
        }

        $search = SavedSearch::where('id', $id)->where('user_id', $userId)->first();
        if (!$search) {
            return response()->json(['error' => 'Saved search not found'], 404);
        }

        $search->delete();

        return response()->json([
            'message' => 'Saved search deleted successfully',
        ]);
    }

    /**
     * Test webhook delivery for a saved search.
     *
     * @param Request $request
     * @param int $id Search ID
     * @return JsonResponse
     */
    public function testWebhook(Request $request, int $id): JsonResponse
    {
        $userId = $request->header('X-User-Id');
        if (!$userId) {
            return response()->json(['error' => 'X-User-Id header required'], 401);
        }

        $search = SavedSearch::where('id', $id)->where('user_id', $userId)->first();
        if (!$search) {
            return response()->json(['error' => 'Saved search not found'], 404);
        }

        if (!$search->webhook_url) {
            return response()->json(['error' => 'No webhook URL configured'], 400);
        }

        $result = $this->webhookService->processSavedSearch($search);

        return response()->json([
            'success' => $result === true,
            'message' => match ($result) {
                true => 'Webhook delivered successfully',
                false => 'Webhook delivery failed',
                null => 'No matching jobs to send',
            },
        ]);
    }

    /**
     * Format a saved search for API response.
     *
     * @param SavedSearch $search
     * @return array
     */
    private function formatSearch(SavedSearch $search): array
    {
        return [
            'id' => $search->id,
            'name' => $search->name,
            'query' => $search->query,
            'filters' => $search->filters ?? [],
            'webhook_url' => $search->webhook_url,
            'webhook_secret' => $search->webhook_secret,
            'last_notified_at' => $search->last_notified_at?->toIso8601String(),
            'created_at' => $search->created_at->toIso8601String(),
        ];
    }
}
