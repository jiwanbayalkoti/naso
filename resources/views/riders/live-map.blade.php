@extends('layouts.app')

@section('title', 'Live Riders - ' . config('app.name'))
@section('page-title', 'Live Riders')

@section('content')
    <div id="rider-live-map-page"
         data-poll-url="{{ $pollUrl }}"
         data-is-shop="{{ !empty($isShop) ? '1' : '0' }}">
        <x-page-header
            title="Live Riders"
            :subtitle="$isShop
                ? 'Online riders and riders currently working on your deliveries'
                : 'Track all riders with a shared live location'"
        >
            <x-slot:actions>
                <button type="button" class="btn btn-outline-primary" id="rider-map-refresh">
                    <i class="fa-solid fa-rotate me-1"></i> Refresh
                </button>
            </x-slot:actions>
        </x-page-header>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body p-0">
                        <div id="rider-fleet-map" class="rider-fleet-map" aria-label="Rider live map"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Riders on map</span>
                        <span class="badge bg-primary" id="rider-map-count">0</span>
                    </div>
                    <div class="card-body p-0">
                        <div id="rider-map-list" class="rider-map-list list-group list-group-flush">
                            <div class="list-group-item text-muted small">Loading…</div>
                        </div>
                    </div>
                    <div class="card-footer small text-muted" id="rider-map-updated">
                        Waiting for locations…
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .rider-fleet-map {
            width: 100%;
            height: min(70vh, 560px);
            min-height: 320px;
            border-radius: 0.5rem;
            background: #e2e8f0;
        }
        .rider-map-list {
            max-height: min(70vh, 560px);
            overflow-y: auto;
        }
        .rider-map-list .list-group-item.active {
            background: rgba(37, 99, 235, 0.08);
            color: inherit;
            border-color: rgba(37, 99, 235, 0.2);
        }
        .rider-map-dot {
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 50%;
            display: inline-block;
        }
        .rider-map-dot.is-online { background: #16a34a; }
        .rider-map-dot.is-offline { background: #94a3b8; }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/core/google-maps-tracker.js') }}"></script>
    <script src="{{ asset('js/modules/riders/live-map.js') }}"></script>
@endpush
