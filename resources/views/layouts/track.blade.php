<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="google-maps-api-key" content="{{ $googleMapsApiKey ?? '' }}">
    <title>@yield('title', 'Track Delivery - ' . config('app.name'))</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/app-custom.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body class="guest-body bg-light">
    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ url('/') }}">
                <i class="fa-solid fa-truck-fast me-2 text-primary"></i>{{ config('app.name') }}
            </a>
        </div>
    </nav>

    <main class="container py-4">
        @yield('content')
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/core/google-maps-tracker.js') }}"></script>

    @stack('scripts')
</body>
</html>
