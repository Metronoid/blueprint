@extends('layouts.app')

@section('title', 'Audit Detail - Blueprint Auditing')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <div class="flex items-center">
                        <a href="{{ route('auditing.audits.history') }}" class="text-indigo-600 hover:text-indigo-500 mr-4">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Audit Detail</h1>
                            <p class="mt-1 text-sm text-gray-500">Comprehensive audit information and changes</p>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                        @if($audit->event === 'created') bg-green-100 text-green-800
                        @elseif($audit->event === 'updated') bg-blue-100 text-blue-800
                        @elseif($audit->event === 'deleted') bg-red-100 text-red-800
                        @else bg-gray-100 text-gray-800 @endif">
                        {{ ucfirst($audit->event) }}
                    </span>
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
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Audit Information -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Audit Information</h3>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Event Type</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($audit->event) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Model Type</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ class_basename($audit->auditable_type) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Model ID</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $audit->auditable_id }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">User</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($audit->user)
                                        {{ $audit->user->name }} ({{ $audit->user->email }})
                                    @else
                                        <span class="text-gray-400">Unknown</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">IP Address</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $audit->ip_address ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">User Agent</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($audit->user_agent)
                                        <span class="truncate block" title="{{ $audit->user_agent }}">
                                            {{ Str::limit($audit->user_agent, 50) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Route</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $audit->route_name ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Controller Action</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $audit->controller_action ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Origin Type</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $audit->origin_type ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Created At</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $audit->created_at->format('Y-m-d H:i:s') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Changes -->
                @if($audit->old_values || $audit->new_values)
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Changes</h3>
                        
                        @if($audit->event === 'created')
                            <div class="bg-green-50 border border-green-200 rounded-md p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-green-800">New Record Created</h3>
                                        <div class="mt-2 text-sm text-green-700">
                                            <p>This record was created with the following initial values:</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @elseif($audit->event === 'deleted')
                            <div class="bg-red-50 border border-red-200 rounded-md p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800">Record Deleted</h3>
                                        <div class="mt-2 text-sm text-red-700">
                                            <p>This record was deleted. Here are the values that were removed:</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="mt-4">
                            @if($audit->old_values && $audit->new_values)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Field</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Old Value</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Value</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($audit->new_values as $field => $newValue)
                                                @php
                                                    $oldValue = $audit->old_values[$field] ?? null;
                                                    $hasChanged = $oldValue !== $newValue;
                                                @endphp
                                                <tr class="@if($hasChanged) bg-yellow-50 @endif">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        {{ $field }}
                                                        @if($hasChanged)
                                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                Changed
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        @if(is_array($oldValue))
                                                            <pre class="text-xs">{{ json_encode($oldValue, JSON_PRETTY_PRINT) }}</pre>
                                                        @else
                                                            {{ $oldValue ?? 'null' }}
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        @if(is_array($newValue))
                                                            <pre class="text-xs">{{ json_encode($newValue, JSON_PRETTY_PRINT) }}</pre>
                                                        @else
                                                            {{ $newValue ?? 'null' }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @elseif($audit->new_values)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Field</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($audit->new_values as $field => $value)
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $field }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        @if(is_array($value))
                                                            <pre class="text-xs">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                                        @else
                                                            {{ $value ?? 'null' }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                <!-- Side Effects -->
                @if($sideEffects)
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Side Effects</h3>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">Cascading Changes Detected</h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>This change triggered additional modifications to related records:</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <pre class="bg-gray-50 p-4 rounded-md text-sm overflow-x-auto">{{ json_encode($sideEffects, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Related Audits -->
                @if($relatedAudits->count() > 0)
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Related Audits</h3>
                        <div class="flow-root">
                            <ul class="-mb-8">
                                @foreach($relatedAudits as $relatedAudit)
                                <li>
                                    <div class="relative pb-8">
                                        @if(!$loop->last)
                                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                        @endif
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white 
                                                    @if($relatedAudit->event === 'created') bg-green-500 @elseif($relatedAudit->event === 'updated') bg-blue-500 @elseif($relatedAudit->event === 'deleted') bg-red-500 @else bg-gray-500 @endif">
                                                    <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                        @if($relatedAudit->event === 'created')
                                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                                                        @elseif($relatedAudit->event === 'updated')
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                                                        @elseif($relatedAudit->event === 'deleted')
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                        @else
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                                        @endif
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                <div>
                                                    <p class="text-sm text-gray-500">
                                                        <a href="{{ route('auditing.audits.show', $relatedAudit) }}" class="font-medium text-gray-900 hover:text-indigo-600">
                                                            {{ class_basename($relatedAudit->auditable_type) }}
                                                        </a>
                                                        {{ $relatedAudit->event }}
                                                    </p>
                                                    <p class="text-xs text-gray-400">{{ $relatedAudit->created_at->diffForHumans() }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Quick Actions -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            @if($audit->auditable)
                            <a href="{{ route('auditing.rewind.interface', ['model_type' => $audit->auditable_type, 'model_id' => $audit->auditable_id]) }}" 
                               class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                </svg>
                                Rewind to This Point
                            </a>
                            @endif
                            
                            <a href="{{ route('auditing.git.versioning', ['model_type' => $audit->auditable_type, 'model_id' => $audit->auditable_id]) }}" 
                               class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                View Git History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 