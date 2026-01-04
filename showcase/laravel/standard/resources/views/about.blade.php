@extends('layouts.app')

@section('title', 'About')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-8 text-white">
            <h1 class="text-3xl font-bold">PHP Array Shapes Showcase</h1>
            <p class="mt-2 text-indigo-100">Demonstrating typed arrays and array shapes in PHP</p>
        </div>

        <div class="p-6 space-y-8">
            <!-- PHP Version Info -->
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                    <h3 class="font-semibold text-gray-900">PHP Version</h3>
                    <p class="text-gray-600">{{ $phpVersion }}</p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $hasArrayShapes ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                        {{ $hasArrayShapes ? 'Array Shapes Enabled' : 'Standard PHP' }}
                    </span>
                </div>
            </div>

            <!-- What This Showcases -->
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-4">What This Showcases</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="border rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">API Type Documentation</h3>
                        <p class="text-gray-600 text-sm">
                            Clean, enforceable API contracts using array shapes for request/response types.
                        </p>
                    </div>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">Data Ingestion</h3>
                        <p class="text-gray-600 text-sm">
                            Type-safe normalization of data from multiple external APIs (Remotive, Arbeitnow).
                        </p>
                    </div>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">Webhook Payloads</h3>
                        <p class="text-gray-600 text-sm">
                            Structured webhook delivery with validated payload shapes.
                        </p>
                    </div>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">Database DTOs</h3>
                        <p class="text-gray-600 text-sm">
                            Type-safe data transfer between layers using shape-validated arrays.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Comparison -->
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Standard vs Patched PHP</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Feature</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Standard PHP</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Patched PHP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr>
                                <td class="px-4 py-3 font-mono text-sm">array&lt;int&gt;</td>
                                <td class="px-4 py-3 text-red-600">Syntax Error</td>
                                <td class="px-4 py-3 text-green-600">Runtime validated</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-sm">array&lt;string, User&gt;</td>
                                <td class="px-4 py-3 text-red-600">Syntax Error</td>
                                <td class="px-4 py-3 text-green-600">Key + value types</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-sm">array{id: int, name: string}</td>
                                <td class="px-4 py-3 text-red-600">Syntax Error</td>
                                <td class="px-4 py-3 text-green-600">Shape validated</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-sm">array{...}!</td>
                                <td class="px-4 py-3 text-red-600">Syntax Error</td>
                                <td class="px-4 py-3 text-green-600">Closed shape (extra keys rejected)</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-sm">shape User = array{...}</td>
                                <td class="px-4 py-3 text-red-600">Syntax Error</td>
                                <td class="px-4 py-3 text-green-600">Type aliases</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Data Sources -->
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Data Sources</h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <span class="font-medium text-gray-900">Remotive</span>
                            <span class="text-gray-500 text-sm ml-2">Remote jobs API</span>
                        </div>
                        <a href="https://remotive.com/api-documentation" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-sm">
                            Docs &rarr;
                        </a>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <span class="font-medium text-gray-900">Arbeitnow</span>
                            <span class="text-gray-500 text-sm ml-2">EU/Remote jobs</span>
                        </div>
                        <a href="https://www.arbeitnow.com/api" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-sm">
                            Docs &rarr;
                        </a>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <span class="font-medium text-gray-900">JSearch (RapidAPI)</span>
                            <span class="text-gray-500 text-sm ml-2">General jobs (API key required)</span>
                        </div>
                        <a href="https://rapidapi.com/letscrape-6bRBa3QguO5/api/jsearch" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-sm">
                            Docs &rarr;
                        </a>
                    </div>
                </div>
            </section>

            <!-- Commands -->
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Artisan Commands</h2>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-gray-100 text-sm"><code># Fetch jobs from all providers
php artisan jobs:fetch

# Fetch from specific provider
php artisan jobs:fetch --provider=remotive

# Process webhook notifications
php artisan webhooks:process

# Retry failed webhooks
php artisan webhooks:process --retry</code></pre>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
