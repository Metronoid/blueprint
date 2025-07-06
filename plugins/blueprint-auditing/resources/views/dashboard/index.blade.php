@extends('dashboard.index')

@section('plugin-dashboard')
    <div class="bg-white shadow rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Blueprint Auditing Dashboard</h3>
            <p class="text-sm text-gray-500">Comprehensive audit trail and version control management</p>
            {{-- Existing auditing dashboard content goes here --}}
            @include('blueprint-auditing::dashboard._auditing-content')
        </div>
    </div>
@endsection 