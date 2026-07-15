@extends('layouts.app')

@section('title', 'Registration Requests - ' . config('app.name'))
@section('page-title', 'Registration Requests')

@section('content')
    @php
        $routes = [
            'datatable' => route('registration-requests.datatable'),
            'show' => route('registration-requests.show', ['type' => '__TYPE__', 'uuid' => '__ID__']),
            'approve' => route('registration-requests.approve', ['type' => '__TYPE__', 'uuid' => '__ID__']),
            'reject' => route('registration-requests.reject', ['type' => '__TYPE__', 'uuid' => '__ID__']),
        ];
    @endphp

    <div id="registration-requests-module" data-routes="{{ json_encode($routes) }}">
        <x-page-header
            title="Registration Requests"
            :subtitle="'Review shop and rider registrations (' . ($pendingCount ?? 0) . ' pending)'"
        />

        <x-alert type="success" />
        <x-alert type="danger" />

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="filter-search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                            <input type="text" class="form-control" id="filter-search" placeholder="Name, email...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="filter-type" class="form-label">Type</label>
                        <select class="form-select" id="filter-type">
                            <option value="">All types</option>
                            <option value="shop">Shop</option>
                            <option value="rider">Rider</option>
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
                    <table id="registration-requests-table" class="table table-hover w-100" data-url="{{ route('registration-requests.datatable') }}">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Documents</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th class="text-end no-export">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <x-modal id="review-modal" title="Review Registration" size="xl" scrollable>
            <x-slot:body>
                <div id="review-content">
                    <div class="text-center py-4 text-muted">Loading...</div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="review-reject-btn">
                    <i class="fa-solid fa-xmark me-1"></i> Reject
                </button>
                <button type="button" class="btn btn-success" id="review-approve-btn">
                    <i class="fa-solid fa-check me-1"></i> Approve
                </button>
            </x-slot:footer>
        </x-modal>

        <x-modal id="reject-modal" title="Reject Registration" size="md">
            <x-slot:body>
                <form id="reject-form" novalidate>
                    <div class="mb-3">
                        <label for="reject_reason" class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_reason" name="reason" rows="4" required placeholder="Explain why this registration is rejected..."></textarea>
                        <div class="invalid-feedback d-block" data-field="reason"></div>
                    </div>
                </form>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="reject-submit-btn">
                    <i class="fa-solid fa-xmark me-1"></i> Confirm Reject
                </button>
            </x-slot:footer>
        </x-modal>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/modules/registration-requests/index.js') }}"></script>
@endpush
