@extends('layouts.track')

@section('title', 'Track ' . ($delivery->tracking_number ?? 'Delivery'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <h1 class="h4 mb-1">Track Delivery</h1>
                            <p class="text-muted mb-0">{{ $delivery->tracking_number }}</p>
                        </div>
                        <span class="badge bg-primary fs-6">{{ $tracking['status_label'] ?? ucfirst($delivery->status) }}</span>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <i class="fa-solid fa-map-location-dot me-2"></i>Live Map
                </div>
                <div class="card-body p-0">
                    <div id="delivery-tracking-map" class="delivery-tracking-map"></div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h6 text-muted">Pickup</h2>
                            <p class="mb-1 fw-semibold">{{ $tracking['pickup']['label'] ?? 'Pickup' }}</p>
                            <p class="mb-0">{{ $tracking['pickup']['address'] ?? $delivery->pickup_address }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h6 text-muted">Delivery</h2>
                            <p class="mb-1 fw-semibold">{{ $tracking['dropoff']['label'] ?? $delivery->customer_name }}</p>
                            <p class="mb-0">{{ $tracking['dropoff']['address'] ?? $delivery->delivery_address }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .delivery-tracking-map {
            width: 100%;
            min-height: 420px;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tracking = @json($tracking);
            const pollUrl = @json(route('deliveries.track', ['trackingNumber' => $delivery->tracking_number]));

            if (window.GoogleMapsTracker) {
                window.GoogleMapsTracker.render('delivery-tracking-map', tracking, {
                    pollUrl: pollUrl,
                    pollInterval: 15000,
                    showRoute: true,
                });
            }
        });
    </script>
@endpush
