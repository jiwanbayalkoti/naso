@extends('layouts.app')

@section('title', 'Shops - ' . config('app.name'))
@section('page-title', 'Shops')

@section('content')
    @php
        $routes = [
            'datatable' => route('shops.datatable'),
            'store' => route('shops.store'),
            'edit' => route('shops.edit', ['shop' => '__ID__']),
            'update' => route('shops.update', ['shop' => '__ID__']),
            'destroy' => route('shops.destroy', ['shop' => '__ID__']),
        ];
    @endphp

    <div id="shops-module" data-routes="{{ json_encode($routes) }}">
        <x-page-header
            title="Shops"
            subtitle="Manage shop locations and owner accounts"
        >
            <x-slot:actions>
                <button type="button" class="btn btn-primary" id="btn-create-shop">
                    <i class="fa-solid fa-plus me-1"></i> Add Shop
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
                            <input type="text" class="form-control" id="filter-search" placeholder="Name, email, phone, city...">
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
                    <div class="col-md-3">
                        <label for="filter-city" class="form-label">City</label>
                        <input type="text" class="form-control" id="filter-city" placeholder="Filter by city" list="filter-cities-list">
                        <datalist id="filter-cities-list">
                            @foreach($cities ?? [] as $city)
                                <option value="{{ $city }}">
                            @endforeach
                        </datalist>
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
                    <table id="shops-table" class="table table-hover w-100" data-url="{{ route('shops.datatable') }}">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>City</th>
                                <th>Owner</th>
                                <th>Status</th>
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

        <x-modal id="shop-modal" title="Add Shop" size="lg" scrollable>
            <x-slot:body>
                @include('shops.partials.form', ['cities' => $cities ?? []])
            </x-slot:body>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary modal-submit-btn" id="shop-form-submit">
                    <i class="fa-solid fa-save me-1"></i> Save Shop
                </button>
            </x-slot:footer>
        </x-modal>

        <x-modal id="location-picker-modal" title="Pick location on map" size="lg">
            <x-slot:body>
                <p class="text-muted small mb-2">Tap on the map to set the location. Address will be filled automatically.</p>
                <div id="location-picker-map" style="width:100%;min-height:360px;border-radius:0.5rem;"></div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="location-picker-apply">
                    <i class="fa-solid fa-check me-1"></i> Use this location
                </button>
            </x-slot:footer>
        </x-modal>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/core/google-maps-tracker.js') }}"></script>
    <script src="{{ asset('js/core/address-autocomplete.js') }}"></script>
    <script src="{{ asset('js/core/location-helper.js') }}"></script>
    <script src="{{ asset('js/modules/shops/index.js') }}"></script>
@endpush
