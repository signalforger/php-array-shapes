@extends('layouts.app')

@section('title', 'API Documentation')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-lg shadow">
        <!-- Header -->
        <div class="border-b px-6 py-4">
            <h1 class="text-2xl font-bold text-gray-900">API Documentation</h1>
            <p class="text-gray-600 mt-1">RESTful API for job listings, companies, and saved searches</p>
        </div>

        <div class="p-6 space-y-8">
            <!-- Base URL -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Base URL</h3>
                <code class="text-indigo-600 bg-indigo-50 px-3 py-1 rounded">{{ url('/api') }}</code>
            </div>

            <!-- Jobs Endpoints -->
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                    <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded mr-2">JOBS</span>
                    Job Listings
                </h2>

                <!-- GET /api/jobs -->
                <div class="border rounded-lg mb-4">
                    <div class="bg-gray-50 px-4 py-3 border-b flex items-center">
                        <span class="bg-green-100 text-green-800 text-xs font-mono font-bold px-2 py-1 rounded mr-3">GET</span>
                        <code class="text-gray-800">/api/jobs</code>
                    </div>
                    <div class="p-4">
                        <p class="text-gray-600 mb-4">List all jobs with optional filters and pagination.</p>

                        <h4 class="font-medium text-gray-900 mb-2">Query Parameters</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">Parameter</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">Type</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr><td class="px-3 py-2 font-mono">q</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Search query (title, company, description)</td></tr>
                                    <tr><td class="px-3 py-2 font-mono">remote</td><td class="px-3 py-2">bool</td><td class="px-3 py-2">Filter remote jobs only</td></tr>
                                    <tr><td class="px-3 py-2 font-mono">job_type</td><td class="px-3 py-2">string</td><td class="px-3 py-2">full-time, part-time, contract, internship</td></tr>
                                    <tr><td class="px-3 py-2 font-mono">location</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Location filter</td></tr>
                                    <tr><td class="px-3 py-2 font-mono">source</td><td class="px-3 py-2">string</td><td class="px-3 py-2">remotive, arbeitnow, jsearch</td></tr>
                                    <tr><td class="px-3 py-2 font-mono">min_salary</td><td class="px-3 py-2">int</td><td class="px-3 py-2">Minimum salary</td></tr>
                                    <tr><td class="px-3 py-2 font-mono">page</td><td class="px-3 py-2">int</td><td class="px-3 py-2">Page number (default: 1)</td></tr>
                                    <tr><td class="px-3 py-2 font-mono">per_page</td><td class="px-3 py-2">int</td><td class="px-3 py-2">Items per page (max: 100)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h4 class="font-medium text-gray-900 mt-4 mb-2">Response Structure</h4>
                        <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs"><code>{
  "data": [
    {
      "id": 1,
      "title": "Senior PHP Developer",
      "company_name": "Acme Corp",
      "company_logo": "https://...",
      "location": "Remote, USA",
      "remote": true,
      "job_type": "full-time",
      "salary": {
        "min": 80000,
        "max": 120000,
        "currency": "USD",
        "formatted": "USD 80,000 - 120,000"
      },
      "url": "https://...",
      "tags": ["PHP", "Laravel", "PostgreSQL"],
      "source": "remotive",
      "posted_at": "2026-01-04T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}</code></pre>
                    </div>
                </div>

                <!-- GET /api/jobs/:id -->
                <div class="border rounded-lg mb-4">
                    <div class="bg-gray-50 px-4 py-3 border-b flex items-center">
                        <span class="bg-green-100 text-green-800 text-xs font-mono font-bold px-2 py-1 rounded mr-3">GET</span>
                        <code class="text-gray-800">/api/jobs/{id}</code>
                    </div>
                    <div class="p-4">
                        <p class="text-gray-600">Get a single job listing with full description.</p>
                    </div>
                </div>

                <!-- GET /api/jobs/stats -->
                <div class="border rounded-lg">
                    <div class="bg-gray-50 px-4 py-3 border-b flex items-center">
                        <span class="bg-green-100 text-green-800 text-xs font-mono font-bold px-2 py-1 rounded mr-3">GET</span>
                        <code class="text-gray-800">/api/jobs/stats</code>
                    </div>
                    <div class="p-4">
                        <p class="text-gray-600">Get job statistics (counts by source, type, etc.)</p>
                    </div>
                </div>
            </section>

            <!-- Companies Endpoints -->
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                    <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2 py-1 rounded mr-2">COMPANIES</span>
                    Companies
                </h2>

                <div class="border rounded-lg mb-4">
                    <div class="bg-gray-50 px-4 py-3 border-b flex items-center">
                        <span class="bg-green-100 text-green-800 text-xs font-mono font-bold px-2 py-1 rounded mr-3">GET</span>
                        <code class="text-gray-800">/api/companies</code>
                    </div>
                    <div class="p-4">
                        <p class="text-gray-600">List companies with job counts.</p>
                    </div>
                </div>

                <div class="border rounded-lg">
                    <div class="bg-gray-50 px-4 py-3 border-b flex items-center">
                        <span class="bg-green-100 text-green-800 text-xs font-mono font-bold px-2 py-1 rounded mr-3">GET</span>
                        <code class="text-gray-800">/api/companies/{slug}</code>
                    </div>
                    <div class="p-4">
                        <p class="text-gray-600">Get company details with their job listings.</p>
                    </div>
                </div>
            </section>

            <!-- Saved Searches Endpoints -->
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                    <span class="bg-orange-100 text-orange-800 text-xs font-medium px-2 py-1 rounded mr-2">WEBHOOKS</span>
                    Saved Searches & Webhooks
                </h2>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <p class="text-yellow-800 text-sm">
                        <strong>Authentication:</strong> These endpoints require the <code class="bg-yellow-100 px-1 rounded">X-User-Id</code> header.
                    </p>
                </div>

                <div class="border rounded-lg mb-4">
                    <div class="bg-gray-50 px-4 py-3 border-b flex items-center">
                        <span class="bg-green-100 text-green-800 text-xs font-mono font-bold px-2 py-1 rounded mr-3">GET</span>
                        <code class="text-gray-800">/api/saved-searches</code>
                    </div>
                    <div class="p-4">
                        <p class="text-gray-600">List user's saved searches.</p>
                    </div>
                </div>

                <div class="border rounded-lg mb-4">
                    <div class="bg-gray-50 px-4 py-3 border-b flex items-center">
                        <span class="bg-blue-100 text-blue-800 text-xs font-mono font-bold px-2 py-1 rounded mr-3">POST</span>
                        <code class="text-gray-800">/api/saved-searches</code>
                    </div>
                    <div class="p-4">
                        <p class="text-gray-600 mb-4">Create a new saved search with optional webhook.</p>

                        <h4 class="font-medium text-gray-900 mb-2">Request Body</h4>
                        <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs"><code>{
  "name": "Remote PHP Jobs",
  "query": "PHP Laravel",
  "filters": {
    "remote": true,
    "job_type": "full-time",
    "min_salary": 80000
  },
  "webhook_url": "https://your-server.com/webhook"
}</code></pre>

                        <h4 class="font-medium text-gray-900 mt-4 mb-2">Webhook Payload</h4>
                        <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs"><code>{
  "event": "new_jobs_matched",
  "search": {
    "id": 1,
    "name": "Remote PHP Jobs",
    "query": "PHP Laravel"
  },
  "jobs": [
    {
      "id": 42,
      "title": "Senior PHP Developer",
      "company": "Acme Corp",
      "url": "https://..."
    }
  ],
  "job_count": 1,
  "delivered_at": "2026-01-04T12:00:00Z"
}</code></pre>
                    </div>
                </div>

                <div class="border rounded-lg">
                    <div class="bg-gray-50 px-4 py-3 border-b flex items-center">
                        <span class="bg-blue-100 text-blue-800 text-xs font-mono font-bold px-2 py-1 rounded mr-3">POST</span>
                        <code class="text-gray-800">/api/saved-searches/{id}/test-webhook</code>
                    </div>
                    <div class="p-4">
                        <p class="text-gray-600">Test webhook delivery for a saved search.</p>
                    </div>
                </div>
            </section>

            <!-- PHP Type Information -->
            <section class="bg-indigo-50 rounded-lg p-6">
                <h2 class="text-xl font-semibold text-indigo-900 mb-4">PHP Type Safety</h2>
                <p class="text-indigo-800 mb-4">
                    This API demonstrates proper type documentation. In the <strong>patched PHP version</strong>,
                    these types are enforced at runtime using native array shapes and typed arrays.
                </p>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-white rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-2">Standard PHP (PHPDoc)</h4>
                        <pre class="text-xs bg-gray-100 p-2 rounded"><code>/**
 * @return array{
 *   id: int,
 *   title: string,
 *   salary: array{min: ?int, max: ?int}
 * }
 */
function getJob(): array</code></pre>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-2">Patched PHP (Native)</h4>
                        <pre class="text-xs bg-gray-100 p-2 rounded"><code>function getJob(): array{
    id: int,
    title: string,
    salary: array{min: ?int, max: ?int}
}</code></pre>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
