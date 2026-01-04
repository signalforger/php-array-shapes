<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'JobBoard') - Job Aggregator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center space-x-2">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-xl font-bold text-gray-900">JobBoard</span>
                    </a>
                    <div class="hidden sm:ml-8 sm:flex sm:space-x-4">
                        <a href="/" class="px-3 py-2 text-sm font-medium {{ request()->is('/') ? 'text-indigo-600' : 'text-gray-600 hover:text-gray-900' }}">
                            Jobs
                        </a>
                        <a href="/api-docs" class="px-3 py-2 text-sm font-medium {{ request()->is('api-docs') ? 'text-indigo-600' : 'text-gray-600 hover:text-gray-900' }}">
                            API Docs
                        </a>
                        <a href="/about" class="px-3 py-2 text-sm font-medium {{ request()->is('about') ? 'text-indigo-600' : 'text-gray-600 hover:text-gray-900' }}">
                            About
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded">
                        PHP {{ phpversion() }}
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t mt-auto">
        <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-center">
                <p class="text-gray-500 text-sm">
                    Job Board Aggregator - PHP Array Shapes Showcase
                </p>
                <p class="text-gray-400 text-xs mt-2 sm:mt-0">
                    Data from Remotive, Arbeitnow, and more
                </p>
            </div>
        </div>
    </footer>
</body>
</html>
