@extends('layouts.app')

@section('title', 'Find Your Next Job')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Stats Banner -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-lg p-6 mb-8 text-white">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center">
                <div class="text-3xl font-bold">{{ number_format($stats['total']) }}</div>
                <div class="text-indigo-200 text-sm">Total Jobs</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold">{{ number_format($stats['remote']) }}</div>
                <div class="text-indigo-200 text-sm">Remote Jobs</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold">{{ count($stats['sources']) }}</div>
                <div class="text-indigo-200 text-sm">Sources</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold">{{ array_sum($stats['sources']) > 0 ? round(($stats['remote'] / max(1, $stats['total'])) * 100) : 0 }}%</div>
                <div class="text-indigo-200 text-sm">Remote Rate</div>
            </div>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Filters Sidebar -->
        <div class="lg:w-64 flex-shrink-0">
            <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                <h3 class="font-semibold text-gray-900 mb-4">Filters</h3>
                <form action="/" method="GET" class="space-y-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                            placeholder="Job title or company..."
                            class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <!-- Remote Only -->
                    <div class="flex items-center">
                        <input type="checkbox" name="remote" value="1" id="remote"
                            {{ ($filters['remote'] ?? '') === '1' ? 'checked' : '' }}
                            class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500">
                        <label for="remote" class="ml-2 text-sm text-gray-700">Remote Only</label>
                    </div>

                    <!-- Job Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Type</label>
                        <select name="job_type" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Types</option>
                            <option value="full-time" {{ ($filters['job_type'] ?? '') === 'full-time' ? 'selected' : '' }}>Full-time</option>
                            <option value="part-time" {{ ($filters['job_type'] ?? '') === 'part-time' ? 'selected' : '' }}>Part-time</option>
                            <option value="contract" {{ ($filters['job_type'] ?? '') === 'contract' ? 'selected' : '' }}>Contract</option>
                            <option value="internship" {{ ($filters['job_type'] ?? '') === 'internship' ? 'selected' : '' }}>Internship</option>
                        </select>
                    </div>

                    <!-- Source -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                        <select name="source" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Sources</option>
                            @foreach($stats['sources'] as $source => $count)
                            <option value="{{ $source }}" {{ ($filters['source'] ?? '') === $source ? 'selected' : '' }}>
                                {{ ucfirst($source) }} ({{ $count }})
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition">
                        Apply Filters
                    </button>

                    @if(!empty(array_filter($filters)))
                    <a href="/" class="block text-center text-sm text-gray-500 hover:text-gray-700">
                        Clear all filters
                    </a>
                    @endif
                </form>
            </div>
        </div>

        <!-- Job Listings -->
        <div class="flex-1">
            @if($jobs->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No jobs found</h3>
                    <p class="text-gray-500">Try adjusting your filters or fetch new jobs.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($jobs as $job)
                    <a href="/job/{{ $job->id }}" class="block bg-white rounded-lg shadow hover:shadow-md transition p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start space-x-4">
                                @if($job->company_logo)
                                <img src="{{ $job->company_logo }}" alt="{{ $job->company_name }}"
                                    class="w-12 h-12 rounded-lg object-cover bg-gray-100">
                                @else
                                <div class="w-12 h-12 rounded-lg bg-indigo-100 flex items-center justify-center">
                                    <span class="text-indigo-600 font-bold text-lg">{{ substr($job->company_name, 0, 1) }}</span>
                                </div>
                                @endif
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 hover:text-indigo-600">
                                        {{ $job->title }}
                                    </h3>
                                    <p class="text-gray-600">{{ $job->company_name }}</p>
                                    <div class="flex flex-wrap items-center gap-2 mt-2">
                                        <span class="inline-flex items-center text-sm text-gray-500">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            {{ $job->location ?? 'Remote' }}
                                        </span>
                                        @if($job->remote)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                            Remote
                                        </span>
                                        @endif
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ ucfirst($job->job_type ?? 'Full-time') }}
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                                            {{ ucfirst($job->source) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                @php $salary = $job->getSalaryRange(); @endphp
                                @if($salary['min'] || $salary['max'])
                                <div class="text-sm font-medium text-gray-900">{{ $salary['formatted'] }}</div>
                                @endif
                                @if($job->posted_at)
                                <div class="text-xs text-gray-400 mt-1">
                                    {{ $job->posted_at->diffForHumans() }}
                                </div>
                                @endif
                            </div>
                        </div>

                        @if($job->tags && count($job->tags) > 0)
                        <div class="mt-3 flex flex-wrap gap-1">
                            @foreach(array_slice($job->tags, 0, 5) as $tag)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-600 border">
                                {{ $tag }}
                            </span>
                            @endforeach
                            @if(count($job->tags) > 5)
                            <span class="text-xs text-gray-400">+{{ count($job->tags) - 5 }} more</span>
                            @endif
                        </div>
                        @endif
                    </a>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $jobs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
