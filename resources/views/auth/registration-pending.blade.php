@extends('layouts.guest')

@section('title', 'Registration Submitted - ' . config('app.name'))
@section('subtitle', 'Waiting for admin approval')

@section('content')
    <div class="text-center mb-4">
        <div class="guest-logo mb-3">
            <i class="fa-solid fa-hourglass-half text-warning" style="font-size: 3rem;"></i>
        </div>
        <h2 class="h4 mb-3">Registration Submitted</h2>
        <p class="text-muted mb-0">
            Thank you for registering. Your documents have been sent to the admin for review.
            You will be able to login after your account is approved.
        </p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info small">
        <i class="fa-solid fa-circle-info me-1"></i>
        This usually takes some time. Please check back later and try logging in once approved.
    </div>

    <a href="{{ route('login') }}" class="btn btn-primary w-100">
        <i class="fa-solid fa-right-to-bracket me-2"></i>Back to Login
    </a>
@endsection

@push('styles')
    <style>
        .guest-card { max-width: 520px; }
    </style>
@endpush
