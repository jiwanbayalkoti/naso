@extends('layouts.guest')

@section('title', 'Login - ' . config('app.name'))
@section('subtitle', 'Sign in to your account')

@section('content')
    <form id="login-form" action="{{ route('login') }}" method="POST" data-redirect="{{ route('dashboard') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus>
            </div>
            <div class="invalid-feedback d-block" data-error="email"></div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                <button type="button" class="btn btn-outline-secondary" id="toggle-password" tabindex="-1">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <div class="invalid-feedback d-block" data-error="password"></div>
        </div>

        <div class="mb-4 form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>

        <button type="submit" class="btn btn-primary w-100" id="login-submit">
            <span class="btn-text"><i class="fa-solid fa-right-to-bracket me-2"></i>Sign In</span>
            <span class="btn-spinner d-none">
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>Signing in...
            </span>
        </button>

        <p class="text-center text-muted small mt-4 mb-0">
            Don't have an account?<br>
            <a href="{{ route('register.shop') }}">Register as Shop</a>
            &nbsp;|&nbsp;
            <a href="{{ route('register.rider') }}">Register as Rider</a>
        </p>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/core/form-helper.js') }}"></script>
    <script src="{{ asset('js/auth/login.js') }}"></script>
@endpush
