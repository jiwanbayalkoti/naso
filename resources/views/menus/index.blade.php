@extends('layouts.app')

@section('title', 'Menus - ' . config('app.name'))
@section('page-title', 'Menus')

@section('content')
    @php
        $routes = [
            'datatable' => route('menus.datatable'),
            'store' => route('menus.store'),
            'edit' => route('menus.edit', ['menu' => '__ID__']),
            'update' => route('menus.update', ['menu' => '__ID__']),
            'destroy' => route('menus.destroy', ['menu' => '__ID__']),
        ];
    @endphp

    <div id="menus-module" data-routes="{{ json_encode($routes) }}">
        <x-page-header
            title="Menus"
            subtitle="Manage sidebar navigation items and visibility"
        >
            <x-slot:actions>
                <button type="button" class="btn btn-primary" id="btn-create-menu">
                    <i class="fa-solid fa-plus me-1"></i> Add Menu
                </button>
            </x-slot:actions>
        </x-page-header>

        <x-alert type="success" />
        <x-alert type="danger" />

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="filter-search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                            <input type="text" class="form-control" id="filter-search" placeholder="Title, route, permission...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="filter-status" class="form-label">Status</label>
                        <select class="form-select" id="filter-status">
                            <option value="">All statuses</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
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
                    <table id="menus-table" class="table table-hover w-100" data-url="{{ route('menus.datatable') }}">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Route</th>
                                <th>Permission</th>
                                <th>Parent</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end no-export">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <x-modal id="menu-modal" title="Add Menu" size="lg" scrollable>
            <x-slot:body>
                @include('menus.partials.form', [
                    'parentMenus' => $parentMenus ?? [],
                    'permissions' => $permissions ?? [],
                    'routeNames' => $routeNames ?? [],
                ])
            </x-slot:body>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary modal-submit-btn" id="menu-form-submit">
                    <i class="fa-solid fa-save me-1"></i> Save Menu
                </button>
            </x-slot:footer>
        </x-modal>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/modules/menus/index.js') }}"></script>
@endpush
