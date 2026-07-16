@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name'))
@section('page-title', 'Dashboard')

@section('content')
    @php
        $isRiderDashboard = auth()->user()->hasRole('rider');
        $isShopDashboard = auth()->user()->hasRole('shop');
        $isAdminDashboard = auth()->user()->hasRole('super_admin');
        $dashboardStats = $isRiderDashboard
            ? [
                ['key' => 'my_earning', 'label' => 'My Earning', 'icon' => 'fa-coins', 'color' => 'success', 'money' => true],
                ['key' => 'assigned_deliveries', 'label' => 'Awaiting Accept', 'icon' => 'fa-bell', 'color' => 'warning'],
                ['key' => 'active_deliveries', 'label' => 'Active', 'icon' => 'fa-route', 'color' => 'info'],
                ['key' => 'completed_deliveries', 'label' => 'Completed', 'icon' => 'fa-circle-check', 'color' => 'primary'],
            ]
            : ($isShopDashboard
                ? [
                    ['key' => 'my_earning', 'label' => 'My Balance', 'icon' => 'fa-wallet', 'color' => 'success', 'money' => true],
                    ['key' => 'total_deliveries', 'label' => 'Total Deliveries', 'icon' => 'fa-box', 'color' => 'primary'],
                    ['key' => 'pending_deliveries', 'label' => 'Pending', 'icon' => 'fa-clock', 'color' => 'warning'],
                    ['key' => 'completed_deliveries', 'label' => 'Completed', 'icon' => 'fa-circle-check', 'color' => 'info'],
                ]
                : [
                    ['key' => 'my_earning', 'label' => 'My Earning', 'icon' => 'fa-coins', 'color' => 'success', 'money' => true],
                    ['key' => 'total_deliveries', 'label' => 'Total Deliveries', 'icon' => 'fa-box', 'color' => 'primary'],
                    ['key' => 'pending_deliveries', 'label' => 'Pending', 'icon' => 'fa-clock', 'color' => 'warning'],
                    ['key' => 'completed_deliveries', 'label' => 'Completed', 'icon' => 'fa-circle-check', 'color' => 'info'],
                    ['key' => 'online_riders', 'label' => 'Online Riders', 'icon' => 'fa-motorcycle', 'color' => 'primary'],
                    ['key' => 'pending_payout_total', 'label' => 'Pending Payouts', 'icon' => 'fa-hourglass-half', 'color' => 'warning', 'money' => true],
                    ['key' => 'paid_payout_total', 'label' => 'Paid Out', 'icon' => 'fa-money-bill-transfer', 'color' => 'success', 'money' => true],
                ]);
    @endphp

    <div id="dashboard-page"
         @if($isRiderDashboard) data-rider-dashboard="1" @endif
         data-payment-history-url="{{ route('payouts.index') }}">
        <div class="row g-4 mb-4" id="dashboard-stats" data-url="{{ route('api.dashboard.stats') }}">
            @foreach($dashboardStats as $stat)
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card stat-card-{{ $stat['color'] }} {{ ($stat['key'] ?? '') === 'my_earning' ? 'stat-card-clickable' : '' }}"
                         data-stat="{{ $stat['key'] }}"
                         @if(!empty($stat['money'])) data-money="1" @endif
                         @if(($stat['key'] ?? '') === 'my_earning') data-href="{{ route('payouts.index') }}" role="button" tabindex="0" @endif>
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

        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-1">Payment History</h5>
                    <p class="text-muted small mb-0">
                        @if($isAdminDashboard)
                            All shop/rider payout requests and transfers.
                        @else
                            Your payout requests and paid transfers.
                        @endif
                    </p>
                </div>
                <a href="{{ route('payouts.index') }}" class="btn btn-outline-primary">
                    <i class="fa-solid fa-receipt me-1"></i> View payment history
                </a>
            </div>
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
    <script src="{{ asset('js/dashboard/index.js') }}?v=20260716-earning"></script>
@endpush
