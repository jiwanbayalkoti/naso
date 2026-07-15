@extends('layouts.app')

@section('title', 'Riders - ' . config('app.name'))
@section('page-title', 'Riders')

@section('content')
    @php
        $routes = [
            'datatable' => route('riders.datatable'),
            'store' => route('riders.store'),
            'edit' => route('riders.edit', ['rider' => '__ID__']),
            'update' => route('riders.update', ['rider' => '__ID__']),
            'destroy' => route('riders.destroy', ['rider' => '__ID__']),
            'toggleOnline' => route('riders.toggle-online', ['rider' => '__ID__']),
        ];
    @endphp

    <div id="riders-module" data-routes="{{ json_encode($routes) }}">
        <x-page-header
            title="Riders"
            subtitle="Manage delivery riders and their availability"
        >
            <x-slot:actions>
                <button type="button" class="btn btn-primary" id="btn-create-rider">
                    <i class="fa-solid fa-plus me-1"></i> Add Rider
                </button>
            </x-slot:actions>
        </x-page-header>

        <x-alert type="success" />
        <x-alert type="danger" />

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="filter-search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                            <input type="text" class="form-control" id="filter-search" placeholder="Name, email, phone, vehicle...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="filter-online" class="form-label">Online Status</label>
                        <select class="form-select" id="filter-online">
                            <option value="">All</option>
                            <option value="1">Online</option>
                            <option value="0">Offline</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filter-available" class="form-label">Availability</label>
                        <select class="form-select" id="filter-available">
                            <option value="">All</option>
                            <option value="1">Available</option>
                            <option value="0">Unavailable</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary flex-grow-1" id="btn-filter">
                                <i class="fa-solid fa-filter me-1"></i> Filter
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btn-reset-filters" title="Reset filters">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card table-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="riders-table" class="table table-hover w-100" data-url="{{ route('riders.datatable') }}">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Vehicle</th>
                                <th>Online</th>
                                <th>Available</th>
                                <th>Rating</th>
                                <th>Deliveries</th>
                                <th>Created</th>
                                <th class="text-end no-export">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <x-modal id="rider-modal" title="Add Rider" size="lg" scrollable>
            <x-slot:body>
                @include('riders.partials.form')
            </x-slot:body>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary modal-submit-btn" id="rider-form-submit">
                    <i class="fa-solid fa-save me-1"></i> Save Rider
                </button>
            </x-slot:footer>
        </x-modal>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/modules/riders/index.js') }}"></script>
@endpush
