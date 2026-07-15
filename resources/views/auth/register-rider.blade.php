@extends('layouts.guest')

@section('title', 'Rider Registration - ' . config('app.name'))
@section('subtitle', 'Join as a delivery rider')

@section('content')
    <form id="register-rider-form" action="{{ route('register.rider') }}" method="POST" enctype="multipart/form-data" novalidate>
        @csrf

        <p class="text-muted small mb-3"><i class="fa-solid fa-user me-1"></i> Account details</p>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                <div class="invalid-feedback d-block" data-error="name"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required>
                <div class="invalid-feedback d-block" data-error="email"></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="phone" class="form-label">Phone</label>
                <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone') }}">
                <div class="invalid-feedback d-block" data-error="phone"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="password" name="password" required>
                <div class="invalid-feedback d-block" data-error="password"></div>
            </div>
        </div>
        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirm Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
            <div class="invalid-feedback d-block" data-error="password_confirmation"></div>
        </div>

        <hr class="my-3">

        <p class="text-muted small mb-3"><i class="fa-solid fa-motorcycle me-1"></i> Vehicle details</p>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="vehicle_type" class="form-label">Vehicle Type</label>
                <select class="form-select" id="vehicle_type" name="vehicle_type">
                    <option value="">Select type</option>
                    <option value="motorcycle" @selected(old('vehicle_type') === 'motorcycle')>Motorcycle</option>
                    <option value="bicycle" @selected(old('vehicle_type') === 'bicycle')>Bicycle</option>
                    <option value="car" @selected(old('vehicle_type') === 'car')>Car</option>
                    <option value="van" @selected(old('vehicle_type') === 'van')>Van</option>
                    <option value="scooter" @selected(old('vehicle_type') === 'scooter')>Scooter</option>
                </select>
                <div class="invalid-feedback d-block" data-error="vehicle_type"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="vehicle_number" class="form-label">Vehicle Number</label>
                <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" value="{{ old('vehicle_number') }}">
                <div class="invalid-feedback d-block" data-error="vehicle_number"></div>
            </div>
        </div>
        <div class="mb-3">
            <label for="license_number" class="form-label">License Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="license_number" name="license_number" value="{{ old('license_number') }}" required>
            <div class="invalid-feedback d-block" data-error="license_number"></div>
        </div>

        <hr class="my-3">

        <p class="text-muted small mb-3"><i class="fa-solid fa-file-circle-check me-1"></i> Verification documents</p>
        <p class="text-muted small">Upload clear photos or PDF scans. Max 5MB each (JPG, PNG, PDF).</p>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="document_nid" class="form-label">National ID (NID) <span class="text-muted">(Optional)</span></label>
                <input type="file" class="form-control" id="document_nid" name="documents[nid]" accept=".jpg,.jpeg,.png,.pdf">
                <div class="invalid-feedback d-block" data-error="documents.nid"></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="document_license" class="form-label">Driving License <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="document_license" name="documents[license]" accept=".jpg,.jpeg,.png,.pdf" required>
                <div class="invalid-feedback d-block" data-error="documents.license"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="document_blue_book" class="form-label">Blue Book <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="document_blue_book" name="documents[blue_book]" accept=".jpg,.jpeg,.png,.pdf" required>
                <div class="invalid-feedback d-block" data-error="documents.blue_book"></div>
            </div>
        </div>
        <div class="mb-4">
            <label for="document_citizenship" class="form-label">Citizenship <span class="text-danger">*</span></label>
            <input type="file" class="form-control" id="document_citizenship" name="documents[citizenship]" accept=".jpg,.jpeg,.png,.pdf" required>
            <div class="invalid-feedback d-block" data-error="documents.citizenship"></div>
        </div>

        <button type="submit" class="btn btn-primary w-100 mb-3" id="register-submit">
            <span class="btn-text"><i class="fa-solid fa-motorcycle me-2"></i>Register as Rider</span>
            <span class="btn-spinner d-none">
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>Registering...
            </span>
        </button>

        <p class="text-center text-muted small mb-0">
            Already have an account?
            <a href="{{ route('login') }}">Sign in</a>
            &nbsp;|&nbsp;
            <a href="{{ route('register.shop') }}">Register as Shop</a>
        </p>
    </form>
@endsection

@push('styles')
    <style>
        .guest-card { max-width: 820px; }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/core/form-helper.js') }}"></script>
    <script src="{{ asset('js/auth/register.js') }}"></script>
@endpush
