<?php

namespace App\Services;

use App\Models\JobListing;
use App\Models\SavedSearch;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing webhook deliveries.
 */
class WebhookService
{
    /**
     * Process all saved searches and send webhooks for new matching jobs.
     *
     * @return array{processed: int, delivered: int, failed: int}
     */
    public function processAllSavedSearches(): array
    {
        $stats = ['processed' => 0, 'delivered' => 0, 'failed' => 0];

        $searches = SavedSearch::whereNotNull('webhook_url')->get();

        foreach ($searches as $search) {
            $stats['processed']++;
            $result = $this->processSavedSearch($search);
            if ($result === true) {
                $stats['delivered']++;
            } elseif ($result === false) {
                $stats['failed']++;
            }
            // null means no new jobs to notify about
        }

        return $stats;
    }

    /**
     * Process a single saved search and send webhook if there are new jobs.
     *
     * @param SavedSearch $search The saved search to process
     * @return bool|null true=delivered, false=failed, null=no new jobs
     */
    public function processSavedSearch(SavedSearch $search): ?bool
    {
        $jobs = $this->findMatchingJobs($search);

        if ($jobs->isEmpty()) {
            return null;
        }

        $payload = $this->buildWebhookPayload($search, $jobs->all());
        $delivery = $this->createDelivery($search, $jobs->pluck('id')->all(), $payload);

        return $this->sendWebhook($delivery);
    }

    /**
     * Find jobs matching a saved search that haven't been notified yet.
     *
     * @param SavedSearch $search The search criteria
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findMatchingJobs(SavedSearch $search): \Illuminate\Database\Eloquent\Collection
    {
        $query = JobListing::query();

        // Only new jobs since last notification
        if ($search->last_notified_at) {
            $query->where('created_at', '>', $search->last_notified_at);
        }

        // Apply search query
        if ($search->query) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search->query}%")
                    ->orWhere('description', 'like', "%{$search->query}%")
                    ->orWhere('company_name', 'like', "%{$search->query}%");
            });
        }

        // Apply filters
        $filters = $search->filters ?? [];

        if (!empty($filters['remote'])) {
            $query->where('remote', true);
        }

        if (!empty($filters['job_type'])) {
            $query->where('job_type', $filters['job_type']);
        }

        if (!empty($filters['location'])) {
            $query->where('location', 'like', "%{$filters['location']}%");
        }

        if (!empty($filters['min_salary'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('salary_min')
                    ->orWhere('salary_min', '>=', $filters['min_salary']);
            });
        }

        return $query->orderBy('posted_at', 'desc')->limit(20)->get();
    }

    /**
     * Build webhook payload.
     *
     * @param SavedSearch $search The search that triggered the webhook
     * @param array $jobs Array of matching jobs
     * @return array Webhook payload
     */
    public function buildWebhookPayload(SavedSearch $search, array $jobs): array
    {
        return [
            'event' => 'new_jobs_matched',
            'search' => [
                'id' => $search->id,
                'name' => $search->name,
                'query' => $search->query,
            ],
            'jobs' => array_map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'company' => $job->company_name,
                    'location' => $job->location,
                    'remote' => $job->remote,
                    'url' => $job->url,
                    'salary' => $job->getSalaryRange(),
                    'posted_at' => $job->posted_at?->toIso8601String(),
                ];
            }, $jobs),
            'job_count' => count($jobs),
            'delivered_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Create a webhook delivery record.
     *
     * @param SavedSearch $search The search
     * @param array $jobIds Array of job IDs
     * @param array $payload The webhook payload
     * @return WebhookDelivery
     */
    public function createDelivery(SavedSearch $search, array $jobIds, array $payload): WebhookDelivery
    {
        return WebhookDelivery::create([
            'saved_search_id' => $search->id,
            'job_ids' => $jobIds,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }

    /**
     * Send a webhook delivery.
     *
     * @param WebhookDelivery $delivery The delivery to send
     * @return bool Success status
     */
    public function sendWebhook(WebhookDelivery $delivery): bool
    {
        $search = $delivery->savedSearch;

        if (!$search || !$search->webhook_url) {
            $delivery->update(['status' => 'failed']);
            return false;
        }

        $delivery->increment('attempts');

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Secret' => $search->webhook_secret,
                    'X-Webhook-Event' => 'new_jobs_matched',
                ])
                ->post($search->webhook_url, $delivery->payload);

            $delivery->update([
                'status' => $response->successful() ? 'delivered' : 'failed',
                'http_status' => $response->status(),
                'response' => substr($response->body(), 0, 1000),
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            if ($response->successful()) {
                $search->update(['last_notified_at' => now()]);
                return true;
            }

            Log::warning('Webhook delivery failed', [
                'delivery_id' => $delivery->id,
                'status' => $response->status(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Webhook delivery exception', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);

            $delivery->update([
                'status' => 'failed',
                'response' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Retry failed deliveries.
     *
     * @param int $maxAttempts Maximum attempts before giving up
     * @return array{retried: int, delivered: int, failed: int}
     */
    public function retryFailedDeliveries(int $maxAttempts = 3): array
    {
        $stats = ['retried' => 0, 'delivered' => 0, 'failed' => 0];

        $deliveries = WebhookDelivery::where('status', 'failed')
            ->where('attempts', '<', $maxAttempts)
            ->get();

        foreach ($deliveries as $delivery) {
            $stats['retried']++;
            if ($this->sendWebhook($delivery)) {
                $stats['delivered']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }
}
