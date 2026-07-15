@extends('layouts.app')

@section('title', 'Users - ' . config('app.name'))
@section('page-title', 'Users')

@section('content')
    @php
        $routes = [
            'datatable' => route('users.datatable'),
            'store' => route('users.store'),
            'edit' => route('users.edit', ['user' => '__ID__']),
            'update' => route('users.update', ['user' => '__ID__']),
            'destroy' => route('users.destroy', ['user' => '__ID__']),
        ];
    @endphp

    <div id="users-module" data-routes="{{ json_encode($routes) }}">
        <x-page-header
            title="Users"
            subtitle="Manage system users and role assignments"
        >
            <x-slot:actions>
                <button type="button" class="btn btn-primary" id="btn-create-user">
                    <i class="fa-solid fa-plus me-1"></i> Add User
                </button>
            </x-slot:actions>
        </x-page-header>

        <x-alert type="success" />
        <x-alert type="danger" />

        {{-- Filters --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="filter-search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                            <input type="text" class="form-control" id="filter-search" placeholder="Name, email, phone...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="filter-role" class="form-label">Role</label>
                        <select class="form-select" id="filter-role">
                            <option value="">All roles</option>
                            @foreach($roles ?? ['admin', 'manager', 'shop', 'rider'] as $role)
                                <option value="{{ is_object($role) ? $role->name : $role }}">
                                    {{ ucfirst(is_object($role) ? $role->name : $role) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filter-status" class="form-label">Status</label>
                        <select class="form-select" id="filter-status">
                            <option value="">All statuses</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
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

        {{-- DataTable --}}
        <div class="card table-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="users-table" class="table table-hover w-100" data-url="{{ route('users.datatable') }}">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created</th>
                                <th class="text-end no-export">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Create/Edit Modal --}}
        <x-modal id="user-modal" title="Add User" size="lg" scrollable>
            <x-slot:body>
                @include('users.partials.form', ['roles' => $roles ?? []])
            </x-slot:body>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary modal-submit-btn" id="user-form-submit">
                    <i class="fa-solid fa-save me-1"></i> Save User
                </button>
            </x-slot:footer>
        </x-modal>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/modules/users/index.js') }}"></script>
@endpush
