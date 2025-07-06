@extends('layouts.app')

@section('title', 'Origin Tracking - Blueprint Auditing')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Origin Tracking</h1>
                    <p class="mt-1 text-sm text-gray-500">Track the source and context of audit events</p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('auditing.analytics') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Analytics
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex space-x-8">
                <a href="{{ route('auditing.dashboard') }}" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                    Overview
                </a>
                <a href="{{ route('auditing.audits.history') }}" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                    Audit History
                </a>
                <a href="{{ route('auditing.rewind.interface') }}" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                    Rewind
                </a>
                <a href="{{ route('auditing.origin.tracking') }}" class="border-indigo-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                    Origin Tracking
                </a>
                <a href="{{ route('auditing.git.versioning') }}" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                    Git Versioning
                </a>
            </nav>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filters -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Filters</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="timeframe" class="block text-sm font-medium text-gray-700">Timeframe</label>
                        <select id="timeframe" name="timeframe" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="1d" @if($timeframe === '1d') selected @endif>Last 24 hours</option>
                            <option value="7d" @if($timeframe === '7d') selected @endif>Last 7 days</option>
                            <option value="30d" @if($timeframe === '30d') selected @endif>Last 30 days</option>
                            <option value="90d" @if($timeframe === '90d') selected @endif>Last 90 days</option>
                            <option value="1y" @if($timeframe === '1y') selected @endif>Last year</option>
                        </select>
                    </div>
                    <div>
                        <label for="origin_type" class="block text-sm font-medium text-gray-700">Origin Type</label>
                        <select id="origin_type" name="origin_type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="">All Origin Types</option>
                            @foreach($originTypes as $type)
                                <option value="{{ $type->origin_type }}" @if(request('origin_type') === $type->origin_type) selected @endif>
                                    {{ $type->origin_type }} ({{ $type->count }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Origin Type Summary -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Origin Type Distribution</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach($originTypes->take(8) as $type)
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $type->origin_type }}</p>
                                <p class="text-2xl font-bold text-indigo-600">{{ number_format($type->count) }}</p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500">
                                    {{ number_format(($type->count / $originTypes->sum('count')) * 100, 1) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Origin Data Table -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Origin Details</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origin Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Controller Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($originData as $data)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $data->origin_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $data->route_name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $data->controller_action ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ number_format($data->count) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ number_format(($data->count / $originData->sum('count')) * 100, 1) }}%
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                    No origin data found for the selected filters.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Insights -->
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Most Common Origins -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Most Common Origins</h3>
                    <div class="space-y-4">
                        @foreach($originData->take(5) as $index => $data)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <span class="text-sm font-medium text-indigo-600">{{ $index + 1 }}</span>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-900">{{ $data->origin_type }}</p>
                                    <p class="text-sm text-gray-500">{{ $data->route_name ?? 'N/A' }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900">{{ number_format($data->count) }}</p>
                                <p class="text-sm text-gray-500">{{ number_format(($data->count / $originData->sum('count')) * 100, 1) }}%</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Origin Type Analysis -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Origin Type Analysis</h3>
                    <div class="space-y-4">
                        @php
                            $totalCount = $originTypes->sum('count');
                            $topOrigin = $originTypes->first();
                            $apiCount = $originTypes->where('origin_type', 'like', '%api%')->sum('count');
                            $webCount = $originTypes->where('origin_type', 'like', '%web%')->sum('count');
                            $consoleCount = $originTypes->where('origin_type', 'like', '%console%')->sum('count');
                        @endphp
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">Primary Origin</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p><strong>{{ $topOrigin->origin_type }}</strong> is the most common origin type with {{ number_format($topOrigin->count) }} events ({{ number_format(($topOrigin->count / $totalCount) * 100, 1) }}%)</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">{{ number_format($apiCount) }}</div>
                                <div class="text-sm text-gray-500">API Events</div>
                                <div class="text-xs text-gray-400">{{ $totalCount > 0 ? number_format(($apiCount / $totalCount) * 100, 1) : 0 }}%</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600">{{ number_format($webCount) }}</div>
                                <div class="text-sm text-gray-500">Web Events</div>
                                <div class="text-xs text-gray-400">{{ $totalCount > 0 ? number_format(($webCount / $totalCount) * 100, 1) : 0 }}%</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600">{{ number_format($consoleCount) }}</div>
                                <div class="text-sm text-gray-500">Console Events</div>
                                <div class="text-xs text-gray-400">{{ $totalCount > 0 ? number_format(($consoleCount / $totalCount) * 100, 1) : 0 }}%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 