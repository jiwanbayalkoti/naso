@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name'))
@section('page-title', 'Dashboard')

@section('content')
    @php
        $isRiderDashboard = auth()->user()->hasRole('rider');
        $dashboardStats = $isRiderDashboard
            ? [
                ['key' => 'assigned_deliveries', 'label' => 'Awaiting Accept', 'icon' => 'fa-bell', 'color' => 'warning'],
                ['key' => 'active_deliveries', 'label' => 'Active', 'icon' => 'fa-route', 'color' => 'info'],
                ['key' => 'completed_deliveries', 'label' => 'Completed', 'icon' => 'fa-circle-check', 'color' => 'success'],
                ['key' => 'total_deliveries', 'label' => 'My Deliveries', 'icon' => 'fa-box', 'color' => 'primary'],
            ]
            : [
                ['key' => 'total_deliveries', 'label' => 'Total Deliveries', 'icon' => 'fa-box', 'color' => 'primary'],
                ['key' => 'pending_deliveries', 'label' => 'Pending', 'icon' => 'fa-clock', 'color' => 'warning'],
                ['key' => 'completed_deliveries', 'label' => 'Completed', 'icon' => 'fa-circle-check', 'color' => 'success'],
                ['key' => 'online_riders', 'label' => 'Online Riders', 'icon' => 'fa-motorcycle', 'color' => 'info'],
            ];
    @endphp

    <div id="dashboard-page" @if($isRiderDashboard) data-rider-dashboard="1" @endif>
        <div class="row g-4 mb-4" id="dashboard-stats" data-url="{{ route('api.dashboard.stats') }}">
            @foreach($dashboardStats as $stat)
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card stat-card-{{ $stat['color'] }}" data-stat="{{ $stat['key'] }}">
                        <div class="stat-card-loading">
                            <div class="placeholder-glow">
                                <span class="placeholder col-6"></span>
                            </div>
                        </div>
                        <div class="stat-card-content d-none">
                            <div class="stat-card-icon">
                                <i class="fa-solid {{ $stat['icon'] }}"></i>
                            </div>
                            <div class="stat-card-body">
                                <div class="stat-card-value" data-value>0</div>
                                <div class="stat-card-label">{{ $stat['label'] }}</div>
                                <div class="stat-card-trend d-none" data-trend></div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card chart-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Delivery Trends</h5>
                        <select class="form-select form-select-sm w-auto" id="chart-period">
                            <option value="7">Last 7 days</option>
                            <option value="30" selected>Last 30 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                    </div>
                    <div class="card-body">
                        <div id="delivery-trends-chart" class="chart-wrapper" data-url="{{ route('api.dashboard.trends') }}">
                            <div class="chart-placeholder text-center text-muted py-5">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                                <p class="mb-0 mt-2 small">Loading chart...</p>
                            </div>
                            <canvas id="delivery-trends-canvas" class="d-none" height="280"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Delivery Status</h5>
                    </div>
                    <div class="card-body">
                        <div id="delivery-status-chart" class="chart-wrapper chart-wrapper-sm" data-url="{{ route('api.dashboard.status-chart') }}">
                            <div class="chart-placeholder text-center text-muted py-5">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                                <p class="mb-0 mt-2 small">Loading chart...</p>
                            </div>
                            <canvas id="delivery-status-canvas" class="d-none" height="260"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card table-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Latest Deliveries</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" id="latest-deliveries" data-url="{{ route('api.dashboard.latest-deliveries') }}">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Tracking #</th>
                                        <th>Shop</th>
                                        <th>Rider</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody id="latest-deliveries-body">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                @unless($isRiderDashboard)
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Pending Deliveries</h5>
                        <span class="badge bg-warning" id="pending-count-badge">0</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="pending-list" id="pending-deliveries-list" data-url="{{ route('api.dashboard.pending-deliveries') }}">
                            <div class="text-center text-muted py-4">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Online Riders</h5>
                        <span class="badge bg-success" id="online-riders-count-badge">0 online</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="riders-list" id="online-riders-list" data-url="{{ route('api.dashboard.online-riders') }}">
                            <div class="text-center text-muted py-4">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </div>
                        </div>
                    </div>
                </div>
                @else
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">New delivery assignments appear on your deliveries page. Accept or reject them, then update status as you complete each trip.</p>
                        <a href="{{ route('deliveries.index') }}" class="btn btn-primary w-100">
                            <i class="fa-solid fa-box me-1"></i> View My Deliveries
                        </a>
                    </div>
                </div>
                @endunless
            </div>
        </div>
    </div>
@endsection

@push('charts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endpush

@push('scripts')
    <script src="{{ asset('js/dashboard/index.js') }}"></script>
@endpush
