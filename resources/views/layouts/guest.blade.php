<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@php
    $branding = app(\App\Services\AppSettingService::class)->publicPayload();
    $brandName = $branding['app_name'] ?? config('app.name', 'NASO');
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="login-url" content="{{ route('login') }}">
    <title>@yield('title', 'Login - '.$brandName)</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <link href="{{ asset('css/app-custom.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body class="guest-body">
    <div id="global-loading-overlay" class="loading-overlay" aria-hidden="true">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="loading-text mt-3 mb-0">Signing in...</p>
        </div>
    </div>

    <div class="guest-wrapper">
        <div class="guest-card">
            <div class="guest-brand text-center mb-4">
                <div class="guest-logo {{ !empty($branding['app_logo_url']) ? 'guest-logo--image' : '' }}">
                    @if(!empty($branding['app_logo_url']))
                        <img src="{{ $branding['app_logo_url'] }}"
                             alt="{{ $brandName }}"
                             class="guest-logo-image"
                             width="60"
                             height="60"
                             decoding="async">
                    @else
                        <i class="fa-solid fa-truck-fast"></i>
                    @endif
                </div>
                <h1 class="guest-title">{{ $brandName }}</h1>
                <p class="guest-subtitle text-muted mb-0">@yield('subtitle', 'Sign in to your account')</p>
            </div>

            @yield('content')
        </div>

        <p class="guest-footer text-center text-muted">
            &copy; {{ date('Y') }} {{ $brandName }}. All rights reserved.
        </p>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="{{ asset('js/core/notification-helper.js') }}"></script>
    <script src="{{ asset('js/core/ajax-helper.js') }}"></script>

    @stack('scripts')
</body>
</html>
