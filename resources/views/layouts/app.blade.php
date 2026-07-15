<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="login-url" content="{{ route('login') }}">
    <meta name="google-maps-api-key" content="{{ $googleMapsApiKey ?? '' }}">
    <title>@yield('title', config('app.name', 'NASO Delivery'))</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <link href="{{ asset('css/app-custom.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body class="admin-body" data-dashboard-refresh="{{ (int) env('DASHBOARD_REFRESH_INTERVAL', 30) * 1000 }}">
    @php
        $branding = app(\App\Services\AppSettingService::class)->publicPayload();
    @endphp
    <div id="global-loading-overlay" class="loading-overlay" aria-hidden="true">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="loading-text mt-3 mb-0">Please wait...</p>
        </div>
    </div>

    <div class="admin-wrapper">
        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="sidebar-brand" id="sidebar-brand">
                @if(!empty($branding['app_logo_url']))
                    <img src="{{ $branding['app_logo_url'] }}" alt="{{ $branding['app_name'] }}" class="sidebar-brand-logo" id="sidebar-brand-logo">
                @else
                    <i class="fa-solid fa-truck-fast" id="sidebar-brand-icon"></i>
                @endif
                <span id="sidebar-brand-name">{{ $branding['app_name'] ?? config('app.name', 'NASO') }}</span>
            </div>

            @include('layouts.partials.sidebar-nav')
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <button type="button" class="btn btn-link sidebar-toggle d-lg-none" id="sidebar-toggle">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="topbar-title">
                    @hasSection('page-title')
                        <h1 class="h5 mb-0">@yield('page-title')</h1>
                    @endif
                </div>

                <div class="topbar-actions">
                    @if(auth()->user()->hasRole('rider') && auth()->user()->rider)
                        @php
                            $riderOnline = (bool) auth()->user()->rider->is_online;
                        @endphp
                        <div class="rider-online-toggle topbar-action-item"
                             id="rider-online-toggle"
                             data-toggle-url="{{ route('riders.toggle-online', ['rider' => auth()->user()->rider->uuid]) }}"
                             data-online="{{ $riderOnline ? '1' : '0' }}">
                            <button type="button"
                                    class="btn rider-online-btn {{ $riderOnline ? 'is-online' : 'is-offline' }}"
                                    id="rider-online-btn"
                                    title="{{ $riderOnline ? 'You prefer Online — click to go Offline' : 'You are Offline — click to go Online' }}">
                                <span class="rider-online-dot" aria-hidden="true"></span>
                                <span class="rider-online-label" id="rider-online-label">
                                    {{ $riderOnline ? 'Online' : 'Offline' }}
                                </span>
                                <span class="rider-online-hint d-none d-sm-inline">
                                    {{ $riderOnline ? 'Tap to go offline' : 'Tap to go online' }}
                                </span>
                            </button>
                        </div>
                    @endif
                    @if(auth()->user()->hasRole('shop'))
                        <div class="dropdown topbar-action-item"
                             id="app-notifications"
                             data-index-url="{{ route('api.notifications.index') }}"
                             data-unread-url="{{ route('api.notifications.unread-count') }}"
                             data-read-all-url="{{ route('api.notifications.read-all') }}"
                             data-read-url="{{ route('api.notifications.read', ['notification' => '__ID__']) }}">
                            <button class="btn topbar-notification dropdown-toggle"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    aria-expanded="false"
                                    title="Notifications">
                                <span class="topbar-icon-wrap">
                                    <i class="fa-solid fa-bell"></i>
                                    <span class="notification-badge d-none" id="notification-unread-badge">0</span>
                                </span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end notification-dropdown p-0">
                                <div class="notification-dropdown-header d-flex justify-content-between align-items-center">
                                    <strong>Notifications</strong>
                                    <button type="button" class="btn btn-link btn-sm p-0" id="notification-mark-all-read">
                                        Mark all read
                                    </button>
                                </div>
                                <div class="notification-dropdown-body" id="notification-list">
                                    <div class="text-center text-muted py-4 small">Loading...</div>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="dropdown topbar-action-item">
                        <button class="btn topbar-user dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <span class="user-avatar" id="topbar-user-avatar">
                                @if(auth()->user()->avatar)
                                    <img src="{{ \App\Helpers\MediaUrlHelper::url(auth()->user()->avatar) }}" alt="" class="user-avatar-image">
                                @else
                                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                                @endif
                            </span>
                            <span class="user-name d-none d-md-inline">{{ auth()->user()->name ?? 'User' }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text text-muted small">{{ auth()->user()->email ?? '' }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.index') }}">
                                    <i class="fa-solid fa-user me-2"></i> My Profile
                                </a>
                            </li>
                            @if(auth()->user()->hasRole('super_admin'))
                                <li>
                                    <a class="dropdown-item" href="{{ route('settings.index') }}">
                                        <i class="fa-solid fa-gear me-2"></i> System Settings
                                    </a>
                                </li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST" id="logout-form">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="admin-content">
                @yield('content')
            </main>
        </div>
    </div>

    @if(auth()->user()->hasRole('rider'))
        <div id="rider-delivery-offers"
             class="rider-offer-stack"
             data-offers-url="{{ route('deliveries.available-offers') }}"
             data-claim-url="{{ route('deliveries.claim', ['delivery' => '__ID__']) }}">
            <div class="rider-offer-stack-header">
                <div>
                    <span class="badge bg-success mb-1">New Deliveries</span>
                    <h6 class="mb-0">Available delivery offers</h6>
                    <p class="text-muted small mb-0 mt-1">Tap Accept or skip for later</p>
                </div>
                <span class="badge bg-primary" id="rider-offer-count">0</span>
            </div>
            <div id="rider-offer-stack-list" class="rider-offer-stack-list"></div>
        </div>

        <div id="rider-location-sender"
             class="d-none"
             data-url="{{ route('riders.location', ['rider' => auth()->user()->rider?->uuid]) }}"
             data-toggle-online="{{ route('riders.toggle-online', ['rider' => auth()->user()->rider?->uuid]) }}"
             data-online="{{ auth()->user()->rider?->is_online ? '1' : '0' }}"
             data-heartbeat="{{ route('riders.heartbeat', ['rider' => auth()->user()->rider?->uuid]) }}"></div>
    @endif

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script src="{{ asset('js/core/notification-helper.js') }}"></script>
    <script src="{{ asset('js/core/ajax-helper.js') }}"></script>
    <script src="{{ asset('js/core/datatable-helper.js') }}"></script>
    <script src="{{ asset('js/core/form-helper.js') }}"></script>
    <script src="{{ asset('js/core/realtime-helper.js') }}"></script>
    <script src="{{ asset('js/core/app-notifications.js') }}"></script>
    @if(auth()->user()->hasRole('rider'))
        <script src="{{ asset('js/core/rider-delivery-offers.js') }}"></script>
        <script src="{{ asset('js/core/rider-location-sender.js') }}"></script>
        <script src="{{ asset('js/core/rider-online-toggle.js') }}"></script>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.NotificationHelper) {
                window.NotificationHelper.initToastr();
            }

            const toggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('admin-sidebar');
            if (toggle && sidebar) {
                toggle.addEventListener('click', function () {
                    sidebar.classList.toggle('is-open');
                });
            }

            const logoutForm = document.getElementById('logout-form');
            if (logoutForm && window.AjaxHelper) {
                logoutForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    window.AjaxHelper.post(logoutForm.action, {}, {
                        success: function () {
                            window.location.href = '{{ route('login') }}';
                        },
                    });
                });
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
