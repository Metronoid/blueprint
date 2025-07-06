@extends('layouts.app')

@section('title', 'Analytics - Blueprint Auditing Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Analytics & Reporting</h1>
                    <p class="mt-1 text-sm text-gray-500">Comprehensive insights into your audit trail</p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('auditing.dashboard') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Dashboard
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
                <a href="{{ route('auditing.origin.tracking') }}" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                    Origin Tracking
                </a>
                <a href="{{ route('auditing.git.versioning') }}" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                    Git Versioning
                </a>
            </nav>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Timeframe Filter -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Time Period</h3>
                    <div class="flex space-x-2">
                        <a href="{{ route('auditing.analytics', ['timeframe' => '1d']) }}" 
                           class="px-3 py-1 text-sm rounded-md {{ $timeframe === '1d' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:text-gray-700' }}">
                            1 Day
                        </a>
                        <a href="{{ route('auditing.analytics', ['timeframe' => '7d']) }}" 
                           class="px-3 py-1 text-sm rounded-md {{ $timeframe === '7d' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:text-gray-700' }}">
                            7 Days
                        </a>
                        <a href="{{ route('auditing.analytics', ['timeframe' => '30d']) }}" 
                           class="px-3 py-1 text-sm rounded-md {{ $timeframe === '30d' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:text-gray-700' }}">
                            30 Days
                        </a>
                        <a href="{{ route('auditing.analytics', ['timeframe' => '90d']) }}" 
                           class="px-3 py-1 text-sm rounded-md {{ $timeframe === '90d' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:text-gray-700' }}">
                            90 Days
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Statistics Chart -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Daily Audit Activity</h3>
                <div class="h-64 flex items-end justify-between space-x-2">
                    @foreach($dailyStats as $stat)
                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-full bg-gray-200 rounded-t" style="height: {{ max(10, ($stat->total / max($dailyStats->max('total'), 1)) * 200) }}px">
                            <div class="w-full bg-indigo-600 rounded-t" style="height: 100%"></div>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">{{ \Carbon\Carbon::parse($stat->date)->format('M j') }}</div>
                        <div class="text-xs font-medium text-gray-900">{{ $stat->total }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Model Statistics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Most Audited Models</h3>
                    <div class="space-y-4">
                        @forelse($modelStats as $stat)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <span class="text-xs font-medium text-indigo-600">{{ substr(class_basename($stat->auditable_type), 0, 1) }}</span>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ class_basename($stat->auditable_type) }}</p>
                                    <p class="text-sm text-gray-500">{{ $stat->unique_users }} users</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900">{{ number_format($stat->total) }}</p>
                                <p class="text-sm text-gray-500">audits</p>
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-gray-500">No model statistics available</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Top Users</h3>
                    <div class="space-y-4">
                        @forelse($userStats as $stat)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-xs font-medium text-green-600">{{ substr('User', 0, 1) }}</span>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">User #{{ $stat->user_id }}</p>
                                    <p class="text-sm text-gray-500">{{ $stat->model_types }} model types</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900">{{ number_format($stat->total) }}</p>
                                <p class="text-sm text-gray-500">actions</p>
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-gray-500">No user statistics available</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Audits</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($dailyStats->sum('total')) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Unique Users</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($userStats->count()) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Model Types</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($modelStats->count()) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Avg Daily</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($dailyStats->avg('total'), 1) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 