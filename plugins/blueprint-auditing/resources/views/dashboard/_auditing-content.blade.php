{{-- This partial contains the main content of the auditing dashboard, extracted from the original index.blade.php --}}

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Audits -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Audits</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ number_format($stats['total_audits']) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-5 py-3">
            <div class="text-sm">
                <span class="text-green-600 font-medium">+{{ $stats['audits_24h'] }}</span>
                <span class="text-gray-500">in last 24h</span>
            </div>
        </div>
    </div>
    <!-- Unique Models -->
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
                        <dt class="text-sm font-medium text-gray-500 truncate">Audited Models</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ $stats['unique_models'] }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-5 py-3">
            <div class="text-sm">
                <span class="text-blue-600 font-medium">{{ $stats['unique_users'] }}</span>
                <span class="text-gray-500">unique users</span>
            </div>
        </div>
    </div>
    <!-- Git Versioning -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Git Branches</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ $gitStats['total_branches'] }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-5 py-3">
            <div class="text-sm">
                <span class="text-purple-600 font-medium">{{ $gitStats['total_commits'] }}</span>
                <span class="text-gray-500">commits</span>
            </div>
        </div>
    </div>
    <!-- Rewind Operations -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Rewind Operations</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ $rewindStats['rewind_operations'] }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-5 py-3">
            <div class="text-sm">
                <span class="text-orange-600 font-medium">{{ $rewindStats['models_with_rewind'] }}</span>
                <span class="text-gray-500">models enabled</span>
                <span class="mx-2">•</span>
                <span class="text-red-600 font-medium">{{ $rewindStats['unrewindable_audits'] }}</span>
                <span class="text-gray-500">unrewindable</span>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Recent Audits -->
    <div class="lg:col-span-2">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Audits</h3>
                    <a href="{{ route('auditing.audits.history') }}" class="text-sm text-indigo-600 hover:text-indigo-500">View all</a>
                </div>
                <div class="flow-root">
                    <ul class="-mb-8">
                        @forelse($recentAudits as $audit)
                        <li>
                            <div class="relative pb-8">
                                @if(!$loop->last)
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                @endif
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white 
                                            @if($audit->event === 'created') bg-green-500 @elseif($audit->event === 'updated') bg-blue-500 @elseif($audit->event === 'deleted') bg-red-500 @else bg-gray-500 @endif">
                                            <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                @if($audit->event === 'created')
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                                                @elseif($audit->event === 'updated')
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                                                @elseif($audit->event === 'deleted')
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
                                                <span class="font-medium text-gray-900">{{ class_basename($audit->auditable_type) }}</span> 
                                                {{ $audit->event }}
                                                @if($audit->user)
                                                by <span class="font-medium text-gray-900">{{ $audit->user->name }}</span>
                                                @endif
                                            </p>
                                            @if($audit->route_name)
                                            <p class="text-xs text-gray-400">via {{ $audit->route_name }}</p>
                                            @endif
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            <time datetime="{{ $audit->created_at->toISOString() }}">{{ $audit->created_at->diffForHumans() }}</time>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        @empty
                        <li class="text-center py-8 text-gray-500">No recent audits found</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Top Models -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Most Audited Models</h3>
                <div class="space-y-3">
                    @forelse($topModels as $model)
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-900">{{ class_basename($model->auditable_type) }}</span>
                        <span class="text-sm text-gray-500">{{ number_format($model->count) }}</span>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500">No data available</p>
                    @endforelse
                </div>
            </div>
        </div>
        <!-- Origin Types -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Origin Types</h3>
                <div class="space-y-3">
                    @forelse($originTypes as $origin)
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-900">{{ ucfirst($origin->origin_type) }}</span>
                        <span class="text-sm text-gray-500">{{ number_format($origin->count) }}</span>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500">No origin tracking data</p>
                    @endforelse
                </div>
            </div>
        </div>
        <!-- Quick Actions -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('auditing.rewind.interface') }}" class="flex items-center p-3 text-sm font-medium text-gray-900 bg-gray-50 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        </svg>
                        Time Travel
                    </a>
                    <a href="{{ route('auditing.git.versioning') }}" class="flex items-center p-3 text-sm font-medium text-gray-900 bg-gray-50 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h2a2 2 0 002-2z"></path>
                        </svg>
                        Git Versioning
                    </a>
                    <a href="{{ route('auditing.origin.tracking') }}" class="flex items-center p-3 text-sm font-medium text-gray-900 bg-gray-50 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Origin Analysis
                    </a>
                </div>
            </div>
        </div>
    </div>
</div> 