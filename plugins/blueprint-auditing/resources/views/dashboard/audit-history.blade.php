@extends('layouts.app')

@section('title', 'Audit History - Blueprint Auditing Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Audit History</h1>
                    <p class="mt-1 text-sm text-gray-500">Browse and filter audit trail entries</p>
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
                <a href="{{ route('auditing.audits.history') }}" class="border-indigo-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
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
        <!-- Filters -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Filters</h3>
                <form method="GET" action="{{ route('auditing.audits.history') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                        <input type="text" name="search" id="search" value="{{ $filters['search'] ?? '' }}" 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="Route, controller, IP...">
                    </div>

                    <!-- Event Type -->
                    <div>
                        <label for="event" class="block text-sm font-medium text-gray-700">Event Type</label>
                        <select name="event" id="event" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">All Events</option>
                            <option value="created" {{ ($filters['event'] ?? '') === 'created' ? 'selected' : '' }}>Created</option>
                            <option value="updated" {{ ($filters['event'] ?? '') === 'updated' ? 'selected' : '' }}>Updated</option>
                            <option value="deleted" {{ ($filters['event'] ?? '') === 'deleted' ? 'selected' : '' }}>Deleted</option>
                            <option value="restored" {{ ($filters['event'] ?? '') === 'restored' ? 'selected' : '' }}>Restored</option>
                        </select>
                    </div>

                    <!-- Model Type -->
                    <div>
                        <label for="model_type" class="block text-sm font-medium text-gray-700">Model Type</label>
                        <select name="model_type" id="model_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">All Models</option>
                            @foreach($audits->pluck('auditable_type')->unique() as $modelType)
                                <option value="{{ $modelType }}" {{ ($filters['model_type'] ?? '') === $modelType ? 'selected' : '' }}>
                                    {{ class_basename($modelType) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Origin Type -->
                    <div>
                        <label for="origin_type" class="block text-sm font-medium text-gray-700">Origin Type</label>
                        <select name="origin_type" id="origin_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">All Origins</option>
                            <option value="request" {{ ($filters['origin_type'] ?? '') === 'request' ? 'selected' : '' }}>Request</option>
                            <option value="console" {{ ($filters['origin_type'] ?? '') === 'console' ? 'selected' : '' }}>Console</option>
                            <option value="job" {{ ($filters['origin_type'] ?? '') === 'job' ? 'selected' : '' }}>Job</option>
                            <option value="observer" {{ ($filters['origin_type'] ?? '') === 'observer' ? 'selected' : '' }}>Observer</option>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700">From Date</label>
                        <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] ?? '' }}" 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700">To Date</label>
                        <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] ?? '' }}" 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>

                    <!-- Filter Actions -->
                    <div class="lg:col-span-2 flex items-end space-x-3">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"></path>
                            </svg>
                            Apply Filters
                        </button>
                        <a href="{{ route('auditing.audits.history') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Clear Filters
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results -->
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <div class="px-4 py-5 sm:px-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Audit Entries ({{ $audits->total() }} total)
                    </h3>
                    <div class="text-sm text-gray-500">
                        Showing {{ $audits->firstItem() ?? 0 }} to {{ $audits->lastItem() ?? 0 }} of {{ $audits->total() }} results
                    </div>
                </div>
            </div>
            <ul class="divide-y divide-gray-200">
                @forelse($audits as $audit)
                <li>
                    <div class="px-4 py-4 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <span class="h-8 w-8 rounded-full flex items-center justify-center 
                                        @if($audit->event === 'created') bg-green-100 text-green-800 @elseif($audit->event === 'updated') bg-blue-100 text-blue-800 @elseif($audit->event === 'deleted') bg-red-100 text-red-800 @else bg-gray-100 text-gray-800 @endif">
                                        <span class="text-xs font-medium">{{ strtoupper(substr($audit->event, 0, 1)) }}</span>
                                    </span>
                                </div>
                                <div class="ml-4">
                                    <div class="flex items-center">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ class_basename($audit->auditable_type) }} #{{ $audit->auditable_id }}
                                        </p>
                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            @if($audit->event === 'created') bg-green-100 text-green-800 @elseif($audit->event === 'updated') bg-blue-100 text-blue-800 @elseif($audit->event === 'deleted') bg-red-100 text-red-800 @else bg-gray-100 text-gray-800 @endif">
                                            {{ ucfirst($audit->event) }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex items-center text-sm text-gray-500">
                                        @if($audit->user)
                                        <span>by {{ $audit->user->name }}</span>
                                        <span class="mx-1">•</span>
                                        @endif
                                        <span>{{ $audit->created_at->format('M j, Y g:i A') }}</span>
                                        @if($audit->ip_address)
                                        <span class="mx-1">•</span>
                                        <span>{{ $audit->ip_address }}</span>
                                        @endif
                                    </div>
                                    @if($audit->route_name || $audit->controller_action)
                                    <div class="mt-1 text-sm text-gray-500">
                                        @if($audit->route_name)
                                        <span class="font-medium">{{ $audit->route_name }}</span>
                                        @endif
                                        @if($audit->controller_action)
                                        <span class="mx-1">•</span>
                                        <span>{{ $audit->controller_action }}</span>
                                        @endif
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                @if($audit->is_unrewindable)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                    Unrewindable
                                </span>
                                @endif
                                @if($audit->origin_type)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    {{ ucfirst($audit->origin_type) }}
                                </span>
                                @endif
                                <a href="{{ route('auditing.audits.show', $audit) }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </li>
                @empty
                <li class="px-4 py-8 text-center text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No audits found</h3>
                    <p class="mt-1 text-sm text-gray-500">Try adjusting your filters or search terms.</p>
                </li>
                @endforelse
            </ul>
        </div>

        <!-- Pagination -->
        @if($audits->hasPages())
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-6">
            <div class="flex-1 flex justify-between sm:hidden">
                @if($audits->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-white cursor-not-allowed">
                    Previous
                </span>
                @else
                <a href="{{ $audits->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
                @endif

                @if($audits->hasMorePages())
                <a href="{{ $audits->nextPageUrl() }}" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
                @else
                <span class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-white cursor-not-allowed">
                    Next
                </span>
                @endif
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium">{{ $audits->firstItem() }}</span> to <span class="font-medium">{{ $audits->lastItem() }}</span> of <span class="font-medium">{{ $audits->total() }}</span> results
                    </p>
                </div>
                <div>
                    {{ $audits->links() }}
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection 