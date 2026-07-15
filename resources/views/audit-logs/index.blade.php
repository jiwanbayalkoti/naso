@extends('layouts.app')

@section('title', 'Audit Logs - ' . config('app.name'))
@section('page-title', 'Audit Logs')

@section('content')
    @php
        $routes = [
            'datatable' => route('audit-logs.datatable'),
        ];
    @endphp

    <div id="audit-logs-module" data-routes="{{ json_encode($routes) }}">
        <x-page-header
            title="Audit Logs"
            subtitle="Record-level change audit trail"
        />

        {{-- Filters --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="filter-search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                            <input type="text" class="form-control" id="filter-search" placeholder="Model, event, IP...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="filter-event" class="form-label">Event</label>
                        <select class="form-select" id="filter-event">
                            <option value="">All events</option>
                            <option value="created">Created</option>
                            <option value="updated">Updated</option>
                            <option value="deleted">Deleted</option>
                            <option value="restored">Restored</option>
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
                    <div class="col-md-2">
                        <label for="filter-model" class="form-label">Model</label>
                        <select class="form-select" id="filter-model">
                            <option value="">All models</option>
                            <option value="App\Models\Delivery">Delivery</option>
                            <option value="App\Models\Shop">Shop</option>
                            <option value="App\Models\Rider">Rider</option>
                            <option value="App\Models\User">User</option>
                        </select>
                    </div>
                    <div class="col-md-2">
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
                    <table id="audit-logs-table" class="table table-hover w-100" data-url="{{ route('audit-logs.datatable') }}">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th>Event</th>
                                <th>Model</th>
                                <th>Record ID</th>
                                <th>Changes</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Detail Modal --}}
        <x-modal id="audit-detail-modal" title="Audit Log Details" size="lg" scrollable :showFooter="false">
            <x-slot:body>
                <div id="audit-detail-content"></div>
            </x-slot:body>
        </x-modal>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/modules/audit-logs/index.js') }}"></script>
@endpush
