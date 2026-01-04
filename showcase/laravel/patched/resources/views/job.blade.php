@extends('layouts.app')

@section('title', $job->title)

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <nav class="mb-6">
        <a href="/" class="text-indigo-600 hover:text-indigo-800">&larr; Back to Jobs</a>
    </nav>

    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Main Content -->
        <div class="flex-1">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <!-- Header -->
                <div class="flex items-start space-x-4 mb-6">
                    @if($job->company_logo)
                    <img src="{{ $job->company_logo }}" alt="{{ $job->company_name }}"
                        class="w-16 h-16 rounded-lg object-cover bg-gray-100">
                    @else
                    <div class="w-16 h-16 rounded-lg bg-indigo-100 flex items-center justify-center">
                        <span class="text-indigo-600 font-bold text-2xl">{{ substr($job->company_name, 0, 1) }}</span>
                    </div>
                    @endif
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $job->title }}</h1>
                        <p class="text-lg text-gray-600">{{ $job->company_name }}</p>
                    </div>
                </div>

                <!-- Meta Info -->
                <div class="flex flex-wrap gap-3 mb-6">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        </svg>
                        {{ $job->location ?? 'Remote' }}
                    </span>
                    @if($job->remote)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-700">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Remote
                    </span>
                    @endif
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-700">
                        {{ ucfirst($job->job_type ?? 'Full-time') }}
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-indigo-100 text-indigo-700">
                        {{ ucfirst($job->source) }}
                    </span>
                </div>

                @php $salary = $job->getSalaryRange(); @endphp
                @if($salary['min'] || $salary['max'])
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <div class="text-sm text-green-600 font-medium">Salary Range</div>
                    <div class="text-xl font-bold text-green-700">{{ $salary['formatted'] }}</div>
                </div>
                @endif

                <!-- Tags -->
                @if($job->tags && count($job->tags) > 0)
                <div class="mb-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Skills & Tags</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($job->tags as $tag)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700">
                            {{ $tag }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Description -->
                <div class="prose max-w-none">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Job Description</h3>
                    <div class="text-gray-700 whitespace-pre-wrap">
                        {!! nl2br(e(strip_tags($job->description))) !!}
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:w-80 flex-shrink-0">
            <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                <a href="{{ $job->url }}" target="_blank" rel="noopener"
                    class="block w-full bg-indigo-600 text-white text-center py-3 px-4 rounded-lg hover:bg-indigo-700 transition font-medium mb-4">
                    Apply Now &rarr;
                </a>

                <div class="space-y-4 text-sm">
                    @if($job->posted_at)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Posted</span>
                        <span class="text-gray-900">{{ $job->posted_at->format('M d, Y') }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-500">Fetched</span>
                        <span class="text-gray-900">{{ $job->fetched_at->format('M d, Y H:i') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Source</span>
                        <span class="text-gray-900">{{ ucfirst($job->source) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Job ID</span>
                        <span class="text-gray-900 font-mono text-xs">{{ $job->external_id }}</span>
                    </div>
                </div>
            </div>

            <!-- Related Jobs -->
            @if($relatedJobs->isNotEmpty())
            <div class="bg-white rounded-lg shadow p-6 mt-6">
                <h3 class="font-semibold text-gray-900 mb-4">Related Jobs</h3>
                <div class="space-y-3">
                    @foreach($relatedJobs as $related)
                    <a href="/job/{{ $related->id }}" class="block hover:bg-gray-50 -mx-2 px-2 py-2 rounded">
                        <div class="font-medium text-gray-900 text-sm">{{ $related->title }}</div>
                        <div class="text-gray-500 text-xs">{{ $related->company_name }}</div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
