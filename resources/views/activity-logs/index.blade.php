@extends('layouts.app')

@section('title', 'Activity Logs - ' . config('app.name'))
@section('page-title', 'Activity Logs')

@section('content')
    @php
        use App\Helpers\ActivityType;

        $routes = [
            'datatable' => route('activity-logs.datatable'),
        ];

        $activityTypes = ActivityType::all();
        $activityLabels = array_map(function ($type) {
            return ucwords(str_replace('_', ' ', $type));
        }, array_combine($activityTypes, $activityTypes));
    @endphp

    <div id="activity-logs-module" data-routes="{{ json_encode($routes) }}">
        <x-page-header
            title="Activity Logs"
            subtitle="System activity and event history"
        />

        {{-- Filters --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="filter-search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                            <input type="text" class="form-control" id="filter-search" placeholder="Description, IP...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="filter-type" class="form-label">Activity Type</label>
                        <select class="form-select" id="filter-type">
                            <option value="">All types</option>
                            @foreach($activityTypes as $type)
                                <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filter-user" class="form-label">User</label>
                        <select class="form-select select2" id="filter-user" data-placeholder="All users">
                            <option value=""></option>
                            @foreach($users ?? [] as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="filter-date-from">
                            <span class="input-group-text">to</span>
                            <input type="date" class="form-control" id="filter-date-to">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-primary" id="btn-filter">
                                <i class="fa-solid fa-filter me-1"></i> Filter
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btn-reset-filters">
                                <i class="fa-solid fa-rotate-left me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- DataTable --}}
        <div class="card table-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="activity-logs-table" class="table table-hover w-100" data-url="{{ route('activity-logs.datatable') }}">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Subject</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/modules/activity-logs/index.js') }}"></script>
@endpush
