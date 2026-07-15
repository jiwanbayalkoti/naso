@extends('layouts.app')

@section('title', 'Delivery #' . ($delivery->tracking_number ?? $delivery->id) . ' - ' . config('app.name'))
@section('page-title', 'Delivery Details')

@section('content')
    @php
        use App\Helpers\DeliveryStatus;

        $statusLabels = DeliveryStatus::labels();
        $statusClass = str_replace('_', '-', $delivery->status);
        $routes = [
            'index' => route('deliveries.index'),
            'status' => route('deliveries.status', ['delivery' => $delivery->uuid]),
            'tracking' => route('deliveries.tracking', ['delivery' => $delivery->uuid]),
            'assign' => route('deliveries.assign', ['delivery' => $delivery->uuid]),
            'rejectAssignment' => route('deliveries.reject-assignment', ['delivery' => $delivery->uuid]),
            'edit' => route('deliveries.edit', ['delivery' => $delivery->uuid]),
            'update' => route('deliveries.update', ['delivery' => $delivery->uuid]),
            'assignableRiders' => route('riders.assignable'),
        ];
        $isRiderUser = $isRiderUser ?? false;
        $riderActionStatuses = DeliveryStatus::riderActionStatuses($delivery->status);
        $riderActionLabels = DeliveryStatus::riderActionLabels();
    @endphp

    <div id="delivery-show-module" data-routes="{{ json_encode($routes) }}" data-delivery-id="{{ $delivery->uuid }}" @if($isRiderUser) data-is-rider="1" @endif>
        <x-page-header
            :title="'Delivery ' . $delivery->tracking_number"
            :subtitle="'Created ' . $delivery->created_at->format('M d, Y h:i A')"
            :breadcrumb="[
                ['label' => 'Deliveries', 'url' => route('deliveries.index')],
                ['label' => $delivery->tracking_number],
            ]"
        >
            <x-slot:actions>
                <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
                @if($isRiderUser)
                    @if($delivery->status === DeliveryStatus::ASSIGNED)
                        <button type="button" class="btn btn-success btn-rider-accept" data-id="{{ $delivery->uuid }}" data-tracking="{{ $delivery->tracking_number }}">
                            <i class="fa-solid fa-check me-1"></i> Accept
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-rider-reject" data-id="{{ $delivery->uuid }}" data-tracking="{{ $delivery->tracking_number }}">
                            <i class="fa-solid fa-xmark me-1"></i> Reject
                        </button>
                    @endif
                    @foreach($riderActionStatuses as $nextStatus)
                        @continue($delivery->status === DeliveryStatus::ASSIGNED)
                        <button type="button"
                                class="btn btn-primary btn-rider-status"
                                data-id="{{ $delivery->uuid }}"
                                data-status="{{ $nextStatus }}"
                                data-tracking="{{ $delivery->tracking_number }}">
                            {{ $riderActionLabels[$nextStatus] ?? ucfirst(str_replace('_', ' ', $nextStatus)) }}
                        </button>
                    @endforeach
                @else
                    <button type="button" class="btn btn-outline-info btn-assign" data-id="{{ $delivery->uuid }}" data-tracking="{{ $delivery->tracking_number }}">
                        <i class="fa-solid fa-motorcycle me-1"></i> Assign Rider
                    </button>
                    <button type="button" class="btn btn-primary btn-edit-page" data-id="{{ $delivery->uuid }}">
                        <i class="fa-solid fa-pen me-1"></i> Edit
                    </button>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="row g-4">
            {{-- Main Info --}}
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-box me-2"></i>Delivery Information</span>
                        <span class="badge-status {{ $statusClass }}">{{ $statusLabels[$delivery->status] ?? ucfirst($delivery->status) }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="text-muted small d-block">Tracking Number</label>
                                <strong>{{ $delivery->tracking_number }}</strong>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small d-block">Priority</label>
                                <span class="badge bg-{{ $delivery->priority === 'urgent' ? 'danger' : ($delivery->priority === 'high' ? 'warning text-dark' : 'secondary') }}">
                                    {{ ucfirst($delivery->priority) }}
                                </span>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small d-block">Shop</label>
                                <strong>{{ $delivery->shop->name ?? '—' }}</strong>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small d-block">Assigned Rider</label>
                                <strong>{{ $delivery->rider->user->name ?? 'Unassigned' }}</strong>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small d-block">Customer</label>
                                <strong>{{ $delivery->customer_name }}</strong>
                                <div class="text-muted small">{{ $delivery->customer_phone }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small d-block">Delivery Fee</label>
                                <strong>${{ number_format($delivery->delivery_fee, 2) }}</strong>
                                <div class="text-muted small">
                                    {{ ucfirst($delivery->payment_method ?? 'N/A') }} · {{ ucfirst($delivery->payment_status) }}
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="text-muted small d-block">Pickup Address</label>
                                <p class="mb-0">{{ $delivery->pickup_address }}</p>
                            </div>
                            <div class="col-12">
                                <label class="text-muted small d-block">Delivery Address</label>
                                <p class="mb-0">{{ $delivery->delivery_address }}</p>
                            </div>
                            @if($delivery->notes)
                                <div class="col-12">
                                    <label class="text-muted small d-block">Notes</label>
                                    <p class="mb-0">{{ $delivery->notes }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-map-location-dot me-2"></i>Live Tracking</span>
                        @if($tracking['is_live'] ?? false)
                            <span class="badge bg-success">Live</span>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <div id="delivery-tracking-map" class="delivery-tracking-map"></div>
                    </div>
                </div>

                {{-- Status Timeline --}}
                <div class="card">
                    <div class="card-header">
                        <i class="fa-solid fa-timeline me-2"></i>Status Timeline
                    </div>
                    <div class="card-body">
                        <div class="delivery-timeline" id="delivery-timeline">
                            @forelse($delivery->statusHistories->sortByDesc('created_at') as $history)
                                @php
                                    $histStatusClass = str_replace('_', '-', $history->status);
                                @endphp
                                <div class="delivery-timeline-item">
                                    <div class="delivery-timeline-marker">
                                        <i class="fa-solid fa-circle"></i>
                                    </div>
                                    <div class="delivery-timeline-content">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <span class="badge-status {{ $histStatusClass }}">
                                                    {{ $statusLabels[$history->status] ?? ucfirst(str_replace('_', ' ', $history->status)) }}
                                                </span>
                                                @if($history->previous_status)
                                                    <small class="text-muted ms-2">
                                                        from {{ $statusLabels[$history->previous_status] ?? $history->previous_status }}
                                                    </small>
                                                @endif
                                            </div>
                                            <small class="text-muted">{{ $history->created_at->format('M d, Y h:i A') }}</small>
                                        </div>
                                        @if($history->notes)
                                            <p class="text-muted small mb-0 mt-1">{{ $history->notes }}</p>
                                        @endif
                                        @if($history->changedBy)
                                            <small class="text-muted">by {{ $history->changedBy->name }}</small>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted py-3">
                                    <i class="fa-solid fa-clock-rotate-left d-block mb-2"></i>
                                    No status history recorded yet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                @unless($isRiderUser)
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fa-solid fa-arrows-rotate me-2"></i>Update Status
                    </div>
                    <div class="card-body">
                        <form id="show-status-form">
                            <div class="mb-3">
                                <select class="form-select" name="status" id="show-status-select">
                                    @foreach($statusLabels as $value => $label)
                                        <option value="{{ $value }}" {{ $delivery->status === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <textarea class="form-control" name="notes" id="show-status-notes" rows="2" placeholder="Status change notes (optional)"></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning w-100" id="show-status-submit">
                                <i class="fa-solid fa-check me-1"></i> Update Status
                            </button>
                        </form>
                    </div>
                </div>
                @endunless

                <div class="card">
                    <div class="card-header">
                        <i class="fa-solid fa-clock me-2"></i>Key Timestamps
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Created</span>
                                <span>{{ $delivery->created_at?->format('M d, Y h:i A') ?? '—' }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Assigned</span>
                                <span>{{ $delivery->assigned_at?->format('M d, Y h:i A') ?? '—' }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Picked Up</span>
                                <span>{{ $delivery->picked_up_at?->format('M d, Y h:i A') ?? '—' }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Delivered</span>
                                <span>{{ $delivery->delivered_at?->format('M d, Y h:i A') ?? '—' }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2">
                                <span class="text-muted">Completed</span>
                                <span>{{ $delivery->completed_at?->format('M d, Y h:i A') ?? '—' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        @unless($isRiderUser)
        {{-- Assign Rider Modal --}}
        <x-modal id="assign-rider-modal" title="Assign Rider" size="md">
            <x-slot:body>
                <form id="assign-rider-form" novalidate>
                    <input type="hidden" name="delivery_id" id="assign-delivery-id" value="{{ $delivery->uuid }}">
                    <p class="text-muted mb-3">
                        Assigning rider to delivery <strong id="assign-tracking-label">{{ $delivery->tracking_number }}</strong>
                    </p>
                    <div class="mb-3">
                        <label for="assign_rider_id" class="form-label">Select Rider <span class="text-danger">*</span></label>
                        <select class="form-select select2" name="rider_id" id="assign_rider_id" data-placeholder="Choose available rider" required>
                            <option value=""></option>
                            @foreach($riders ?? [] as $rider)
                                <option value="{{ $rider->id }}" {{ $delivery->rider_id === $rider->id ? 'selected' : '' }}>
                                    {{ $rider->user->name ?? 'Rider #' . $rider->id }}
                                    {{ $rider->is_online ? '(Online)' : '(Offline)' }}
                                </option>
                            @endforeach
                        </select>
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
    </div>
@endsection

@push('styles')
    <style>
        .delivery-timeline { position: relative; padding-left: 1.5rem; }
        .delivery-timeline::before {
            content: '';
            position: absolute;
            left: 0.4rem;
            top: 0.5rem;
            bottom: 0.5rem;
            width: 2px;
            background: var(--dms-border, #e2e8f0);
        }
        .delivery-timeline-item {
            position: relative;
            padding-bottom: 1.25rem;
        }
        .delivery-timeline-item:last-child { padding-bottom: 0; }
        .delivery-timeline-marker {
            position: absolute;
            left: -1.35rem;
            top: 0.15rem;
            color: var(--dms-primary, #2563eb);
            font-size: 0.5rem;
            background: #fff;
            line-height: 1;
        }
        .delivery-timeline-content {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid var(--dms-border, #e2e8f0);
        }
        .badge-status.assigned, .badge-status.accepted, .badge-status.picked-up,
        .badge-status.on-the-way { background: #dbeafe; color: #1e40af; }
        .badge-status.completed { background: #dcfce7; color: #166534; }
        .delivery-tracking-map { width: 100%; min-height: 360px; }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/core/google-maps-tracker.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tracking = @json($tracking);
            const pollUrl = @json(route('deliveries.tracking', ['delivery' => $delivery->uuid]));

            if (window.GoogleMapsTracker) {
                window.GoogleMapsTracker.render('delivery-tracking-map', tracking, {
                    pollUrl: pollUrl,
                    pollInterval: 15000,
                    showRoute: true,
                });
            }
        });
    </script>
    <script src="{{ asset('js/modules/deliveries/index.js') }}"></script>
@endpush
