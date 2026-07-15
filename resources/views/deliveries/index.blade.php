@extends('layouts.app')

@section('title', 'Deliveries - ' . config('app.name'))
@section('page-title', 'Deliveries')

@section('content')
    @php
        use App\Helpers\DeliveryStatus;

        $routes = [
            'datatable' => route('deliveries.datatable'),
            'store' => route('deliveries.store'),
            'edit' => route('deliveries.edit', ['delivery' => '__ID__']),
            'update' => route('deliveries.update', ['delivery' => '__ID__']),
            'destroy' => route('deliveries.destroy', ['delivery' => '__ID__']),
            'show' => route('deliveries.show', ['delivery' => '__ID__']),
            'assign' => route('deliveries.assign', ['delivery' => '__ID__']),
            'rejectAssignment' => route('deliveries.reject-assignment', ['delivery' => '__ID__']),
            'status' => route('deliveries.status', ['delivery' => '__ID__']),
            'assignableRiders' => route('riders.assignable'),
        ];

        $statusLabels = DeliveryStatus::labels();
        $isRiderUser = $isRiderUser ?? false;
        $assignedCount = $assignedCount ?? 0;
    @endphp

    <div id="deliveries-module"
         data-routes="{{ json_encode($routes) }}"
         @if($isRiderUser) data-is-rider="1" @endif>
        <x-page-header
            title="{{ $isRiderUser ? 'My Deliveries' : 'Deliveries' }}"
            subtitle="{{ $isRiderUser ? 'Accept assignments and update delivery progress' : 'Track and manage all delivery orders' }}"
        >
            @unless($isRiderUser)
            <x-slot:actions>
                <button type="button" class="btn btn-primary" id="btn-create-delivery">
                    <i class="fa-solid fa-plus me-1"></i> New Delivery
                </button>
            </x-slot:actions>
            @endunless
        </x-page-header>

        @if($isRiderUser && $assignedCount > 0)
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="fa-solid fa-bell fa-lg"></i>
                <div>
                    <strong>{{ $assignedCount }} new {{ Str::plural('assignment', $assignedCount) }}</strong>
                    waiting for your response. Please accept or reject below.
                </div>
            </div>
        @endif

        <x-alert type="success" />
        <x-alert type="danger" />

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="filter-search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                            <input type="text" class="form-control" id="filter-search" placeholder="Tracking #, customer...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="filter-status" class="form-label">Status</label>
                        <select class="form-select" id="filter-status">
                            <option value="">All statuses</option>
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @unless($isRiderUser)
                    <div class="col-md-2">
                        <label for="filter-shop" class="form-label">Shop</label>
                        <select class="form-select select2" id="filter-shop" data-placeholder="All shops">
                            <option value=""></option>
                            @foreach($shops ?? [] as $shop)
                                <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filter-rider" class="form-label">Rider</label>
                        <select class="form-select select2" id="filter-rider" data-placeholder="All riders">
                            <option value=""></option>
                            @foreach($riders ?? [] as $rider)
                                <option value="{{ $rider->id }}">{{ $rider->user->name ?? 'Rider #' . $rider->id }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endunless
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="filter-date-from" placeholder="From">
                            <span class="input-group-text">to</span>
                            <input type="date" class="form-control" id="filter-date-to" placeholder="To">
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

        <div class="card table-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="deliveries-table" class="table table-hover w-100" data-url="{{ route('deliveries.datatable') }}">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Shop</th>
                                <th>Customer</th>
                                <th>Rider</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Fee</th>
                                <th>Payment</th>
                                <th>Created</th>
                                <th class="text-end no-export">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        @unless($isRiderUser)
        <x-modal id="delivery-modal" title="New Delivery" size="lg" scrollable>
            <x-slot:body>
                @include('deliveries.partials.form', ['shops' => $shops ?? []])
            </x-slot:body>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary modal-submit-btn" id="delivery-form-submit">
                    <i class="fa-solid fa-save me-1"></i> Save Delivery
                </button>
            </x-slot:footer>
        </x-modal>

        <x-modal id="assign-rider-modal" title="Assign Rider" size="md">
            <x-slot:body>
                <form id="assign-rider-form" novalidate>
                    <input type="hidden" name="delivery_id" id="assign-delivery-id" value="">
                    <p class="text-muted mb-3">
                        Assigning rider to delivery <strong id="assign-tracking-label">—</strong>
                    </p>
                    <div class="mb-3">
                        <label for="assign_rider_id" class="form-label">Select Rider <span class="text-danger">*</span></label>
                        <select class="form-select select2" name="rider_id" id="assign_rider_id" data-placeholder="Choose available rider" required>
                            <option value=""></option>
                        </select>
                        <div id="assign-no-riders" class="alert alert-warning d-none mt-2 mb-0 small">
                            No approved riders found. Ask admin to approve rider registrations first.
                        </div>
                        <div class="invalid-feedback d-block" data-field="rider_id"></div>
                    </div>
                    <div class="mb-0">
                        <label for="assign_notes" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="assign_notes" rows="2" placeholder="Optional assignment notes"></textarea>
                    </div>
                </form>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="assign-rider-submit">
                    <i class="fa-solid fa-motorcycle me-1"></i> Assign Rider
                </button>
            </x-slot:footer>
        </x-modal>
        @endunless

        <x-modal id="delivery-detail-modal" title="Delivery Details" size="lg" scrollable :showFooter="false">
            <x-slot:body>
                <div id="delivery-detail-content">
                    <div class="text-center py-5">
                        <span class="spinner-border spinner-border-sm me-2"></span>
                        Loading delivery details...
                    </div>
                </div>
            </x-slot:body>
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
    <script src="{{ asset('js/modules/deliveries/index.js') }}"></script>
@endpush
